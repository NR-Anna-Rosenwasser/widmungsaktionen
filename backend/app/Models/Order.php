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

    /**
     * Where the parcel goes: the gift address or the billing address.
     *
     * @return array<int, string>
     */
    public function shippingAddressLines(): array
    {
        $prefix = $this->is_gift ? 'shipping' : 'billing';

        return array_values(array_filter([
            $this->{"{$prefix}_company"},
            trim($this->{"{$prefix}_first_name"}.' '.$this->{"{$prefix}_last_name"}),
            $this->{"{$prefix}_street"},
            trim($this->{"{$prefix}_postal_code"}.' '.$this->{"{$prefix}_town"}),
            ($this->{"{$prefix}_country"} ?? 'CH') === 'LI' ? 'Liechtenstein' : null,
        ]));
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
     * Placed in an Aktion in testing mode: may be deleted, never moved into a real Aktion.
     */
    public function isTest(): bool
    {
        return (bool) $this->aktion?->is_test;
    }

    /**
     * Signed, the shop sends the "your book is signed" mail, and it has not
     * gone out since. The mail is sent with a delay (undo window); see SendSignedMail.
     */
    public function signedMailPending(): bool
    {
        if ($this->status !== OrderStatus::Signed || ! $this->tenant->setting('notify_signed')) {
            return false;
        }

        $signedAt = $this->events()->where('type', 'signed')->max('created_at');

        return ! $this->events()
            ->where('type', 'mail_sent')
            ->where('data->mail', 'OrderSignedMail')
            ->when($signedAt, fn ($q) => $q->where('created_at', '>=', $signedAt))
            ->exists();
    }

    /**
     * Link to continue a draft (abandoned-order mail). Test orders go through
     * the preview link, so it works in any browser.
     */
    public function resumeUrl(): string
    {
        $path = '/bestellen?t='.$this->token;

        return $this->isTest() ? $this->aktion->previewUrl($path) : $this->tenant->storefrontUrl($path);
    }

    /**
     * Personal tracking link, on the shop's own domain if it has one.
     */
    public function storefrontUrl(): string
    {
        return $this->tenant->storefrontUrl("/bestellung/{$this->token}");
    }
}
