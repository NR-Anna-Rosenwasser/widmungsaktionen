<?php

namespace App\Mail;

class OrderShippedMail extends OrderMail
{
    protected function key(): string
    {
        return 'shipped';
    }
}
