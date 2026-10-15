<?php

return [
    'footer' => 'Du erhältst diese E-Mail wegen deiner Bestellung.',
    'greeting' => 'Hallo :name',
    'view_order' => 'Bestellung ansehen',
    'pay_online' => 'Jetzt online bezahlen',
    'signature' => 'Herzliche Grüsse',
    'books' => 'Deine Bücher',
    'book' => 'Buch',
    'dedication' => 'Widmung',
    'signature_only' => 'Nur Unterschrift',
    'shipping' => 'Versand',
    'total' => 'Total',
    'deliver_to' => 'Wir schicken das Paket an :name, :town.',
    'dates' => 'Widmungstag: :signing · Versand voraussichtlich ab :shipping',
    'questions' => 'Fragen? Antworte einfach auf diese E-Mail.',

    'confirmation' => [
        'subject' => 'Deine Bestellung :number',
        'intro' => 'Danke für deine Bestellung! Wir freuen uns, dass dein Buch bald persönlich gewidmet wird.',
        'paid' => 'Deine Zahlung ist bereits eingegangen, danke!',
        'invoice_advance' => 'Im Anhang findest du die QR-Rechnung. Bitte bezahle sie bis am **:date**. Sobald deine Zahlung da ist, ist dein Buch für den Widmungstag eingeplant.',
        'invoice_advance_online' => 'Lieber online bezahlen? Das geht mit Karte oder TWINT:',
        'invoice_in_parcel' => 'Die Rechnung liegt deinem Paket bei.',
        'online_pending' => 'Deine Online-Zahlung ist noch nicht abgeschlossen. Du kannst sie jederzeit hier nachholen:',
    ],
    'payment_received' => [
        'subject' => 'Zahlung erhalten: Bestellung :number',
        'intro' => 'Deine Zahlung ist bei uns eingegangen, danke! Dein Buch ist jetzt für den Widmungstag eingeplant. 💗',
    ],
    'payment_reminder' => [
        'subject' => 'Kleine Erinnerung: Bestellung :number',
        'intro' => 'Wir haben deine Zahlung für die Bestellung :number noch nicht erhalten. Bitte bezahle bis am **:date**, damit dein Buch am Widmungstag dabei ist.',
        'invoice' => 'Die QR-Rechnung findest du nochmals im Anhang.',
        'already_paid' => 'Hast du schon bezahlt? Dann ignoriere diese E-Mail, Überweisungen brauchen manchmal ein paar Tage.',
    ],
    'moved' => [
        'subject' => 'Neue Daten für deine Bestellung :number',
        'intro' => 'Deine Bestellung ist neu Teil der Aktion «:aktion».',
        'unpaid' => 'Wir haben deine Zahlung bis zum Bestellschluss nicht erhalten. Kein Problem: Bezahle bis am **:date**, dann ist dein Buch bei dieser Aktion dabei.',
    ],
    'cancelled' => [
        'subject' => 'Bestellung :number storniert',
        'intro' => 'Deine Bestellung :number wurde storniert.',
        'reason' => 'Grund: :reason',
        'unpaid' => 'Wir haben leider keine Zahlung erhalten. Du kannst jederzeit eine neue Bestellung aufgeben.',
    ],
    'refunded' => [
        'subject' => 'Rückerstattung zu Bestellung :number',
        'intro' => 'Wir haben dir CHF :amount zurückerstattet.',
        'online' => 'Der Betrag wird dir über die ursprüngliche Zahlungsart gutgeschrieben. Das kann einige Tage dauern.',
        'manual' => 'Wir überweisen dir den Betrag in den nächsten Tagen.',
    ],
    'signed' => [
        'subject' => 'Dein Buch ist gewidmet! (:number)',
        'intro' => 'Gute Nachrichten: Dein Buch wurde heute persönlich gewidmet. ✍️',
        'next' => 'Als Nächstes packen wir es ein und schicken es los, voraussichtlich ab :date.',
    ],
    'shipped' => [
        'subject' => 'Dein Buch ist unterwegs (:number)',
        'intro' => 'Dein Buch ist auf dem Weg zu dir!',
        'intro_gift' => 'Das Buch ist auf dem Weg zu :name.',
        'letter' => 'Es kommt als Brief, in der Regel innert 2–3 Arbeitstagen.',
        'parcel' => 'Es kommt als Paket, in der Regel innert 2–3 Arbeitstagen.',
        'tracking' => 'Sendungsnummer: :number',
        'invoice_in_parcel' => 'Die Rechnung liegt dem Paket bei.',
    ],
];
