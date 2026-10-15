<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRefunded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $amount  Refunded amount in Rappen
     */
    public function __construct(public Order $order, public int $amount) {}
}
