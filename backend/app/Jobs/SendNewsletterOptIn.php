<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Tells the shop's newsletter automation (e.g. n8n → Notifuse) about an
 * opt-in from the order form. The newsletter tool sends its own double
 * opt-in confirmation.
 *
 * Payload is JSON, signed with HMAC-SHA256 over "<timestamp>.<body>" using
 * the shop's webhook secret:
 *   X-Widmungen-Timestamp: 1760000000
 *   X-Widmungen-Signature: sha256=<hex>
 */
class SendNewsletterOptIn implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public Order $order) {}

    /**
     * Retry for about a day if the automation is down.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function handle(): void
    {
        $tenant = $this->order->tenant;
        if (blank($tenant->newsletter_webhook_url)) {
            return;
        }

        $body = json_encode([
            'event' => 'newsletter.opt_in',
            'shop' => $tenant->slug,
            'email' => $this->order->email,
            'first_name' => $this->order->billing_first_name,
            'last_name' => $this->order->billing_last_name,
            'locale' => $this->order->locale,
            'source' => 'widmungen',
            'order_number' => $this->order->number,
            'opted_in_at' => ($this->order->submitted_at ?? now())->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $timestamp = (string) now()->timestamp;
        $headers = ['X-Widmungen-Timestamp' => $timestamp];
        if (filled($tenant->newsletter_webhook_secret)) {
            $headers['X-Widmungen-Signature'] = 'sha256='.hash_hmac('sha256', "$timestamp.$body", $tenant->newsletter_webhook_secret);
        }

        Http::timeout(15)
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($tenant->newsletter_webhook_url)
            ->throw();

        $this->order->log('newsletter_sent');
    }
}
