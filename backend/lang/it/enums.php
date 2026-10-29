<?php

return [
    'TenantRole' => [
        'admin' => 'Admin',
        'fulfillment' => 'Spedizione',
        'widmen' => 'Dediche',
    ],
    'AktionStatus' => [
        'draft' => 'Bozza',
        'scheduled' => 'Pianificata',
        'open' => 'Aperta',
        'closed' => 'Ordini chiusi',
        'signing' => 'Dediche',
        'shipping' => 'Spedizione',
        'done' => 'Conclusa',
    ],
    'OrderStatus' => [
        'draft' => 'Bozza',
        'pending_payment' => 'Pagamento in sospeso',
        'confirmed' => 'Confermato',
        'signed' => 'Dedicato',
        'shipped' => 'Spedito',
        'cancelled' => 'Annullato',
    ],
    'PaymentStatus' => [
        'unpaid' => 'Non pagato',
        'paid' => 'Pagato',
        'partially_refunded' => 'Rimborsato in parte',
        'refunded' => 'Rimborsato',
    ],
    'PaymentMethod' => [
        'online' => 'Online (carta, TWINT)',
        'invoice_advance' => 'Fattura per e-mail (pagamento anticipato)',
        'invoice_in_parcel' => 'Fattura nel pacco',
    ],
    'ModerationStatus' => [
        'pending' => 'In sospeso',
        'approved' => 'Approvata',
        'flagged' => 'Domanda aperta',
    ],
    'PaymentState' => [
        'pending' => 'In sospeso',
        'paid' => 'Pagato',
        'failed' => 'Non riuscito',
        'expired' => 'Scaduto',
        'cancelled' => 'Annullato',
    ],
];
