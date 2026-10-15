<?php

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class OrderConfirmationMail extends OrderMail
{
    protected function key(): string
    {
        return 'confirmation';
    }

    public function attachments(): array
    {
        $unpaidInvoice = $this->order->payment_method === PaymentMethod::InvoiceAdvance
            && $this->order->payment_status === PaymentStatus::Unpaid;

        return $unpaidInvoice ? [$this->invoiceAttachment()] : [];
    }
}
