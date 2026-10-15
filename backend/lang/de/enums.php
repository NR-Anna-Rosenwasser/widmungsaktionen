<?php

return [
    'TenantRole' => [
        'admin' => 'Admin',
        'fulfillment' => 'Versand',
        'widmen' => 'Widmen',
    ],
    'AktionStatus' => [
        'draft' => 'Entwurf',
        'scheduled' => 'Geplant',
        'open' => 'Offen',
        'closed' => 'Bestellschluss',
        'signing' => 'Widmen',
        'shipping' => 'Versand',
        'done' => 'Abgeschlossen',
    ],
    'OrderStatus' => [
        'draft' => 'Entwurf',
        'pending_payment' => 'Zahlung offen',
        'confirmed' => 'Bestätigt',
        'signed' => 'Gewidmet',
        'shipped' => 'Versendet',
        'cancelled' => 'Storniert',
    ],
    'PaymentStatus' => [
        'unpaid' => 'Unbezahlt',
        'paid' => 'Bezahlt',
        'partially_refunded' => 'Teilweise erstattet',
        'refunded' => 'Erstattet',
    ],
    'PaymentMethod' => [
        'online' => 'Online (Karte, TWINT)',
        'invoice_advance' => 'Rechnung per E-Mail (Vorauskasse)',
        'invoice_in_parcel' => 'Rechnung im Paket',
    ],
    'ModerationStatus' => [
        'pending' => 'Offen',
        'approved' => 'Freigegeben',
        'flagged' => 'Rückfrage',
    ],
];
