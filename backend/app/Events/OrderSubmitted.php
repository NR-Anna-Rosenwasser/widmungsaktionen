<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderSubmitted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $confirmNow  Send the confirmation right away, also for online payment.
     *                            Storefront orders paying online are confirmed once Stripe
     *                            reports the payment; orders entered in the admin never go there.
     */
    public function __construct(public Order $order, public bool $confirmNow = false) {}
}
