<?php

namespace App\Mail;

use App\Enums\PaymentMethod;

class PaymentReminderMail extends OrderMail
{
    protected function key(): string
    {
        return 'payment_reminder';
    }

    public function attachments(): array
    {
        return $this->order->payment_method === PaymentMethod::InvoiceAdvance ? [$this->invoiceAttachment()] : [];
    }
}
