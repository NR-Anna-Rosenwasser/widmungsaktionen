<?php

namespace App\Console\Commands;

use App\Enums\AktionStatus;
use App\Models\Aktion;
use App\Services\UnpaidOrders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('aktionen:sync-status')]
#[Description('Close Aktionen past their deadline and open scheduled ones')]
class SyncAktionStatus extends Command
{
    public function handle(UnpaidOrders $unpaid): void
    {
        $closed = 0;
        Aktion::query()
            ->where('status', AktionStatus::Open)
            ->where('order_deadline', '<=', now())
            ->each(function (Aktion $aktion) use ($unpaid, &$closed) {
                $aktion->update(['status' => AktionStatus::Closed]);
                $unpaid->handleClosedAktion($aktion);
                $closed++;
            });

        $opened = 0;
        Aktion::query()
            ->where('status', AktionStatus::Scheduled)
            ->where('orders_open_at', '<=', now())
            ->where('order_deadline', '>', now())
            ->orderBy('orders_open_at')
            ->each(function (Aktion $aktion) use (&$opened) {
                DB::transaction(function () use ($aktion, &$opened) {
                    // One open Aktion per shop; test Aktionen don't count and open freely.
                    $hasOpen = ! $aktion->is_test && Aktion::query()
                        ->where('tenant_id', $aktion->tenant_id)
                        ->where('is_test', false)
                        ->where('status', AktionStatus::Open)
                        ->lockForUpdate()
                        ->exists();

                    if (! $hasOpen) {
                        $aktion->update(['status' => AktionStatus::Open]);
                        $opened++;
                    }
                });
            });

        $this->info("Closed {$closed}, opened {$opened}.");
    }
}
