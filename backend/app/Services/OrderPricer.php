<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;

class OrderPricer
{
    /**
     * Shipping tier for a number of copies, or null if no tier covers it.
     *
     * @return array{max_copies: int, method: string, price: int}|null
     */
    public function shippingTier(Tenant $tenant, int $copies): ?array
    {
        return collect($tenant->setting('shipping_tiers'))
            ->sortBy('max_copies')
            ->first(fn (array $tier) => $copies <= (int) $tier['max_copies']);
    }

    /**
     * Recalculate unit prices and totals from the order's Aktion.
     */
    public function price(Order $order): Order
    {
        $order->loadMissing('aktion.books', 'items', 'tenant');

        $subtotal = 0;
        foreach ($order->items as $item) {
            $book = $order->aktion->books->firstWhere('id', $item->book_id);
            $item->unit_price = $book ? ($book->pivot->price_override ?? $book->price) : $item->unit_price;
            $item->save();
            $subtotal += $item->unit_price;
        }

        $tier = $this->shippingTier($order->tenant, $order->items->count());

        $order->subtotal = $subtotal;
        $order->shipping_cost = $order->items->isEmpty() ? 0 : (int) ($tier['price'] ?? 0);
        $order->total = $order->subtotal + $order->shipping_cost;

        return $order;
    }
}
