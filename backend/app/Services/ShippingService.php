<?php

namespace App\Services;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Events\OrderShipped;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShippingService
{
    public function __construct(protected OrderPricer $pricer) {}

    /**
     * Letter or parcel, from the shop's shipping tiers.
     */
    public function methodFor(Order $order): string
    {
        return $this->pricer->shippingTier($order->tenant, $order->items()->count())['method'] ?? 'parcel';
    }

    public function ship(Order $order, CarbonInterface $date, User $by, ?string $trackingNumber = null, ?string $carrier = null, bool $notify = true): void
    {
        if ($order->status !== OrderStatus::Signed) {
            throw new RuntimeException("Bestellung {$order->number} ist noch nicht (vollständig) gewidmet.");
        }

        DB::transaction(function () use ($order, $date, $by, $trackingNumber, $carrier) {
            $order->shipments()->create([
                'method' => $this->methodFor($order),
                'shipped_at' => $date,
                'carrier' => $carrier ?: 'Schweizerische Post',
                'tracking_number' => $trackingNumber ?: null,
            ]);
            $order->update(['status' => OrderStatus::Shipped]);
            $order->log('shipped', array_filter(['tracking_number' => $trackingNumber]), public: true, user: $by);
        });

        if ($notify) {
            OrderShipped::dispatch($order);
        }
    }

    /**
     * Ship several orders at once (only signed ones), then update the Aktion.
     *
     * @param  iterable<Order>  $orders
     * @return int Number of orders shipped
     */
    public function shipMany(iterable $orders, CarbonInterface $date, User $by, bool $notify = true): int
    {
        $shipped = 0;
        $aktionen = [];

        foreach ($orders as $order) {
            if ($order->status !== OrderStatus::Signed) {
                continue;
            }
            $this->ship($order, $date, $by, notify: $notify);
            $aktionen[$order->aktion_id] = $order->aktion;
            $shipped++;
        }

        array_walk($aktionen, fn (Aktion $aktion) => $this->syncAktionStatus($aktion));

        return $shipped;
    }

    /**
     * «Versand» once something has shipped, «Abgeschlossen» once nothing is
     * open anymore (unpaid, to sign or to ship).
     */
    public function syncAktionStatus(Aktion $aktion): void
    {
        if (! in_array($aktion->status, [AktionStatus::Signing, AktionStatus::Shipping, AktionStatus::Closed], true)) {
            return;
        }

        $open = $aktion->orders()->whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Confirmed, OrderStatus::Signed])->exists();
        $shipped = $aktion->orders()->where('status', OrderStatus::Shipped)->exists();

        $status = match (true) {
            ! $open && $shipped => AktionStatus::Done,
            $shipped => AktionStatus::Shipping,
            default => $aktion->status,
        };

        if ($status !== $aktion->status) {
            $aktion->update(['status' => $status]);
        }
    }
}
