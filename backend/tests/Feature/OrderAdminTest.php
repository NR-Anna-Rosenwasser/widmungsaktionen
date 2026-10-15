<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\InvoicePdf;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');

        $base = "/api/tenants/{$this->tenant->slug}/orders";
        $token = $this->postJson($base, [
            'email' => 'leserin@example.com',
            'copies' => [['book_id' => $this->tenant->books()->first()->id]],
        ])->json('token');
        $this->patchJson("$base/$token", [
            'billing' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'street' => 'Langstrasse 1', 'postal_code' => '8004', 'town' => 'Zürich'],
            'payment_method' => 'invoice_advance',
        ]);
        $this->postJson("$base/$token/submit")->assertOk();
        $this->order = Order::firstWhere('token', $token);

        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    public function test_view_page_renders(): void
    {
        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/orders/{$this->order->id}")
            ->assertOk()
            ->assertSee($this->order->number)
            ->assertSee('Zahlungen');
    }

    public function test_mark_paid_refund_and_cancel(): void
    {
        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('markPaid', ['paid_at' => now()->toDateString(), 'note' => 'ZKB'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Confirmed, $this->order->fresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('refund', ['amount' => '33.00', 'reason' => 'Doppelt bestellt', 'cancel' => true])
            ->assertHasNoActionErrors();

        $order = $this->order->fresh();
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
    }

    public function test_move_to_another_aktion(): void
    {
        $next = $this->tenant->aktionen()->where('status', AktionStatus::Scheduled)->first();

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('move', ['aktion_id' => $next->id, 'notify' => false])
            ->assertHasNoActionErrors();

        $this->assertTrue($this->order->fresh()->aktion->is($next));
    }

    public function test_wrongly_marked_payment_can_be_reverted(): void
    {
        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('markPaid', ['paid_at' => now()->toDateString()])
            ->assertActionVisible('revertPayment')
            ->callAction('revertPayment', ['reason' => 'Falsche Bestellung'])
            ->assertHasNoActionErrors();

        $order = $this->order->fresh();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('pending', $order->payments()->sole()->status->value);

        // ... and can then be marked paid again.
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('markPaid', ['paid_at' => now()->toDateString()]);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_online_and_refunded_payments_cannot_be_reverted(): void
    {
        $payments = app(PaymentService::class);
        $payments->markOrderPaid($this->order, now(), auth()->user());
        $payments->refund($this->order->fresh(), 500, null, auth()->user());

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->assertActionHidden('revertPayment');
    }

    public function test_cancellation_can_be_undone(): void
    {
        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('cancel', ['reason' => 'Versehen', 'notify' => false])
            ->assertActionVisible('reopen')
            ->callAction('reopen')
            ->assertHasNoActionErrors();

        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->cancel_reason);
        // The invoice is payable again.
        $this->assertSame('pending', $order->payments()->sole()->status->value);
    }

    public function test_cancelled_invoice_says_so_and_has_no_payment_slip(): void
    {
        app(PaymentService::class)->cancel($this->order, null, notify: false);

        $html = app(InvoicePdf::class)->html($this->order->fresh());

        $this->assertStringContainsString('Diese Bestellung wurde storniert', $html);
        $this->assertStringNotContainsString('Bitte bezahle', $html);
        $this->assertStringNotContainsString('data:image/svg+xml;base64', $html);
        $this->getJson("/api/tenants/{$this->tenant->slug}/orders/{$this->order->token}")
            ->assertJsonPath('has_invoice', false)
            ->assertJsonPath('can_pay_online', false);
    }
}
