<?php

namespace App\Http\Controllers;

use App\Enums\PaymentState;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\InvalidWebhookException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Stripe → us. One endpoint per tenant, each with its own signing secret:
 * /api/webhooks/stripe/{slug}
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected PaymentService $payments,
    ) {}

    public function __invoke(Request $request, Tenant $tenant): Response
    {
        try {
            $event = $this->gateway->parseWebhook($tenant, $request->getContent(), $request->header('Stripe-Signature'));
        } catch (InvalidWebhookException $e) {
            Log::warning('Rejected Stripe webhook', ['tenant' => $tenant->slug, 'error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        $object = $event['data']['object'];

        match ($event['type']) {
            'checkout.session.completed' => $object['payment_status'] === 'paid'
                ? $this->paid($tenant, $object)
                : $this->linkIntent($tenant, $object),
            'checkout.session.async_payment_succeeded' => $this->paid($tenant, $object),
            'checkout.session.async_payment_failed' => $this->failed($tenant, $object),
            'checkout.session.expired' => $this->expired($tenant, $object),
            'charge.refunded' => $this->payments->syncStripeRefunds((string) $object['payment_intent'], (int) $object['amount_refunded']),
            default => null,
        };

        return response('ok');
    }

    /**
     * The payment belonging to a checkout session, only within this tenant.
     */
    protected function payment(Tenant $tenant, array $session): ?Payment
    {
        return Payment::query()
            ->where('stripe_checkout_session_id', $session['id'])
            ->whereHas('order', fn ($q) => $q->whereBelongsTo($tenant))
            ->first();
    }

    protected function linkIntent(Tenant $tenant, array $session): ?Payment
    {
        $payment = $this->payment($tenant, $session);
        $payment?->update(['stripe_payment_intent_id' => $session['payment_intent'] ?? null]);

        return $payment;
    }

    protected function paid(Tenant $tenant, array $session): void
    {
        if ($payment = $this->linkIntent($tenant, $session)) {
            $this->payments->markPaid($payment, meta: ['stripe_session' => $session['id']]);
        }
    }

    protected function failed(Tenant $tenant, array $session): void
    {
        if ($payment = $this->payment($tenant, $session)) {
            $payment->update(['status' => PaymentState::Failed]);
            $payment->order->log('payment_failed', public: true);
        }
    }

    protected function expired(Tenant $tenant, array $session): void
    {
        $payment = $this->payment($tenant, $session);
        if ($payment?->status === PaymentState::Pending) {
            $payment->update(['status' => PaymentState::Expired]);
        }
    }
}
