<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Services\Payments\PaymentService;
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
            'payment_methods' => app(PaymentService::class)->availableMethods($this->resource),
            'shipping_tiers' => collect($this->setting('shipping_tiers'))
                ->map(fn ($tier) => ['max_copies' => (int) $tier['max_copies'], 'method' => $tier['method'], 'price' => (int) $tier['price']])
                ->sortBy('max_copies')
                ->values(),
            'newsletter' => filled($this->newsletter_webhook_url),
        ];
    }
}
