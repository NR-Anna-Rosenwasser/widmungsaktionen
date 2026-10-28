<?php

namespace App\Listeners;

use App\Events\OrderSubmitted;
use App\Jobs\SendNewsletterOptIn;

class ForwardNewsletterOptIn
{
    public function handle(OrderSubmitted $event): void
    {
        $order = $event->order;

        if (! $order->newsletter_opt_in || blank($order->tenant->newsletter_webhook_url)) {
            return;
        }

        // Test orders must not add real subscribers.
        if ($order->isTest()) {
            $order->log('newsletter_skipped_test');

            return;
        }

        SendNewsletterOptIn::dispatch($order);
    }
}
