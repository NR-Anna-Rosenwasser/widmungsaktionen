<?php

namespace App\Mail;

class PaymentReceivedMail extends OrderMail
{
    protected function key(): string
    {
        return 'payment_received';
    }
}
