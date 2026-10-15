<?php

namespace App\Console\Commands;

use App\Services\UnpaidOrders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:send-payment-reminders')]
#[Description('Remind customers of unpaid orders shortly before the order deadline')]
class SendPaymentReminders extends Command
{
    public function handle(UnpaidOrders $unpaid): void
    {
        $this->info('Sent '.$unpaid->sendReminders().' reminders.');
    }
}
