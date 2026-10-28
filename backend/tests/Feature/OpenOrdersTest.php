<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Filament\Pages\Versand;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Livewire\Versand\OpenOrdersTable;
use App\Mail\OrderMovedMail;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShippingService;
use App\Services\SigningService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * «Offene Bestellungen» on «Vorbereiten & Versand» and the deadline setting.
 */
class OpenOrdersTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected Aktion $next;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();
        $this->next = $this->tenant->aktionen()->where('status', AktionStatus::Scheduled)->first();
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    protected function table()
    {
        return Livewire::test(OpenOrdersTable::class, ['aktionId' => $this->aktion->id]);
    }

    protected function approved(string $method, array $overrides = []): Order
    {
        $order = $this->submitOrder($this->tenant, $method, overrides: $overrides);
        $order->items()->update(['moderation_status' => ModerationStatus::Approved]);

        return $order->fresh();
    }

    public function test_late_payment_before_preparing_and_after(): void
    {
        $paid = $this->approved('invoice_in_parcel');
        $late = $this->approved('invoice_advance', ['email' => 'late@example.com']);
        $later = $this->approved('invoice_advance', ['email' => 'later@example.com']);
        $this->aktion->update(['order_deadline' => now()->subMinute()]);

        // Before preparing: only unpaid orders, the rest waits for the stack.
        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])->assertSeeLivewire(OpenOrdersTable::class);
        $this->table()
            ->assertCanSeeTableRecords([$late, $later])
            ->assertCanNotSeeTableRecords([$paid])
            ->callTableAction('markPaid', $late, ['paid_at' => now()->toDateString()])
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$late]);

        app(SigningService::class)->prepare($this->aktion);
        $this->assertSame(2, $late->fresh()->stack_number);

        // After preparing: a transfer that arrives late goes on the stack with the next number.
        $this->table()
            ->assertSee('Unbezahlt')
            ->callTableAction('markPaid', $later, ['paid_at' => now()->toDateString()])
            ->assertSee('Bezahlt, noch kein Post-it')
            ->callTableAction('addToStack', $later)
            ->assertHasNoTableActionErrors();

        $this->assertSame(3, $later->fresh()->stack_number);
    }

    public function test_decide_per_order_or_in_bulk(): void
    {
        $paid = $this->approved('invoice_in_parcel');
        $move = $this->approved('invoice_advance', ['email' => 'move@example.com']);
        $cancel = $this->approved('invoice_advance', ['email' => 'cancel@example.com']);
        $this->prepareStack($this->aktion);

        $this->table()
            ->assertSee('Noch nicht fertig gewidmet')
            ->filterTable('reason', 'unpaid')
            ->assertCanSeeTableRecords([$move, $cancel])
            ->assertCanNotSeeTableRecords([$paid])
            ->callTableBulkAction('moveSelected', [$move], ['aktion_id' => $this->next->id, 'notify' => true])
            ->assertHasNoTableBulkActionErrors()
            ->callTableBulkAction('cancelSelected', [$cancel], ['reason' => 'Nicht bezahlt', 'notify' => false])
            ->assertHasNoTableBulkActionErrors();

        $this->assertTrue($move->fresh()->aktion->is($this->next));
        $this->assertSame(1, $move->fresh()->moved_count);
        Mail::assertQueued(OrderMovedMail::class, 1);
        $this->assertSame(OrderStatus::Cancelled, $cancel->fresh()->status);

        // The last open order is signed and shipped: the Aktion is done.
        $signing = app(SigningService::class);
        $paid->items->each(fn ($item) => $signing->sign($item, auth()->user()));
        app(ShippingService::class)->shipMany([$paid->fresh()], now(), auth()->user(), notify: false);

        $this->assertSame(AktionStatus::Done, $this->aktion->fresh()->status);
        $this->table()->assertSee('Nichts offen');
    }

    public function test_signed_orders_cannot_move(): void
    {
        $order = $this->approved('invoice_in_parcel');
        $this->prepareStack($this->aktion);
        app(SigningService::class)->sign($order->items->first(), auth()->user());

        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('move');
    }

    public function test_existing_shops_switch_to_deciding_themselves(): void
    {
        $this->tenant->update(['settings' => [...($this->tenant->settings ?? []), 'unpaid_after_deadline' => 'cancel']]);

        $migration = require database_path('migrations/2026_10_29_110000_switch_unpaid_after_deadline_to_manual.php');
        $migration->up();

        $this->assertSame('manual', $this->tenant->fresh()->setting('unpaid_after_deadline'));
        // Everything else is kept.
        $this->assertSame('#2B3C69', $this->tenant->fresh()->setting('storefront.colors.primary'));
        $this->assertSame(1, DB::table('tenants')->count());
    }
}
