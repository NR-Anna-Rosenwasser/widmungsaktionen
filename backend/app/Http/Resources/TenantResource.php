<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'contact_email' => $this->contact_email,
            'intro' => $this->setting('branding.intro'),
            'legal' => $this->setting('legal'),
            'max_copies_per_order' => (int) $this->setting('max_copies_per_order'),
            'dedication_max_length' => (int) $this->setting('dedication_max_length'),
            'payment_methods' => collect($this->setting('payment_methods'))
                ->filter(fn ($method) => PaymentMethod::tryFrom($method))
                // Online payment needs Stripe keys.
                ->reject(fn ($method) => $method === PaymentMethod::Online->value && blank($this->stripe_publishable_key))
                ->values(),
            'shipping_tiers' => collect($this->setting('shipping_tiers'))
                ->map(fn ($tier) => ['max_copies' => (int) $tier['max_copies'], 'method' => $tier['method'], 'price' => (int) $tier['price']])
                ->sortBy('max_copies')
                ->values(),
            'newsletter' => filled($this->newsletter_webhook_url),
        ];
    }
}
