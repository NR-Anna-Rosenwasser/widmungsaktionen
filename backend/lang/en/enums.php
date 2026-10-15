<?php

return [
    'TenantRole' => [
        'admin' => 'Admin',
        'fulfillment' => 'Fulfillment',
        'widmen' => 'Signing',
    ],
    'AktionStatus' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'open' => 'Open',
        'closed' => 'Closed',
        'signing' => 'Signing',
        'shipping' => 'Shipping',
        'done' => 'Done',
    ],
    'OrderStatus' => [
        'draft' => 'Draft',
        'pending_payment' => 'Awaiting payment',
        'confirmed' => 'Confirmed',
        'signed' => 'Signed',
        'shipped' => 'Shipped',
        'cancelled' => 'Cancelled',
    ],
    'PaymentStatus' => [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'partially_refunded' => 'Partially refunded',
        'refunded' => 'Refunded',
    ],
    'PaymentMethod' => [
        'online' => 'Online (card, TWINT)',
        'invoice_advance' => 'Invoice by email (pay in advance)',
        'invoice_in_parcel' => 'Invoice in parcel',
    ],
    'ModerationStatus' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'flagged' => 'Flagged',
    ],
];
