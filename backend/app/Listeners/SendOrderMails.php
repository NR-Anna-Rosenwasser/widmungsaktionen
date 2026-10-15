<?php

namespace App\Listeners;

use App\Enums\PaymentMethod;
use App\Events\OrderCancelled;
use App\Events\OrderMoved;
use App\Events\OrderPaid;
use App\Events\OrderRefunded;
use App\Events\OrderShipped;
use App\Events\OrderSigned;
use App\Jobs\SendSignedMail;
use App\Events\OrderSubmitted;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderMail;
use App\Mail\OrderMovedMail;
use App\Mail\OrderRefundedMail;
use App\Mail\OrderShippedMail;
use App\Mail\PaymentReceivedMail;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;

/**
 * Which email a customer gets for which order event.
 */
class SendOrderMails
{
    public function submitted(OrderSubmitted $event): void
    {
        // Online orders are confirmed once the payment went through (one mail instead of two).
        if ($event->order->payment_method !== PaymentMethod::Online) {
            $this->send(new OrderConfirmationMail($event->order));
        }
    }

    public function paid(OrderPaid $event): void
    {
        $order = $event->order;

        $this->send($order->payment_method === PaymentMethod::Online
            ? new OrderConfirmationMail($order)
            : new PaymentReceivedMail($order));
    }

    public function moved(OrderMoved $event): void
    {
        $this->send(new OrderMovedMail($event->order));
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->send(new OrderCancelledMail($event->order));
    }

    public function refunded(OrderRefunded $event): void
    {
        $this->send(new OrderRefundedMail($event->order, $event->amount));
    }

    public function signed(OrderSigned $event): void
    {
        SendSignedMail::dispatch($event->order)->delay(now()->addMinutes(30));
    }

    public function shipped(OrderShipped $event): void
    {
        $this->send(new OrderShippedMail($event->order));
    }

    protected function send(OrderMail $mail): void
    {
        Mail::to($mail->order->email)->queue($mail);
        $mail->order->log('mail_sent', ['mail' => class_basename($mail)]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderSubmitted::class => 'submitted',
            OrderPaid::class => 'paid',
            OrderMoved::class => 'moved',
            OrderCancelled::class => 'cancelled',
            OrderRefunded::class => 'refunded',
            OrderSigned::class => 'signed',
            OrderShipped::class => 'shipped',
        ];
    }
}
