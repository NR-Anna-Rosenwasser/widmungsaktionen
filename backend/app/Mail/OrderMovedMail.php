<?php

namespace App\Mail;

class OrderMovedMail extends OrderMail
{
    protected function key(): string
    {
        return 'moved';
    }
}
