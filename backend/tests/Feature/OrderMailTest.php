<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderMovedMail;
use App\Mail\OrderRefundedMail;
use App\Mail\PaymentReceivedMail;
use App\Mail\PaymentReminderMail;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\PdfRenderer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class OrderMailTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->tenant->update(['mail_from_address' => 'buch@example.com', 'mail_reply_to' => 'anna@example.com']);
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        Mail::fake();
    }

    protected function order(string $method, string $locale = 'de'): Order
    {
        $base = "/api/tenants/{$this->tenant->slug}/orders";
        $token = $this->postJson($base, [
            'email' => 'leserin@example.com',
            'locale' => $locale,
            'copies' => [['book_id' => $this->tenant->books()->first()->id, 'recipient_name' => 'Mia']],
        ])->json('token');
        $this->patchJson("$base/$token", [
            'billing' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'street' => 'Langstrasse 1', 'postal_code' => '8004', 'town' => 'Zürich'],
            'payment_method' => $method,
        ]);
        $this->postJson("$base/$token/submit")->assertOk();

        return Order::firstWhere('token', $token);
    }

    public function test_invoice_order_gets_confirmation_with_invoice_from_the_shop(): void
    {
        $order = $this->order('invoice_advance');

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($order) {
            $renderer = Mockery::mock(PdfRenderer::class);
            $renderer->shouldReceive('render')->andReturn('%PDF');
            $this->app->instance(PdfRenderer::class, $renderer);

            return $mail->hasTo('leserin@example.com')
                && $mail->hasFrom('buch@example.com', 'Anna Rosenwasser')
                && $mail->hasReplyTo('anna@example.com')
                && $mail->hasSubject("Deine Bestellung {$order->number}")
                && count($mail->attachments()) === 1;
        });
    }

    public function test_confirmation_renders_in_the_customers_language(): void
    {
        $order = $this->order('invoice_in_parcel', 'en');

        $mail = new OrderConfirmationMail($order);
        $html = $mail->render();

        $this->assertStringContainsString('Thank you for your order', $html);
        $this->assertStringContainsString('The invoice comes with your parcel.', $html);
        $this->assertStringContainsString($order->storefrontUrl(), $html);
        $this->assertSame([], $mail->attachments());
    }

    public function test_online_order_is_confirmed_only_after_payment(): void
    {
        $order = $this->order('online');
        Mail::assertNotQueued(OrderConfirmationMail::class);

        $payment = $order->payments()->sole();
        app(PaymentService::class)->markPaid($payment);

        Mail::assertQueued(OrderConfirmationMail::class, 1);
        Mail::assertNotQueued(PaymentReceivedMail::class);
    }

    public function test_bank_payment_sends_payment_received(): void
    {
        $order = $this->order('invoice_advance');

        app(PaymentService::class)->markOrderPaid($order, now(), User::first());

        Mail::assertQueued(PaymentReceivedMail::class);
    }

    public function test_reminder_is_sent_once_close_to_the_deadline(): void
    {
        $order = $this->order('invoice_advance');
        $order->update(['submitted_at' => now()->subDays(5)]);

        // Deadline still far away: no reminder.
        Artisan::call('orders:send-payment-reminders');
        Mail::assertNotQueued(PaymentReminderMail::class);

        $order->aktion->update(['order_deadline' => now()->addDays(2)]);
        Artisan::call('orders:send-payment-reminders');
        Artisan::call('orders:send-payment-reminders');

        Mail::assertQueued(PaymentReminderMail::class, 1);
    }

    public function test_unpaid_orders_wait_for_a_decision_by_default(): void
    {
        $order = $this->order('invoice_advance');
        $first = $order->aktion;

        $first->update(['order_deadline' => now()->subMinute()]);
        Artisan::call('aktionen:sync-status');

        $order->refresh();
        $this->assertTrue($order->aktion->is($first));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        Mail::assertNotQueued(OrderMovedMail::class);
    }

    public function test_unpaid_orders_move_once_then_get_cancelled(): void
    {
        $this->tenant->update(['settings' => [...($this->tenant->settings ?? []), 'unpaid_after_deadline' => 'move']]);
        $order = $this->order('invoice_advance');
        $first = $order->aktion;
        $next = $this->tenant->aktionen()->where('status', AktionStatus::Scheduled)->first();

        $first->update(['order_deadline' => now()->subMinute()]);
        Artisan::call('aktionen:sync-status');

        $order->refresh();
        $this->assertTrue($order->aktion->is($next));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        Mail::assertQueued(OrderMovedMail::class);

        // The next Aktion opens, then also closes without payment.
        $next->update(['status' => AktionStatus::Open, 'orders_open_at' => now()->subDay(), 'order_deadline' => now()->subMinute()]);
        Artisan::call('aktionen:sync-status');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        Mail::assertQueued(OrderCancelledMail::class);
    }

    public function test_paid_orders_stay_when_the_aktion_closes(): void
    {
        $order = $this->order('invoice_in_parcel');
        $order->aktion->update(['order_deadline' => now()->subMinute()]);

        Artisan::call('aktionen:sync-status');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(AktionStatus::Closed, $order->aktion->fresh()->status);
    }

    public function test_refund_mail_contains_amount(): void
    {
        $order = $this->order('invoice_advance');
        $payments = app(PaymentService::class);
        $payments->markOrderPaid($order, now(), User::first());
        $payments->refund($order->fresh(), 1500, null, User::first());

        Mail::assertQueued(OrderRefundedMail::class, fn (OrderRefundedMail $mail) => str_contains($mail->render(), 'CHF 15.00'));
    }
}
