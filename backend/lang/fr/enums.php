<?php

return [
    'TenantRole' => [
        'admin' => 'Admin',
        'fulfillment' => 'Expédition',
        'widmen' => 'Dédicacer',
    ],
    'AktionStatus' => [
        'draft' => 'Brouillon',
        'scheduled' => 'Prévue',
        'open' => 'Ouverte',
        'closed' => 'Fin des commandes',
        'signing' => 'Dédicaces',
        'shipping' => 'Envoi',
        'done' => 'Terminée',
    ],
    'OrderStatus' => [
        'draft' => 'Brouillon',
        'pending_payment' => 'Paiement en attente',
        'confirmed' => 'Confirmée',
        'signed' => 'Dédicacée',
        'shipped' => 'Envoyée',
        'cancelled' => 'Annulée',
    ],
    'PaymentStatus' => [
        'unpaid' => 'Non payée',
        'paid' => 'Payée',
        'partially_refunded' => 'Partiellement remboursée',
        'refunded' => 'Remboursée',
    ],
    'PaymentMethod' => [
        'online' => 'En ligne (carte, TWINT)',
        'invoice_advance' => 'Facture par e-mail (paiement anticipé)',
        'invoice_in_parcel' => 'Facture dans le colis',
    ],
    'ModerationStatus' => [
        'pending' => 'En attente',
        'approved' => 'Validée',
        'flagged' => 'Question en suspens',
    ],
    'PaymentState' => [
        'pending' => 'En attente',
        'paid' => 'Payé',
        'failed' => 'Échoué',
        'expired' => 'Expiré',
        'cancelled' => 'Annulé',
    ],
];
