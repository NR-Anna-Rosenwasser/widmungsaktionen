<?php

namespace App\Models;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aktion extends Model
{
    protected $table = 'aktionen';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AktionStatus::class,
            'orders_open_at' => 'datetime',
            'order_deadline' => 'datetime',
            'signing_date' => 'date',
            'estimated_shipping_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)
            ->withPivot('price_override', 'sort')
            ->orderByPivot('sort');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function priceFor(Book $book): int
    {
        $pivot = $this->books->firstWhere('id', $book->id)?->pivot;

        return $pivot?->price_override ?? $book->price;
    }

    public function isAcceptingOrders(): bool
    {
        return $this->status === AktionStatus::Open
            && $this->orders_open_at->isPast()
            && $this->order_deadline->isFuture()
            && ! $this->isFull();
    }

    /**
     * Copies already ordered (submitted, not cancelled) in this Aktion.
     */
    public function orderedCopies(): int
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('aktion_id', $this->id)
                ->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]))
            ->count();
    }

    public function remainingCapacity(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->orderedCopies());
    }

    public function isFull(): bool
    {
        return $this->remainingCapacity() === 0;
    }
}
