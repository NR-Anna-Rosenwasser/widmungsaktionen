<?php

namespace App\Services;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Events\OrderMoved;
use App\Mail\PaymentReminderMail;
use App\Models\Aktion;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Reminders before the order deadline and what happens to orders that are
 * still unpaid when their Aktion closes.
 */
class UnpaidOrders
{
    public function __construct(protected PaymentService $payments) {}

    /**
     * Send one reminder per order when the deadline is near. Returns the count.
     */
    public function sendReminders(): int
    {
        $sent = 0;

        Order::query()
            ->with('tenant', 'aktion')
            ->where('status', OrderStatus::PendingPayment)
            ->whereNull('payment_reminder_sent_at')
            ->whereHas('aktion', fn ($q) => $q->where('order_deadline', '>', now()))
            ->each(function (Order $order) use (&$sent) {
                $days = (int) $order->tenant->setting('reminder_days_before_deadline');
                if ($order->aktion->order_deadline->gt(now()->addDays($days))) {
                    return;
                }
                // Orders placed in the last day don't need a reminder yet.
                if ($order->submitted_at?->gt(now()->subDay())) {
                    return;
                }

                Mail::to($order->email)->queue(new PaymentReminderMail($order));
                $order->update(['payment_reminder_sent_at' => now()]);
                $order->log('reminder_sent', public: false);
                $sent++;
            });

        return $sent;
    }

    /**
     * After an Aktion closed: move unpaid orders on once, otherwise cancel them.
     */
    public function handleClosedAktion(Aktion $aktion): void
    {
        $next = $aktion->tenant->aktionen()
            ->whereKeyNot($aktion->id)
            ->whereIn('status', [AktionStatus::Scheduled, AktionStatus::Open])
            ->where('order_deadline', '>', now())
            ->orderBy('order_deadline')
            ->first();
        $policy = $aktion->tenant->setting('unpaid_after_deadline');

        $aktion->orders()
            ->where('status', OrderStatus::PendingPayment)
            ->each(function (Order $order) use ($next, $policy, $aktion) {
                if ($policy === 'move' && $next && $order->moved_count === 0) {
                    $order->update([
                        'aktion_id' => $next->id,
                        'moved_count' => $order->moved_count + 1,
                        'payment_reminder_sent_at' => null,
                    ]);
                    $order->log('moved', ['from' => $aktion->name, 'to' => $next->name, 'reason' => 'unpaid'], public: true);
                    OrderMoved::dispatch($order->refresh());

                    return;
                }

                $this->payments->cancel($order, null);
            });
    }
}
