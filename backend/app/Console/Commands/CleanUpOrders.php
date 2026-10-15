<?php

namespace App\Console\Commands;

use App\Services\OrderHousekeeping;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:clean-up')]
#[Description('Delete old drafts and anonymise dedications after the retention period')]
class CleanUpOrders extends Command
{
    public function handle(OrderHousekeeping $housekeeping): void
    {
        $this->info('Deleted '.$housekeeping->deleteOldDrafts().' old drafts.');
        $this->info('Anonymised '.$housekeeping->anonymiseDedications().' dedications.');
    }
}
