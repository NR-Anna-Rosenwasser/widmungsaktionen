<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderShipped;
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
}
