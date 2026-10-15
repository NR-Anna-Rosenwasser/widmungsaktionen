<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    protected function submittedOrder(string $method, int $copies = 1): Order
    {
        $base = "/api/tenants/{$this->tenant->slug}/orders";
        $book = $this->tenant->books()->firstWhere('title', 'Herz');

        $token = $this->postJson($base, [
            'email' => 'leserin@example.com',
            'copies' => array_fill(0, $copies, ['book_id' => $book->id]),
        ])->json('token');

        $this->patchJson("$base/$token", [
            'billing' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'street' => 'Langstrasse 1', 'postal_code' => '8004', 'town' => 'Zürich'],
            'payment_method' => $method,
        ])->assertOk();

        $this->response = $this->postJson("$base/$token/submit")->assertOk();

        return Order::firstWhere('token', $token);
    }

    protected function webhook(string $type, array $object, string $signature = 'valid')
    {
        return $this->call(
            'POST',
            "/api/webhooks/stripe/{$this->tenant->slug}",
            server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['type' => $type, 'data' => ['object' => $object]]),
        );
    }

    public function test_invoice_orders_get_a_creditor_reference(): void
    {
        $order = $this->submittedOrder('invoice_advance');
        $payment = $order->payments()->sole();

        $this->assertSame(PaymentState::Pending, $payment->status);
        $this->assertSame(3300, $payment->amount);
        $this->assertMatchesRegularExpression('/^RF\d{2}AR'.now()->year.'0001$/', $payment->reference);
        $this->response->assertJsonPath('next.type', 'confirmation');
    }

    public function test_qr_iban_gets_a_qr_reference(): void
    {
        $this->tenant->update(['iban' => 'CH4431999123000889012']);

        $reference = $this->submittedOrder('invoice_advance')->payments()->sole()->reference;

        $this->assertMatchesRegularExpression('/^\d{27}$/', $reference);
    }

    public function test_online_orders_redirect_to_checkout(): void
    {
        $order = $this->submittedOrder('online');

        $this->response->assertJsonPath('next.type', 'redirect');
        $this->assertStringStartsWith('https://checkout.stripe.test/', $this->response->json('next.url'));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_online_is_not_offered_without_stripe(): void
    {
        $this->gateway->configured = false;

        $this->getJson("/api/tenants/{$this->tenant->slug}")
            ->assertJsonMissing(['payment_methods' => ['online', 'invoice_advance', 'invoice_in_parcel']])
            ->assertJsonPath('tenant.payment_methods', ['invoice_advance', 'invoice_in_parcel']);
    }

    public function test_webhook_marks_the_order_paid_once(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->submittedOrder('online');
        $payment = $order->payments()->sole();
        $session = ['id' => $payment->stripe_checkout_session_id, 'payment_status' => 'paid', 'payment_intent' => 'pi_123'];

        $this->webhook('checkout.session.completed', $session)->assertOk();
        $this->webhook('checkout.session.completed', $session)->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('pi_123', $payment->fresh()->stripe_payment_intent_id);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_webhook_rejects_bad_signatures(): void
    {
        $this->webhook('checkout.session.completed', ['id' => 'cs_x', 'payment_status' => 'paid'], 'forged')->assertStatus(400);
    }

    public function test_webhook_ignores_sessions_of_other_tenants(): void
    {
        $order = $this->submittedOrder('online');
        $other = Tenant::create(['name' => 'Anderer', 'slug' => 'anderer', 'order_prefix' => 'AN', 'settings' => []]);

        $this->call(
            'POST',
            "/api/webhooks/stripe/{$other->slug}",
            server: ['HTTP_STRIPE_SIGNATURE' => 'valid'],
            content: json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
                'id' => $order->payments()->sole()->stripe_checkout_session_id, 'payment_status' => 'paid', 'payment_intent' => 'pi_x',
            ]]]),
        )->assertOk();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_invoice_order_can_switch_to_online_payment(): void
    {
        $order = $this->submittedOrder('invoice_advance');

        $url = $this->postJson("/api/tenants/{$this->tenant->slug}/orders/{$order->token}/pay")->assertOk()->json('url');
        $this->assertStringStartsWith('https://checkout.stripe.test/', $url);

        $online = $order->payments()->where('method', 'online')->sole();
        $this->webhook('checkout.session.completed', ['id' => $online->stripe_checkout_session_id, 'payment_status' => 'paid', 'payment_intent' => 'pi_1'])->assertOk();

        $this->assertSame(PaymentState::Cancelled, $order->payments()->where('method', 'invoice_advance')->sole()->status);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_admin_can_mark_bank_payment(): void
    {
        $order = $this->submittedOrder('invoice_advance');
        $admin = User::firstWhere('email', 'admin@widmungen.test');

        app(PaymentService::class)->markOrderPaid($order, now()->subDay(), $admin, 'ZKB Auszug');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('ZKB Auszug', $order->payments()->sole()->meta['note']);
    }

    public function test_refunds_go_through_stripe_and_are_not_double_counted(): void
    {
        $order = $this->submittedOrder('online');
        $payment = $order->payments()->sole();
        $this->webhook('checkout.session.completed', ['id' => $payment->stripe_checkout_session_id, 'payment_status' => 'paid', 'payment_intent' => 'pi_9']);
        $admin = User::firstWhere('email', 'admin@widmungen.test');

        app(PaymentService::class)->refund($order->fresh(), 1000, 'Buch beschädigt', $admin);
        // Stripe then reports the same refund by webhook.
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_9', 'amount_refunded' => 1000])->assertOk();

        $this->assertSame([['payment' => $payment->id, 'amount' => 1000]], $this->gateway->refunds);
        $this->assertSame(1000, $payment->fresh()->refunded_amount);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);

        // A further refund made in the Stripe dashboard is picked up.
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_9', 'amount_refunded' => 3300])->assertOk();
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    public function test_cannot_refund_more_than_paid(): void
    {
        $order = $this->submittedOrder('invoice_advance');
        $admin = User::firstWhere('email', 'admin@widmungen.test');
        $payments = app(PaymentService::class);
        $payments->markOrderPaid($order, now(), $admin);

        $this->expectException(\RuntimeException::class);
        $payments->refund($order->fresh(), 5000, null, $admin);
    }
}
