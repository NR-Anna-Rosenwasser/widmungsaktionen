<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\AbandonedOrderMail;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

/**
 * Abandoned drafts and data minimisation.
 */
class OrderHousekeeping
{
    public const REMIND_AFTER_HOURS = 24;

    public const DELETE_DRAFTS_AFTER_DAYS = 30;

    /**
     * One reminder per unfinished order, 24 hours after it was last touched.
     */
    public function sendAbandonedReminders(): int
    {
        $sent = 0;

        Order::query()
            ->with('tenant', 'aktion')
            ->where('status', OrderStatus::Draft)
            ->whereNull('abandoned_reminder_sent_at')
            ->where('reminders_opt_out', false)
            ->where('updated_at', '<=', now()->subHours(self::REMIND_AFTER_HOURS))
            ->where('updated_at', '>', now()->subDays(7))
            ->each(function (Order $draft) use (&$sent) {
                $sent += (int) $this->remindAbandoned($draft);
            });

        return $sent;
    }

    /**
     * Testing mode: remind every unfinished order of the Aktion now, without waiting 24 hours.
     */
    public function sendAbandonedRemindersNow(Aktion $aktion): int
    {
        $sent = 0;

        $aktion->orders()
            ->with('tenant', 'aktion')
            ->where('status', OrderStatus::Draft)
            ->whereNull('abandoned_reminder_sent_at')
            ->where('reminders_opt_out', false)
            ->each(function (Order $draft) use (&$sent) {
                $sent += (int) $this->remindAbandoned($draft);
            });

        return $sent;
    }

    protected function remindAbandoned(Order $draft): bool
    {
        // Mark first, so a failing mail can never lead to a second reminder.
        $draft->forceFill(['abandoned_reminder_sent_at' => now()])->saveQuietly();

        if (! $draft->aktion->isAcceptingOrders() || $this->orderedMeanwhile($draft)) {
            return false;
        }

        Mail::to($draft->email)->queue(new AbandonedOrderMail($draft));
        $draft->log('abandoned_reminder_sent');

        return true;
    }

    /**
     * Did the same person submit an order in this Aktion after all?
     */
    protected function orderedMeanwhile(Order $draft): bool
    {
        return Order::query()
            ->where('aktion_id', $draft->aktion_id)
            ->whereKeyNot($draft->id)
            ->whereNotNull('submitted_at')
            ->whereRaw('lower(email) = ?', [strtolower($draft->email)])
            ->exists();
    }

    public function deleteOldDrafts(): int
    {
        return Order::query()
            ->where('status', OrderStatus::Draft)
            ->where('updated_at', '<', now()->subDays(self::DELETE_DRAFTS_AFTER_DAYS))
            ->delete();
    }

    /**
     * Remove names and wishes from shipped orders after the shop's retention period.
     */
    public function anonymiseDedications(): int
    {
        $count = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$count) {
            $months = (int) $tenant->setting('dedication_retention_months');
            if ($months <= 0) {
                return;
            }

            $count += OrderItem::query()
                ->whereNull('dedication_anonymised_at')
                ->withDedication()
                ->whereHas('order', fn ($q) => $q
                    ->whereBelongsTo($tenant)
                    ->where('status', OrderStatus::Shipped)
                    ->whereHas('shipments', fn ($s) => $s->where('shipped_at', '<', now()->subMonths($months))))
                ->update([
                    'recipient_name' => null,
                    'wish' => null,
                    'dedication_anonymised_at' => now(),
                ]);
        });

        return $count;
    }
}
