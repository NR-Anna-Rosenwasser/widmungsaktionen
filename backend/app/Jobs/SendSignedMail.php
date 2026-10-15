<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Mail\OrderSignedMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sent with a delay, so an "undo" right after signing doesn't leave a wrong
 * mail behind. Skipped if the order was unsigned or has shipped meanwhile.
 */
class SendSignedMail implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $this->order->refresh();

        if ($this->order->status !== OrderStatus::Signed) {
            return;
        }

        Mail::to($this->order->email)->send(new OrderSignedMail($this->order));
        $this->order->log('mail_sent', ['mail' => 'OrderSignedMail']);
    }
}
