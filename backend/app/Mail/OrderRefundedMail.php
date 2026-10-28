<?php

namespace App\Mail;

use App\Models\Order;

class OrderRefundedMail extends OrderMail
{
    public function __construct(Order $order, public int $amount)
    {
        parent::__construct($order);
    }

    protected function key(): string
    {
        return 'refunded';
    }

    // The refund mail's own texts are not editable (lang/*/mail.php).
    protected function subjectLine(): string
    {
        return __('mail.refunded.subject', ['number' => $this->order->number], $this->locale);
    }

    protected function data(): array
    {
        return ['amount' => $this->amount];
    }
}
