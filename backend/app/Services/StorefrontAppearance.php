<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * Look of a shop's storefront: tenant settings merged over the defaults
 * (config/widmungen.php). Texts: App\Support\Texts\Texts.
 */
class StorefrontAppearance
{
    /**
     * @return array{primary: string, accent: string, accent_light: string}
     */
    public function colors(Tenant $tenant): array
    {
        $defaults = config('widmungen.tenant_defaults.storefront.colors');

        return collect($defaults)
            ->map(fn (string $default, string $key) => self::normaliseHex($tenant->setting("storefront.colors.$key")) ?? $default)
            ->all();
    }

    /**
     * Everything the storefront needs to render the shop's look.
     */
    public function forStorefront(Tenant $tenant): array
    {
        $url = fn (?string $path) => $path ? Storage::disk('public')->url($path) : null;
        $visual = $tenant->setting('storefront.hero.visual');
        $heroImage = $url($tenant->setting('storefront.hero.image_path'));

        // Link preview image: explicit one, else the hero image, else the first book cover.
        $seoImage = $url($tenant->setting('storefront.seo.image_path'))
            ?? $heroImage
            ?? $tenant->books()->where('is_active', true)->whereNotNull('cover_path')->orderBy('sort')->first()?->coverUrl();

        return [
            'colors' => $this->colors($tenant),
            'logo_url' => $url($tenant->setting('storefront.logo_path')),
            'favicon_url' => $url($tenant->setting('storefront.favicon_path')),
            'hero' => [
                'visual' => $visual === 'image' && ! $heroImage ? 'none' : $visual,
                'image_url' => $heroImage,
                'image_alt' => $tenant->setting('storefront.hero.image_alt') ?: '',
                'heart_text' => $tenant->setting('storefront.hero.heart_text') ?: $tenant->name,
            ],
            'sections' => collect(config('widmungen.tenant_defaults.storefront.sections'))
                ->map(fn ($default, string $key) => (bool) $tenant->setting("storefront.sections.$key", $default))
                ->all(),
            'seo' => [
                'image_url' => $seoImage,
            ],
        ];
    }

    public static function normaliseHex(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^#?([0-9a-f]{6})$/i', $value, $m) ? '#'.strtoupper($m[1]) : null;
    }

    /**
     * WCAG contrast ratio between two hex colours (1–21).
     */
    public static function contrast(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
            $channel = fn (int $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
        };

        [$light, $dark] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

        return round(($light + 0.05) / ($dark + 0.05), 1);
    }
}
