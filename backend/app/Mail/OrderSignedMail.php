<?php

namespace App\Mail;

class OrderSignedMail extends OrderMail
{
    protected function key(): string
    {
        return 'signed';
    }
}
