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
            'resumeUrl' => $tenant->storefrontUrl('/bestellen?t='.$this->order->token),
            'optOutUrl' => route('orders.reminders.opt-out', ['tenant' => $tenant, 'token' => $this->order->token]),
        ];
    }
}
