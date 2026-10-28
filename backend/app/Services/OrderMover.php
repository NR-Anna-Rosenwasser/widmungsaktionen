<?php

namespace App\Services;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Events\OrderMoved;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Move an order that hasn't been signed into another Aktion (by hand or
 * automatically after the deadline). It loses its post-it number.
 */
class OrderMover
{
    public function __construct(protected ShippingService $shipping) {}

    /**
     * Aktionen orders of the given one can move to: same shop, same kind
     * (test or real), still taking or about to take orders.
     *
     * @return Collection<int, Aktion>
     */
    public function targets(Aktion $from): Collection
    {
        return Aktion::query()
            ->where('tenant_id', $from->tenant_id)
            ->whereKeyNot($from->id)
            ->where('is_test', $from->is_test)
            ->whereIn('status', [AktionStatus::Draft, AktionStatus::Scheduled, AktionStatus::Open])
            ->orderBy('order_deadline')
            ->get();
    }

    public function canMove(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Confirmed], true)
            && $order->items()->whereNotNull('signed_at')->doesntExist();
    }

    public function move(Order $order, Aktion $to, ?User $by, bool $notify = true, ?string $reason = null): void
    {
        if (! $this->canMove($order) || $to->tenant_id !== $order->tenant_id || $to->is_test !== $order->isTest()) {
            throw new RuntimeException("Bestellung {$order->number} kann nicht verschoben werden.");
        }

        $from = $order->aktion;
        $order->update([
            'aktion_id' => $to->id,
            'stack_number' => null,
            'moved_count' => $order->moved_count + 1,
            'payment_reminder_sent_at' => null,
        ]);
        $order->log('moved', array_filter(['from' => $from->name, 'to' => $to->name, 'reason' => $reason]), public: true, user: $by);

        if ($notify) {
            OrderMoved::dispatch($order->refresh());
        }

        $this->shipping->syncAktionStatus($from);
    }
}
