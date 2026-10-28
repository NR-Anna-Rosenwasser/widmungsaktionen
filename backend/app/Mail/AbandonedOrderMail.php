<?php

namespace App\Mail;

class AbandonedOrderMail extends OrderMail
{
    protected function key(): string
    {
        return 'abandoned';
    }

    protected function data(): array
    {
        $tenant = $this->order->tenant;

        return [
            'resumeUrl' => $this->order->resumeUrl(),
            'optOutUrl' => route('orders.reminders.opt-out', ['tenant' => $tenant, 'token' => $this->order->token]),
        ];
    }
}
