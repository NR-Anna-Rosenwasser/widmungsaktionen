<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signature_only' => 'boolean',
            'moderation_status' => ModerationStatus::class,
            'signed_at' => 'datetime',
            'flagged_at' => 'datetime',
            'dedication_anonymised_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Copies the customer wrote something for (needs moderation).
     */
    public function scopeWithDedication(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNotNull('recipient_name')->orWhereNotNull('wish'));
    }

    /**
     * Copies of submitted, active orders of a tenant.
     */
    public function scopeActiveForTenant(Builder $query, Tenant $tenant): void
    {
        $query->whereHas('order', fn ($q) => $q
            ->whereBelongsTo($tenant)
            ->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]));
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
