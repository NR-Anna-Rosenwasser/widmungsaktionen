<?php

namespace App\Listeners;

use App\Events\OrderSubmitted;
use App\Jobs\SendNewsletterOptIn;

class ForwardNewsletterOptIn
{
    public function handle(OrderSubmitted $event): void
    {
        if ($event->order->newsletter_opt_in && filled($event->order->tenant->newsletter_webhook_url)) {
            SendNewsletterOptIn::dispatch($event->order);
        }
    }
}
