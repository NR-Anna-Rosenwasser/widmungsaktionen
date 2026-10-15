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

    protected function data(): array
    {
        return ['amount' => $this->amount];
    }
}
