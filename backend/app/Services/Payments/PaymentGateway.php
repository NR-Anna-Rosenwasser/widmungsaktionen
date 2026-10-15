<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;

/**
 * Online payment provider (Stripe in production, a fake in tests).
 */
interface PaymentGateway
{
    public function isConfigured(Tenant $tenant): bool;

    /**
     * Start a hosted checkout for the payment and return the URL to redirect to.
     */
    public function createCheckout(Order $order, Payment $payment, string $successUrl, string $cancelUrl): string;

    /**
     * Refund an amount (Rappen) of a paid online payment. Returns the refund id.
     */
    public function refund(Payment $payment, int $amount): string;

    /**
     * Verify a webhook payload and return the decoded event.
     *
     * @return array{type: string, data: array{object: array<string, mixed>}}
     *
     * @throws InvalidWebhookException
     */
    public function parseWebhook(Tenant $tenant, string $payload, ?string $signature): array;
}
