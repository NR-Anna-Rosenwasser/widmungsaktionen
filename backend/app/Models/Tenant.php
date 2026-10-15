<?php

namespace App\Models;

use App\Enums\AktionStatus;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Tenant extends Model implements HasName
{
    protected $guarded = ['id', 'order_sequence'];

    protected $hidden = ['stripe_secret_key', 'stripe_webhook_secret', 'newsletter_webhook_secret'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'stripe_secret_key' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
            'newsletter_webhook_secret' => 'encrypted',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function aktionen(): HasMany
    {
        return $this->hasMany(Aktion::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * A tenant setting, falling back to config('widmungen.tenant_defaults').
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get(
            $this->settings ?? [],
            $key,
            Arr::get(config('widmungen.tenant_defaults'), $key, $default),
        );
    }

    /**
     * The Aktion currently taking orders, or null.
     */
    public function openAktion(): ?Aktion
    {
        return $this->aktionen()
            ->where('status', AktionStatus::Open)
            ->where('orders_open_at', '<=', now())
            ->where('order_deadline', '>', now())
            ->first();
    }

    /**
     * The next Aktion that will open, shown when nothing is open.
     */
    public function nextAktion(): ?Aktion
    {
        return $this->aktionen()
            ->whereIn('status', [AktionStatus::Scheduled, AktionStatus::Open])
            ->where('orders_open_at', '>', now())
            ->orderBy('orders_open_at')
            ->first();
    }

    /**
     * The Aktion currently being worked on: signing beats shipping beats
     * waiting for signing day beats taking orders.
     */
    public function currentAktion(): ?Aktion
    {
        $priority = [AktionStatus::Signing, AktionStatus::Shipping, AktionStatus::Closed, AktionStatus::Open, AktionStatus::Scheduled, AktionStatus::Done];

        return $this->aktionen()
            ->whereNot('status', AktionStatus::Draft)
            ->orderByDesc('signing_date')
            ->get()
            ->sortBy(fn (Aktion $aktion) => array_search($aktion->status, $priority, true))
            ->first();
    }

    /**
     * Absolute storefront URL: the first custom domain, else the platform domain with slug.
     */
    public function storefrontUrl(string $path = ''): string
    {
        $domain = $this->domains()->orderBy('id')->value('domain');
        $scheme = parse_url(config('widmungen.storefront_url'), PHP_URL_SCHEME) ?: 'https';

        $base = $domain
            ? "$scheme://$domain"
            : rtrim(config('widmungen.storefront_url'), '/')."/{$this->slug}";

        return $base.$path;
    }

    public function nextOrderNumber(): string
    {
        return DB::transaction(function () {
            $tenant = static::query()->lockForUpdate()->findOrFail($this->id);
            $tenant->increment('order_sequence');

            return sprintf('%s-%s-%04d', $tenant->order_prefix, now()->year, $tenant->order_sequence);
        });
    }
}
