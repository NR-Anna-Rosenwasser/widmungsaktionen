<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantRole;
use App\Exceptions\OrderNotAllowed;
use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderConfirmationMail;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdminOrders;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

/**
 * Orders edited, entered and exported in the admin, e.g. an order that
 * should have gone to two addresses.
 */
class OrderManagementTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected User $admin;

    /** A gift order: one copy for a friend, one the buyer wanted for themself. */
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();

        $books = $this->tenant->books()->orderBy('sort')->pluck('id');
        $this->order = $this->submitOrder($this->tenant, copies: [
            ['book_id' => $books[0], 'recipient_name' => 'Lou', 'wish' => 'Alles Liebe'],
            ['book_id' => $books[1], 'recipient_name' => 'Mia'],
        ], overrides: [
            'is_gift' => true,
            'shipping' => ['first_name' => 'Lou', 'last_name' => 'Freundin', 'street' => 'Bahnhofstrasse 2', 'postal_code' => '3000', 'town' => 'Bern'],
            'gift_message' => 'Für dich!',
        ]);
        app(PaymentService::class)->markOrderPaid($this->order, now(), User::firstWhere('email', 'admin@widmungen.test'), notify: false);
        $this->order->refresh();

        $this->admin = User::firstWhere('email', 'admin@widmungen.test');
        $this->actingAs($this->admin);
        Filament::setTenant($this->tenant);
    }

    public function test_edit_switches_the_parcel_to_the_buyer(): void
    {
        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/orders/{$this->order->id}/edit")->assertOk();

        Livewire::test(EditOrder::class, ['record' => $this->order->id])
            ->assertSchemaStateSet(['is_gift' => true, 'shipping_town' => 'Bern'])
            ->fillForm(['is_gift' => false, 'email' => 'neu@example.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        $order = $this->order->fresh();
        $this->assertFalse($order->is_gift);
        $this->assertSame('neu@example.com', $order->email);
        $this->assertContains('8004 Zürich', $order->shippingAddressLines());
        $this->assertSame(['email', 'is_gift'], $order->events()->where('type', 'edited')->sole()->data['fields']);
    }

    public function test_changed_dedications_go_back_to_moderation_unless_checked(): void
    {
        $items = $this->order->items;
        $items->each->update(['moderation_status' => ModerationStatus::Approved]);
        $items[1]->update(['signed_at' => now()]);

        $editor = app(AdminOrders::class);
        $editor->update($this->order, ['items' => [
            ['id' => $items[0]->id, 'recipient_name' => 'Lou', 'wish' => 'Herzlich'],
            ['id' => $items[1]->id, 'recipient_name' => 'Jemand anderes'],
        ]], $this->admin);

        $this->assertSame('Herzlich', $items[0]->fresh()->wish);
        $this->assertSame(ModerationStatus::Pending, $items[0]->fresh()->moderation_status);
        // Already signed: unchanged.
        $this->assertSame('Mia', $items[1]->fresh()->recipient_name);

        $editor->update($this->order, ['dedications_checked' => true, 'items' => [
            ['id' => $items[0]->id, 'recipient_name' => 'Lou', 'wish' => 'Herzliche Grüsse'],
        ]], $this->admin);
        $this->assertSame(ModerationStatus::Approved, $items[0]->fresh()->moderation_status);
    }

    public function test_gift_with_invoice_in_parcel_is_rejected(): void
    {
        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel');

        $this->expectException(OrderNotAllowed::class);
        app(AdminOrders::class)->update($order, ['is_gift' => true], $this->admin);
    }

    public function test_drafts_cannot_be_edited(): void
    {
        $this->order->update(['status' => OrderStatus::Draft]);

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])->assertActionHidden('edit');
        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/orders/{$this->order->id}/edit")->assertNotFound();
    }

    public function test_cancelled_order_is_replaced_by_two_paid_orders(): void
    {
        Mail::fake();
        app(PaymentService::class)->cancel($this->order, 'Zwei Adressen', $this->admin, notify: false);
        $books = $this->tenant->books()->orderBy('sort')->pluck('id');

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->assertActionVisible(TestAction::make('duplicate'));

        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/orders/create?from={$this->order->id}")->assertOk();

        // The gift, to the friend.
        Livewire::withQueryParams(['from' => $this->order->id])
            ->test(CreateOrder::class)
            ->assertSchemaStateSet([
                'email' => 'leserin@example.com',
                'is_gift' => true,
                'aktion_id' => $this->aktion->id,
                'mark_paid' => true,
                'notify' => false,
                'paid_note' => "Bezahlt mit Bestellung {$this->order->number}",
            ])
            ->set('data.items', [['book_id' => $books[0], 'recipient_name' => 'Lou', 'wish' => 'Alles Liebe']])
            ->fillForm(['shipping_cost' => '3'])
            ->call('create')
            ->assertHasNoFormErrors();

        // The buyer's own copy, shipping already paid.
        Livewire::withQueryParams(['from' => $this->order->id])
            ->test(CreateOrder::class)
            ->set('data.items', [['book_id' => $books[1], 'recipient_name' => 'Mia']])
            ->fillForm(['is_gift' => false, 'shipping_cost' => '0'])
            ->call('create')
            ->assertHasNoFormErrors();

        [$gift, $own] = Order::query()->whereKeyNot($this->order->id)->orderBy('id')->get()->slice(-2)->values();

        foreach ([$gift, $own] as $order) {
            $this->assertSame(OrderStatus::Confirmed, $order->status);
            $this->assertSame(PaymentStatus::Paid, $order->payment_status);
            $this->assertSame("Bezahlt mit Bestellung {$this->order->number}", $order->payments()->sole()->meta['note']);
            $this->assertSame(['from' => $this->order->number], $order->events()->where('type', 'created_by_admin')->sole()->data);
            $this->assertSame(ModerationStatus::Approved, $order->items()->sole()->moderation_status);
            $this->assertStringStartsWith('AR-', $order->number);
        }

        $this->assertTrue($gift->is_gift);
        $this->assertSame(3000 + 300, $gift->total);
        $this->assertFalse($own->is_gift);
        $this->assertSame(2800, $own->total);
        $this->assertSame(2, $this->order->events()->where('type', 'replacement_created')->count());
        Mail::assertNothingQueued();
    }

    public function test_blank_form_uses_the_defaults(): void
    {
        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/orders/create")->assertOk();

        // Language, country and Aktion are left as preselected.
        Livewire::test(CreateOrder::class)
            ->assertSchemaStateSet(['locale' => 'de', 'billing_country' => 'CH', 'aktion_id' => $this->aktion->id, 'notify' => true])
            ->fillForm([
                'email' => 'neu@example.com',
                'billing_first_name' => 'Kim', 'billing_last_name' => 'Keller', 'billing_street' => 'Weg 3', 'billing_postal_code' => '4000', 'billing_town' => 'Basel',
                'payment_method' => 'online',
                'notify' => false,
            ])
            ->set('data.items', [['book_id' => $this->tenant->books()->first()->id, 'recipient_name' => 'Kim']])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::firstWhere('email', 'neu@example.com');
        $this->assertSame('de', $order->locale);
        $this->assertSame('CH', $order->billing_country);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_new_unpaid_order_gets_an_invoice_and_a_confirmation(): void
    {
        Mail::fake();

        $order = app(AdminOrders::class)->create($this->tenant, [
            'aktion_id' => $this->aktion->id,
            'email' => 'telefon@example.com',
            'billing_first_name' => 'Kim', 'billing_last_name' => 'Keller', 'billing_street' => 'Weg 3', 'billing_postal_code' => '4000', 'billing_town' => 'Basel',
            'payment_method' => 'invoice_advance',
            'items' => [['book_id' => $this->tenant->books()->first()->id, 'signature_only' => true]],
            'notify' => true,
        ], $this->admin);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNotNull($order->payments()->sole()->reference);
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_online_orders_entered_in_the_admin_are_confirmed_too(): void
    {
        Mail::fake();
        $base = [
            'aktion_id' => $this->aktion->id,
            'payment_method' => 'online',
            'items' => [['book_id' => $this->tenant->books()->first()->id]],
            'notify' => true,
        ];

        // Replacement of an order paid by card, and one the customer pays later from the order page.
        $paid = app(AdminOrders::class)->create($this->tenant, [...$base, 'email' => 'bezahlt@example.com', 'mark_paid' => true], $this->admin);
        $open = app(AdminOrders::class)->create($this->tenant, [...$base, 'email' => 'offen@example.com'], $this->admin);

        foreach ([$paid, $open] as $order) {
            Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->order->is($order));
        }
        Mail::assertQueuedCount(2);
        $this->assertSame(PaymentStatus::Paid, $paid->payment_status);
    }

    public function test_test_aktion_gets_test_numbers_and_prepared_aktion_puts_it_on_the_stack(): void
    {
        $this->aktion->orders()->each(fn (Order $order) => $order->items()->update(['moderation_status' => ModerationStatus::Approved]));
        $this->prepareStack($this->aktion);
        $stacked = $this->aktion->orders()->max('stack_number');

        $order = app(AdminOrders::class)->create($this->tenant, [
            'aktion_id' => $this->aktion->id,
            'email' => 'spaet@example.com',
            'payment_method' => 'invoice_advance',
            'items' => [['book_id' => $this->tenant->books()->first()->id]],
            'mark_paid' => true,
            'add_to_stack' => true,
        ], $this->admin);
        $this->assertSame($stacked + 1, $order->stack_number);

        $test = $this->tenant->aktionen()->create([
            ...$this->aktion->only(['orders_open_at', 'order_deadline', 'signing_date', 'estimated_shipping_date']),
            'name' => 'Test', 'status' => AktionStatus::Open, 'is_test' => true,
        ]);
        $test->books()->attach($this->tenant->books()->first()->id);
        $testOrder = app(AdminOrders::class)->create($this->tenant, [
            'aktion_id' => $test->id,
            'email' => 'test@example.com',
            'payment_method' => 'online',
            'items' => [['book_id' => $this->tenant->books()->first()->id]],
        ], $this->admin);
        $this->assertStringStartsWith('TEST-', $testOrder->number);
        // Online: no checkout started, the customer pays from the order page.
        $this->assertSame(0, $testOrder->payments()->count());
    }

    public function test_fulfillment_may_create_orders_widmen_may_not(): void
    {
        $fulfillment = User::create(['name' => 'Kim', 'email' => 'kim@example.com', 'password' => 'password']);
        $this->tenant->users()->attach($fulfillment, ['role' => TenantRole::Fulfillment->value]);
        $this->assertTrue($fulfillment->fresh()->can('create', Order::class));

        $widmen = User::firstWhere('email', 'anna@widmungen.test');
        $this->assertFalse($widmen->can('create', Order::class));
    }

    public function test_export_from_the_order_list(): void
    {
        Storage::fake('local');
        $other = $this->submitOrder($this->tenant, overrides: ['email' => 'zweite@example.com']);

        // Another shop's order must never end up in the file.
        $foreign = Tenant::create(['name' => 'Andere', 'slug' => 'andere', 'order_prefix' => 'XX']);
        $foreign->orders()->create(['aktion_id' => $this->aktion->id, 'email' => 'fremd@example.com', 'status' => OrderStatus::Confirmed, 'number' => 'XX-1']);

        Livewire::test(ListOrders::class)
            ->set('activeTab', 'confirmed')
            ->callAction(TestAction::make('export')->table())
            ->assertHasNoActionErrors();

        $export = Export::query()->sole();
        $this->assertSame(1, $export->total_rows);
        $csv = $this->download($export);
        $this->assertStringContainsString($this->order->number, $csv);
        $this->assertStringNotContainsString($other->number, $csv);
        $this->assertStringNotContainsString('fremd@example.com', $csv);
        $this->assertStringContainsString('1× Herz, 1× Rosa Buch', $csv);
        $this->assertStringContainsString('"Lou Freundin";"Bahnhofstrasse 2, 3000 Bern"', $csv);

        Livewire::test(ListOrders::class)
            ->set('activeTab', 'active')
            ->selectTableRecords([$other->id])
            ->callAction(TestAction::make('export')->table()->bulk())
            ->assertHasNoActionErrors();

        $csv = $this->download(Export::query()->latest('id')->first());
        $this->assertStringContainsString($other->number, $csv);
        $this->assertStringNotContainsString($this->order->number, $csv);
    }

    public function test_exporter_columns(): void
    {
        $this->order->load('items.book', 'payments', 'aktion');
        $columns = collect(OrderExporter::getColumns())->keyBy(fn ($column) => $column->getName());

        $this->assertSame('1× Herz, 1× Rosa Buch', OrderExporter::booksLine($this->order));
        $this->assertSame(';', OrderExporter::getCsvDelimiter());
        $this->assertTrue($columns->has('shipping_address'));
    }

    protected function download(Export $export): string
    {
        // The export jobs ran in this process (sync queue) and switched the auth user.
        $this->actingAs($this->admin);

        return $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();
    }
}
