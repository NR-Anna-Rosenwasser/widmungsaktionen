<?php

return [
    'admin_domain' => env('ADMIN_DOMAIN', 'admin.widmungen.ddev.site'),
    'storefront_url' => env('STOREFRONT_URL', 'https://widmungen.ddev.site'),
    'storefront_domain' => parse_url(env('STOREFRONT_URL', 'https://widmungen.ddev.site'), PHP_URL_HOST),
    'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),

    // Slugs that would collide with storefront routes.
    'reserved_slugs' => ['new', 'login', 'logout', 'password-reset', 'horizon', 'livewire', 'filament', 'storage', 'webhooks', 'api', 'admin', 'bestellen', 'bestellung', 'order', 'en', 'de', '_nuxt', 'fonts', 'datenschutz', 'impressum'],

    // A4 label sheets (mm). Dimensions from the manufacturers' specs; please
    // measure a sheet before the first big print run.
    'label_presets' => [
        'avery_3475' => ['name' => 'Avery Zweckform 3475 (70 × 36 mm, 24 pro Blatt)', 'cols' => 3, 'rows' => 8, 'width' => 70, 'height' => 36, 'top' => 4.5, 'left' => 0, 'gap_x' => 0, 'gap_y' => 0],
        'avery_3424' => ['name' => 'Avery Zweckform 3424 (105 × 48 mm, 12 pro Blatt)', 'cols' => 2, 'rows' => 6, 'width' => 105, 'height' => 48, 'top' => 4.5, 'left' => 0, 'gap_x' => 0, 'gap_y' => 0],
        'avery_3425' => ['name' => 'Avery Zweckform 3425 (105 × 57 mm, 10 pro Blatt)', 'cols' => 2, 'rows' => 5, 'width' => 105, 'height' => 57, 'top' => 6, 'left' => 0, 'gap_x' => 0, 'gap_y' => 0],
        'avery_l7163' => ['name' => 'Avery L7163 (99,1 × 38,1 mm, 14 pro Blatt)', 'cols' => 2, 'rows' => 7, 'width' => 99.1, 'height' => 38.1, 'top' => 15.1, 'left' => 4.7, 'gap_x' => 2.5, 'gap_y' => 0],
    ],

    // Defaults for Tenant::$settings; each tenant can override them.
    'tenant_defaults' => [
        'payment_methods' => ['online', 'invoice_advance', 'invoice_in_parcel'],
        'max_copies_per_order' => 4,
        'shipping_tiers' => [
            ['max_copies' => 1, 'method' => 'letter', 'price' => 300],
            ['max_copies' => 4, 'method' => 'parcel', 'price' => 800],
        ],
        'dedication_max_length' => 200,
        'dedication_retention_months' => 12,
        'invoice_payment_days' => 10,
        'reminder_days_before_deadline' => 3,
        // What happens to unpaid orders when an Aktion closes: move (once) or cancel.
        'unpaid_after_deadline' => 'move',
        'checkpoint_interval' => 5,
        'print' => [
            'label_preset' => 'avery_3475',
            'label_custom' => ['cols' => 3, 'rows' => 8, 'width' => 70, 'height' => 36, 'top' => 4.5, 'left' => 0, 'gap_x' => 0, 'gap_y' => 0],
            // Letters to customers carry a QR code linking to the order page.
            'letter_qr' => true,
            'envelope_mode' => 'labels',
        ],
        'legal' => [
            'terms_url' => null,
            'privacy_url' => null,
            'imprint_url' => null,
        ],
        'branding' => [
            // Legacy, superseded by storefront.texts.de.lead.
            'intro' => null,
        ],
        'storefront' => [
            // Brand colours: primary = text, buttons; accent = hero background;
            // accent_light = cards and highlights. Shades are derived from these.
            'colors' => [
                'primary' => '#2B3C69',
                'accent' => '#D989B2',
                'accent_light' => '#EEC6D8',
            ],
            'logo_path' => null,
            'favicon_path' => null,
            'hero' => [
                // image | heart | none
                'visual' => 'none',
                'image_path' => null,
                'image_alt' => null,
                'heart_text' => null,
            ],
            'sections' => [
                'books' => true,
                'how' => true,
                'lookup' => true,
            ],
            'seo' => [
                'title' => null,
                'description' => null,
                'image_path' => null,
            ],
            // Overrides per locale; empty values fall back to lang/*/storefront.php.
            'texts' => [
                'de' => [],
                'en' => [],
            ],
        ],
    ],
];
