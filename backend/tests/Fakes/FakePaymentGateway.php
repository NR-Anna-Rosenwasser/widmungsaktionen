<?php

namespace Tests\Fakes;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\InvalidWebhookException;
use App\Services\Payments\PaymentGateway;

class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, array{payment: int, amount: int}> */
    public array $refunds = [];

    public bool $configured = true;

    public function isConfigured(Tenant $tenant): bool
    {
        return $this->configured;
    }

    public function createCheckout(Order $order, Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $payment->update(['stripe_checkout_session_id' => "cs_test_{$payment->id}"]);

        return "https://checkout.stripe.test/cs_test_{$payment->id}";
    }

    public function refund(Payment $payment, int $amount): string
    {
        $this->refunds[] = ['payment' => $payment->id, 'amount' => $amount];

        return 're_test_'.count($this->refunds);
    }

    public function parseWebhook(Tenant $tenant, string $payload, ?string $signature): array
    {
        if ($signature !== 'valid') {
            throw new InvalidWebhookException('bad signature');
        }

        return json_decode($payload, true);
    }
}
