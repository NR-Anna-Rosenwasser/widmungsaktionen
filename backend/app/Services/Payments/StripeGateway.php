<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Checkout with each tenant's own keys. Payment methods (card, TWINT, …)
 * are chosen in the tenant's Stripe dashboard.
 */
class StripeGateway implements PaymentGateway
{
    public function isConfigured(Tenant $tenant): bool
    {
        return filled($tenant->stripe_publishable_key) && filled($tenant->stripe_secret_key);
    }

    protected function client(Tenant $tenant): StripeClient
    {
        return new StripeClient($tenant->stripe_secret_key);
    }

    public function createCheckout(Order $order, Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $order->loadMissing('items.book', 'tenant');

        $lines = $order->items
            ->groupBy(fn ($item) => $item->book_id.'-'.$item->unit_price)
            ->map(fn ($items) => [
                'quantity' => $items->count(),
                'price_data' => [
                    'currency' => 'chf',
                    'unit_amount' => $items->first()->unit_price,
                    'product_data' => ['name' => $items->first()->book->title.' (signiert)'],
                ],
            ])
            ->values()
            ->all();

        if ($order->shipping_cost > 0) {
            $lines[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'chf',
                    'unit_amount' => $order->shipping_cost,
                    'product_data' => ['name' => $order->locale === 'en' ? 'Shipping' : 'Versand'],
                ],
            ];
        }

        $session = $this->client($order->tenant)->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lines,
            'customer_email' => $order->email,
            'client_reference_id' => $order->number,
            'locale' => $order->locale === 'en' ? 'en' : 'de',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'order_id' => (string) $order->id,
                'payment_id' => (string) $payment->id,
            ],
            'payment_intent_data' => [
                'description' => "Bestellung {$order->number}",
                'metadata' => ['order_id' => (string) $order->id, 'payment_id' => (string) $payment->id],
            ],
        ], ['idempotency_key' => "checkout-payment-{$payment->id}"]);

        $payment->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    public function refund(Payment $payment, int $amount): string
    {
        $refund = $this->client($payment->order->tenant)->refunds->create([
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => $amount,
        ]);

        return $refund->id;
    }

    public function parseWebhook(Tenant $tenant, string $payload, ?string $signature): array
    {
        if (blank($tenant->stripe_webhook_secret) || blank($signature)) {
            throw new InvalidWebhookException('Webhook secret or signature missing.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $tenant->stripe_webhook_secret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhookException($e->getMessage(), previous: $e);
        }

        return $event->toArray();
    }
}
