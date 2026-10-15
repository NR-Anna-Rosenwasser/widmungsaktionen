<?php

namespace App\Console\Commands;

use App\Services\OrderHousekeeping;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:send-abandoned-reminders')]
#[Description('Remind people once about orders they started but did not submit')]
class SendAbandonedReminders extends Command
{
    public function handle(OrderHousekeeping $housekeeping): void
    {
        $this->info('Sent '.$housekeeping->sendAbandonedReminders().' reminders.');
    }
}
