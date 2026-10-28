<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Services\Payments\PaymentService;
use App\Services\StorefrontAppearance;
use App\Support\Texts\Texts;
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
            'legal' => $this->setting('legal'),
            'max_copies_per_order' => (int) $this->setting('max_copies_per_order'),
            'dedication_max_length' => (int) $this->setting('dedication_max_length'),
            'dedication_options' => [
                'signature_only' => (bool) $this->setting('dedication_signature_only'),
                'wish' => (bool) $this->setting('dedication_wish'),
            ],
            'payment_methods' => app(PaymentService::class)->availableMethods($this->resource, test: (bool) $request->attributes->get('preview_aktion')),
            'shipping_tiers' => collect($this->setting('shipping_tiers'))
                ->map(fn ($tier) => ['max_copies' => (int) $tier['max_copies'], 'method' => $tier['method'], 'price' => (int) $tier['price']])
                ->sortBy('max_copies')
                ->values(),
            'newsletter' => filled($this->newsletter_webhook_url),
            'storefront' => app(StorefrontAppearance::class)->forStorefront($this->resource),
            'texts' => app(Texts::class)->storefront($this->resource),
        ];
    }
}
