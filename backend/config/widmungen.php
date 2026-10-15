<?php

return [
    'admin_domain' => env('ADMIN_DOMAIN', 'admin.widmungen.ddev.site'),
    'storefront_url' => env('STOREFRONT_URL', 'https://widmungen.ddev.site'),
    'storefront_domain' => parse_url(env('STOREFRONT_URL', 'https://widmungen.ddev.site'), PHP_URL_HOST),
    'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),

    // Slugs that would collide with storefront routes.
    'reserved_slugs' => ['new', 'login', 'logout', 'password-reset', 'horizon', 'livewire', 'filament', 'storage', 'webhooks', 'api', 'admin', 'bestellen', 'bestellung', 'order', 'en', 'de', '_nuxt', 'fonts', 'datenschutz', 'impressum'],

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
            'label_custom' => null,
            'envelope_mode' => 'labels',
        ],
        'legal' => [
            'terms_url' => null,
            'privacy_url' => null,
            'imprint_url' => null,
        ],
        'branding' => [
            'intro' => null,
        ],
    ],
];
