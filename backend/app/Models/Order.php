<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'is_gift' => 'boolean',
            'newsletter_opt_in' => 'boolean',
            'reminders_opt_out' => 'boolean',
            'abandoned_reminder_sent_at' => 'datetime',
            'payment_reminder_sent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->token ??= Str::random(48);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function aktion(): BelongsTo
    {
        return $this->belongsTo(Aktion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('created_at');
    }

    public function billingName(): string
    {
        return trim("{$this->billing_first_name} {$this->billing_last_name}");
    }

    public function shippingName(): string
    {
        return $this->is_gift
            ? trim("{$this->shipping_first_name} {$this->shipping_last_name}")
            : $this->billingName();
    }

    public function log(string $type, array $data = [], bool $public = false, ?User $user = null): OrderEvent
    {
        return $this->events()->create([
            'type' => $type,
            'data' => $data,
            'is_public' => $public,
            'user_id' => $user?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Personal tracking link, on the shop's own domain if it has one.
     */
    public function storefrontUrl(): string
    {
        return $this->tenant->storefrontUrl("/bestellung/{$this->token}");
    }
}
