<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The order as its owner sees it (draft form state and tracking page).
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $address = fn (string $prefix) => [
            'first_name' => $this->{"{$prefix}_first_name"},
            'last_name' => $this->{"{$prefix}_last_name"},
            'company' => $this->{"{$prefix}_company"},
            'street' => $this->{"{$prefix}_street"},
            'postal_code' => $this->{"{$prefix}_postal_code"},
            'town' => $this->{"{$prefix}_town"},
            'country' => $this->{"{$prefix}_country"},
        ];

        return [
            'token' => $this->token,
            'number' => $this->number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'current_step' => $this->current_step,
            'email' => $this->email,
            'locale' => $this->locale,
            'billing' => $address('billing'),
            'is_gift' => $this->is_gift,
            'shipping' => $this->is_gift ? $address('shipping') : null,
            'gift_message' => $this->gift_message,
            'newsletter_opt_in' => $this->newsletter_opt_in,
            'copies' => $this->items->map(fn ($item) => [
                'book_id' => $item->book_id,
                'title' => $item->book->title,
                'unit_price' => $item->unit_price,
                'signature_only' => $item->signature_only,
                'recipient_name' => $item->recipient_name,
                'wish' => $item->wish,
                'signed' => $item->signed_at !== null,
            ]),
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'total' => $this->total,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'can_pay_online' => app(PaymentService::class)->canPayOnline($this->resource),
            'has_invoice' => $this->number !== null && in_array($this->payment_method?->value, ['invoice_advance', 'invoice_in_parcel'], true),
            'aktion' => [
                'name' => $this->aktion->name,
                'order_deadline' => $this->aktion->order_deadline->toIso8601String(),
                'signing_date' => $this->aktion->signing_date->toDateString(),
                'estimated_shipping_date' => $this->aktion->estimated_shipping_date->toDateString(),
            ],
            'timeline' => $this->timeline(),
            'shipments' => $this->shipments->map(fn ($shipment) => [
                'shipped_at' => $shipment->shipped_at->toDateString(),
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
            ]),
        ];
    }

    /**
     * The four milestones shown on the tracking page.
     */
    protected function timeline(): array
    {
        $at = fn (string $type) => $this->events->firstWhere('type', $type)?->created_at?->toIso8601String();
        $cancelled = $this->status === OrderStatus::Cancelled;

        return [
            ['key' => 'submitted', 'done' => $this->submitted_at !== null, 'at' => $this->submitted_at?->toIso8601String()],
            ['key' => 'paid', 'done' => $this->payment_status->value !== 'unpaid', 'at' => $at('payment_received')],
            ['key' => 'signed', 'done' => in_array($this->status, [OrderStatus::Signed, OrderStatus::Shipped], true), 'at' => $at('signed')],
            ['key' => 'shipped', 'done' => $this->status === OrderStatus::Shipped, 'at' => $this->shipments->first()?->shipped_at?->toDateString()],
            ...($cancelled ? [['key' => 'cancelled', 'done' => true, 'at' => $this->cancelled_at?->toIso8601String()]] : []),
        ];
    }
}
