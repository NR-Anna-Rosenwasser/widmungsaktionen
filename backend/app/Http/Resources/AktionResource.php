<?php

namespace App\Http\Resources;

use App\Models\Aktion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Aktion
 */
class AktionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'orders_open_at' => $this->orders_open_at->toIso8601String(),
            'order_deadline' => $this->order_deadline->toIso8601String(),
            'signing_date' => $this->signing_date->toDateString(),
            'estimated_shipping_date' => $this->estimated_shipping_date->toDateString(),
            'accepting_orders' => $this->isAcceptingOrders(),
            'remaining_capacity' => $this->remainingCapacity(),
            'books' => $this->books
                ->where('is_active', true)
                ->values()
                ->map(fn ($book) => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'subtitle' => $book->subtitle,
                    'author_name' => $book->author_name,
                    'description' => $book->description,
                    'cover_url' => $book->coverUrl(),
                    'price' => $book->pivot->price_override ?? $book->price,
                ]),
        ];
    }
}
