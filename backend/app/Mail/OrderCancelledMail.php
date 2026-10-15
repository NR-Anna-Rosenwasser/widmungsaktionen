<?php

namespace App\Mail;

class OrderCancelledMail extends OrderMail
{
    protected function key(): string
    {
        return 'cancelled';
    }
}
