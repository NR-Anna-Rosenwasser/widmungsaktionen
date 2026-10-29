<?php

namespace App\Support\Texts;

/**
 * Every customer-facing text a shop can edit, grouped as in the admin
 * («Einstellungen → Texte»). Defaults live in lang/{de,fr,it,en}/{storefront,
 * mail,letter,invoice}.php under the same key.
 *
 * Each text: [admin label, kind, placeholders]. {shop} (shop name) works
 * everywhere and is not listed. A group's "extra" is the optional text an
 * Aktion can add at that place (stored on the Aktion, see Texts::extra()).
 */
class TextCatalog
{
    /** Single line, no formatting. */
    public const TEXT = 'text';

    /** Several lines, no formatting. */
    public const LONG = 'long';

    /** Markdown: bold, italic, link, bullet list. */
    public const MARKDOWN = 'markdown';

    /** Markdown for print: bold and italic only. */
    public const PRINT = 'print';

    /** Every platform language (App\Enums\Locale); a shop uses a subset, Tenant::locales(). */
    public const LOCALES = ['de', 'fr', 'it', 'en'];

    /**
     * @return array<string, array{label: string, area: string, extra?: array{0: string, 1: string}, texts: array<string, array{0: string, 1: string, 2?: array<int, string>}>}>
     */
    public static function groups(): array
    {
        $t = self::TEXT;
        $l = self::LONG;
        $md = self::MARKDOWN;
        $p = self::PRINT;

        return [
            // Storefront ------------------------------------------------------
            'home' => [
                'label' => 'Startseite',
                'area' => 'Shop',
                'extra' => ['home', 'Startseite: Zusatztext nach der Einleitung'],
                'texts' => [
                    'storefront.home.eyebrow' => ['Kleiner Titel über der Überschrift (ohne laufende Aktion, sonst steht dort der Name der Aktion)', $t],
                    'storefront.home.title' => ['Überschrift', $t],
                    'storefront.home.lead' => ['Einleitung', $md],
                    'storefront.home.cta' => ['Bestell-Button', $t],
                    'storefront.home.booksTitle' => ['Titel «Bücher»', $t],
                    'storefront.home.shippingFrom' => ['Hinweis Versandkosten unter den Büchern', $t, ['price']],
                    'storefront.home.howTitle' => ['Titel «So funktioniert’s»', $t],
                    'storefront.home.how1Title' => ['Schritt 1: Titel', $t],
                    'storefront.home.how1Text' => ['Schritt 1: Text', $md],
                    'storefront.home.how2Title' => ['Schritt 2: Titel', $t],
                    'storefront.home.how2Text' => ['Schritt 2: Text', $md],
                    'storefront.home.how3Title' => ['Schritt 3: Titel', $t],
                    'storefront.home.how3Text' => ['Schritt 3: Text', $md],
                    'storefront.home.seoTitle' => ['Seitentitel / Link-Vorschau: Titel', $t],
                    'storefront.home.seoDescription' => ['Link-Vorschau: Beschreibung', $l],
                ],
            ],
            'home_status' => [
                'label' => 'Startseite: Daten & keine Aktion',
                'area' => 'Shop',
                'texts' => [
                    'storefront.home.deadline' => ['Beschriftung «Bestellschluss»', $t],
                    'storefront.home.signingDate' => ['Beschriftung «Widmungstag»', $t],
                    'storefront.home.shipping' => ['Beschriftung «Versand ca.»', $t],
                    'storefront.home.full' => ['Aktion ausgebucht', $t],
                    'storefront.home.closedTitle' => ['Titel, wenn keine Aktion läuft', $t],
                    'storefront.home.closedText' => ['Text, wenn die nächste Aktion geplant ist', $l, ['date']],
                    'storefront.home.closedNoNext' => ['Text, wenn noch keine Aktion geplant ist', $l, ['email']],
                    'storefront.home.optedOut' => ['Bestätigung nach «Entwurf löschen» (Link in der Erinnerungsmail)', $l],
                ],
            ],
            'lookup' => [
                'label' => 'Bestellung wiederfinden',
                'area' => 'Shop',
                'texts' => [
                    'storefront.home.lookupTitle' => ['Titel', $t],
                    'storefront.home.lookupText' => ['Text', $md],
                    'storefront.home.lookupNumber' => ['Feld «Bestellnummer»', $t],
                    'storefront.home.lookupEmail' => ['Feld «E-Mail-Adresse»', $t],
                    'storefront.home.lookupSubmit' => ['Button', $t],
                    'storefront.home.lookupNotFound' => ['Nicht gefunden', $t],
                ],
            ],
            'order_books' => [
                'label' => 'Bestellformular: Bücher',
                'area' => 'Shop',
                'extra' => ['order', 'Bestellformular: Zusatztext beim ersten Schritt (Bücher)'],
                'texts' => [
                    'storefront.order.books.title' => ['Titel', $t],
                    'storefront.order.books.lead' => ['Einleitung', $md, ['max']],
                    'storefront.order.books.more' => ['Hinweis «Mehr Bücher?»', $md, ['max', 'email']],
                    'storefront.order.books.shippingTitle' => ['Titel Versandkosten', $t],
                    'storefront.order.books.shippingTier' => ['Versandstufe', $t, ['range', 'price', 'method']],
                    'storefront.order.books.shippingRangeOne' => ['Versandstufe: ein Buch', $t],
                    'storefront.order.books.shippingRangeExact' => ['Versandstufe: genau x Bücher', $t, ['count']],
                    'storefront.order.books.shippingRange' => ['Versandstufe: von–bis', $t, ['from', 'to']],
                    'storefront.order.books.method.letter' => ['Versandart «Brief»', $t],
                    'storefront.order.books.method.parcel' => ['Versandart «Paket»', $t],
                    'storefront.order.books.runningTotalOne' => ['Zwischensumme bei einem Buch', $t, ['subtotal', 'shipping', 'total']],
                    'storefront.order.books.runningTotal' => ['Zwischensumme bei mehreren Büchern', $t, ['books', 'subtotal', 'shipping', 'total']],
                ],
            ],
            'order_email' => [
                'label' => 'Bestellformular: E-Mail',
                'area' => 'Shop',
                'texts' => [
                    'storefront.order.email.title' => ['Titel', $t],
                    'storefront.order.email.lead' => ['Einleitung', $md],
                    'storefront.order.email.label' => ['Feld «E-Mail-Adresse»', $t],
                    'storefront.order.email.privacy' => ['Hinweis Speicherung & Erinnerung', $md],
                ],
            ],
            'order_dedication' => [
                'label' => 'Bestellformular: Widmung',
                'area' => 'Shop',
                'texts' => [
                    'storefront.order.dedication.title' => ['Titel (ein Buch)', $t],
                    'storefront.order.dedication.titleMany' => ['Titel (mehrere Bücher)', $t],
                    'storefront.order.dedication.lead' => ['Einleitung (mehrere Bücher)', $md],
                    'storefront.order.dedication.copy' => ['Exemplar-Nummer', $t, ['n']],
                    'storefront.order.dedication.withDedication' => ['Auswahl «Mit Widmung»', $t],
                    'storefront.order.dedication.signatureOnly' => ['Auswahl «Nur Unterschrift»', $t],
                    'storefront.order.dedication.recipient' => ['Feld «Für wen?»', $t],
                    'storefront.order.dedication.recipientPlaceholder' => ['Beispiel im Feld «Für wen?»', $t],
                    'storefront.order.dedication.wish' => ['Feld «Widmungswunsch»', $t],
                    'storefront.order.dedication.wishPlaceholder' => ['Beispiel im Feld «Widmungswunsch»', $t],
                    'storefront.order.dedication.wishHint' => ['Hinweis zum Widmungswunsch', $md],
                    'storefront.order.dedication.chars' => ['Zeichenzähler', $t, ['count', 'max']],
                ],
            ],
            'order_address' => [
                'label' => 'Bestellformular: Adresse',
                'area' => 'Shop',
                'texts' => [
                    'storefront.order.address.title' => ['Titel', $t],
                    'storefront.order.address.lead' => ['Einleitung', $md],
                    'storefront.order.address.firstName' => ['Feld «Vorname»', $t],
                    'storefront.order.address.lastName' => ['Feld «Nachname»', $t],
                    'storefront.order.address.company' => ['Feld «Firma / c/o»', $t],
                    'storefront.order.address.street' => ['Feld «Strasse und Nr.»', $t],
                    'storefront.order.address.postalCode' => ['Feld «PLZ»', $t],
                    'storefront.order.address.town' => ['Feld «Ort»', $t],
                    'storefront.order.address.gift' => ['Auswahl «Als Geschenk»', $t],
                    'storefront.order.address.giftHint' => ['Hinweis zum Geschenk', $md],
                    'storefront.order.address.multipleRecipients' => ['Hinweis «Mehrere Empfänger*innen»', $md],
                    'storefront.order.address.shippingTitle' => ['Titel Lieferadresse', $t],
                    'storefront.order.address.giftMessage' => ['Feld «Nachricht für den Begleitbrief»', $t],
                    'storefront.order.address.giftMessagePlaceholder' => ['Beispiel im Feld «Nachricht»', $t],
                    'storefront.order.address.giftMessageHint' => ['Hinweis zur Nachricht', $md, ['name']],
                ],
            ],
            'order_payment' => [
                'label' => 'Bestellformular: Zahlung',
                'area' => 'Shop',
                'texts' => [
                    'storefront.order.payment.title' => ['Titel', $t],
                    'storefront.order.payment.lead' => ['Einleitung', $md, ['total']],
                    'storefront.order.payment.methods.online.title' => ['Online: Titel', $t],
                    'storefront.order.payment.methods.online.text' => ['Online: Beschreibung', $t],
                    'storefront.order.payment.methods.invoice_advance.title' => ['Rechnung per E-Mail: Titel', $t],
                    'storefront.order.payment.methods.invoice_advance.text' => ['Rechnung per E-Mail: Beschreibung', $t],
                    'storefront.order.payment.methods.invoice_in_parcel.title' => ['Rechnung im Paket: Titel', $t],
                    'storefront.order.payment.methods.invoice_in_parcel.text' => ['Rechnung im Paket: Beschreibung', $t],
                    'storefront.order.payment.giftNotPossible' => ['Hinweis «Bei Geschenken nicht möglich»', $t],
                ],
            ],
            'order_summary' => [
                'label' => 'Bestellformular: Übersicht',
                'area' => 'Shop',
                'texts' => [
                    'storefront.order.summary.title' => ['Titel', $t],
                    'storefront.order.summary.lead' => ['Einleitung', $md],
                    'storefront.order.summary.books' => ['Titel «Bücher»', $t],
                    'storefront.order.summary.dedication' => ['Beschriftung «Widmung»', $t],
                    'storefront.order.summary.signatureOnly' => ['«Nur Unterschrift» (auch auf der Bestellseite)', $t],
                    'storefront.order.summary.surprise' => ['Name ohne Wunsch: «lass dich überraschen» (auch auf der Bestellseite)', $t],
                    'storefront.order.summary.billing' => ['Titel «Rechnungsadresse»', $t],
                    'storefront.order.summary.shipping' => ['Titel «Lieferadresse»', $t],
                    'storefront.order.summary.sameAsBilling' => ['«Wie Rechnungsadresse»', $t],
                    'storefront.order.summary.giftMessage' => ['Beschriftung «Nachricht»', $t],
                    'storefront.order.summary.payment' => ['Titel «Zahlung»', $t],
                    'storefront.order.summary.subtotal' => ['Zeile «Bücher»', $t],
                    'storefront.order.summary.shippingCost' => ['Zeile «Versand»', $t],
                    'storefront.order.summary.total' => ['Zeile «Total» (auch auf der Bestellseite)', $t],
                    'storefront.order.summary.newsletter' => ['Checkbox Newsletter', $l],
                    'storefront.order.summary.terms' => ['Checkbox AGB & Datenschutz', $l, ['terms', 'privacy']],
                    'storefront.order.summary.termsLink' => ['Link-Text «AGB»', $t],
                    'storefront.order.summary.privacyLink' => ['Link-Text «Datenschutzerklärung»', $t],
                    'storefront.order.summary.submit' => ['Button «Verbindlich bestellen»', $t],
                    'storefront.order.summary.edit' => ['Link «Ändern»', $t],
                ],
            ],
            'tracking' => [
                'label' => 'Bestellseite',
                'area' => 'Shop',
                'extra' => ['tracking', 'Bestellseite: Zusatztext nach dem Dankeschön'],
                'texts' => [
                    'storefront.tracking.thanksTitle' => ['Dankeschön: Titel', $t],
                    'storefront.tracking.thanksText' => ['Dankeschön: Text', $md, ['email']],
                    'storefront.tracking.another' => ['Link «Noch eine Bestellung»', $t],
                    'storefront.tracking.title' => ['Titel', $t],
                    'storefront.tracking.number' => ['Bestellnummer', $t, ['number']],
                    'storefront.tracking.steps.submitted' => ['Status «Bestellt»', $t],
                    'storefront.tracking.steps.paid' => ['Status «Bezahlt»', $t],
                    'storefront.tracking.steps.signed' => ['Status «Gewidmet»', $t],
                    'storefront.tracking.steps.shipped' => ['Status «Versendet»', $t],
                    'storefront.tracking.steps.cancelled' => ['Status «Storniert»', $t],
                    'storefront.tracking.expected.signed' => ['Erwartet: Widmungstag', $t, ['date']],
                    'storefront.tracking.expected.shipped' => ['Erwartet: Versand', $t, ['date']],
                    'storefront.tracking.paymentPending.online' => ['Zahlung offen: online', $t],
                    'storefront.tracking.paymentPending.invoice_advance' => ['Zahlung offen: Rechnung per E-Mail', $t, ['date']],
                    'storefront.tracking.paymentPending.invoice_in_parcel' => ['Zahlung offen: Rechnung im Paket', $t],
                    'storefront.tracking.payNowHint' => ['Online bezahlen: Hinweis', $t],
                    'storefront.tracking.payNow' => ['Online bezahlen: Button', $t],
                    'storefront.tracking.paymentProcessing' => ['Zahlung wird bestätigt', $t],
                    'storefront.tracking.paymentConfirmed' => ['Zahlung erhalten', $t],
                    'storefront.tracking.paymentFailed' => ['Online-Zahlung nicht verfügbar', $t],
                    'storefront.tracking.cancelledNotice' => ['Hinweis bei Stornierung', $l, ['email']],
                    'storefront.tracking.copies' => ['Titel «Deine Bücher»', $t],
                    'storefront.tracking.delivery' => ['Titel «Lieferung an»', $t],
                    'storefront.tracking.trackingNumber' => ['Sendungsnummer', $t, ['number']],
                    'storefront.tracking.aktion' => ['Beschriftung «Aktion»', $t],
                    'storefront.tracking.invoice' => ['Link Rechnung herunterladen', $t],
                    'storefront.tracking.notFound' => ['Bestellung nicht gefunden', $t],
                ],
            ],
            'consent' => [
                'label' => 'Cookie-Banner',
                'area' => 'Shop',
                'texts' => [
                    'storefront.consent.title' => ['Titel', $t],
                    'storefront.consent.description' => ['Text', $l],
                    'storefront.consent.acceptAll' => ['Button «Alle akzeptieren»', $t],
                    'storefront.consent.acceptNecessary' => ['Button «Nur notwendige»', $t],
                    'storefront.consent.showPreferences' => ['Button «Einstellungen»', $t],
                    'storefront.consent.preferencesTitle' => ['Einstellungen: Titel', $t],
                    'storefront.consent.save' => ['Einstellungen: Button «Speichern»', $t],
                    'storefront.consent.necessaryTitle' => ['Notwendig: Titel', $t],
                    'storefront.consent.necessaryText' => ['Notwendig: Text', $l],
                    'storefront.consent.analyticsTitle' => ['Statistik: Titel', $t],
                    'storefront.consent.analyticsText' => ['Statistik: Text', $l],
                    'storefront.consent.marketingTitle' => ['Marketing: Titel', $t],
                    'storefront.consent.marketingText' => ['Marketing: Text', $l],
                ],
            ],
            'labels_order' => [
                'label' => 'Beschriftungen: Bestellformular',
                'area' => 'Shop',
                'texts' => [
                    'storefront.order.stepOf' => ['Fortschritt', $t, ['current', 'total']],
                    'storefront.order.saving' => ['«Wird gespeichert …»', $t],
                    'storefront.order.next' => ['Button «Weiter»', $t],
                    'storefront.order.pressEnter' => ['Hinweis «oder Enter drücken»', $t],
                    'storefront.order.back' => ['Button «Zurück»', $t],
                    'storefront.order.books.add' => ['Button «Eins mehr» (für Screenreader)', $t],
                    'storefront.order.books.remove' => ['Button «Eins weniger» (für Screenreader)', $t],
                ],
            ],
            'labels_site' => [
                'label' => 'Beschriftungen: Navigation, Fusszeile, 404',
                'area' => 'Shop',
                'texts' => [
                    'storefront.nav.order' => ['Seitentitel «Bestellen»', $t],
                    'storefront.nav.language' => ['Sprachwahl (für Screenreader)', $t],
                    'storefront.footer.contact' => ['Fusszeile: Kontakt', $t],
                    'storefront.footer.terms' => ['Fusszeile: AGB', $t],
                    'storefront.footer.privacy' => ['Fusszeile: Datenschutz', $t],
                    'storefront.footer.imprint' => ['Fusszeile: Impressum', $t],
                    'storefront.footer.cookies' => ['Fusszeile: Cookie-Einstellungen', $t],
                    'storefront.notFound.title' => ['Seite nicht gefunden: Titel', $t],
                    'storefront.notFound.text' => ['Seite nicht gefunden: Text', $t],
                    'storefront.notFound.back' => ['Seite nicht gefunden: Link', $t],
                ],
            ],

            // Emails ----------------------------------------------------------
            'mail' => [
                'label' => 'E-Mails: Allgemein',
                'area' => 'E-Mails',
                'texts' => [
                    'mail.greeting' => ['Anrede', $t, ['name']],
                    'mail.questions' => ['«Fragen? Antworte einfach …»', $t],
                    'mail.signature' => ['Grussformel (darunter steht der Absendername)', $t],
                    'mail.view_order' => ['Button «Bestellung ansehen»', $t],
                    'mail.pay_online' => ['Button «Jetzt online bezahlen»', $t],
                    'mail.footer' => ['Fusszeile (nach dem Shopnamen)', $t],
                    'mail.book' => ['Übersicht: Spalte «Buch»', $t],
                    'mail.dedication' => ['Übersicht: Spalte «Widmung»', $t],
                    'mail.signature_only' => ['Übersicht: «Nur Unterschrift»', $t],
                    'mail.surprise' => ['Übersicht: Name ohne Wunsch («lass dich überraschen»)', $t],
                    'mail.shipping' => ['Übersicht: Zeile «Versand»', $t],
                    'mail.total' => ['Übersicht: Zeile «Total»', $t],
                    'mail.deliver_to' => ['Übersicht: Lieferung bei Geschenken', $t, ['name', 'town']],
                    'mail.dates' => ['Übersicht: Daten', $t, ['signing', 'shipping']],
                ],
            ],
            'mail_confirmation' => [
                'label' => 'E-Mail: Bestellbestätigung',
                'area' => 'E-Mails',
                'extra' => ['mail_confirmation', 'E-Mail Bestellbestätigung: Zusatztext'],
                'texts' => [
                    'mail.confirmation.subject' => ['Betreff', $t, ['number']],
                    'mail.confirmation.intro' => ['Einleitung', $md],
                    'mail.confirmation.paid' => ['Schon bezahlt', $md],
                    'mail.confirmation.invoice_advance' => ['Rechnung per E-Mail', $md, ['date']],
                    'mail.confirmation.invoice_advance_online' => ['Rechnung per E-Mail: «Lieber online bezahlen?»', $md],
                    'mail.confirmation.invoice_in_parcel' => ['Rechnung im Paket', $md],
                    'mail.confirmation.online_pending' => ['Online-Zahlung noch offen', $md],
                ],
            ],
            'mail_payment_received' => [
                'label' => 'E-Mail: Zahlung erhalten',
                'area' => 'E-Mails',
                'extra' => ['mail_payment_received', 'E-Mail Zahlung erhalten: Zusatztext'],
                'texts' => [
                    'mail.payment_received.subject' => ['Betreff', $t, ['number']],
                    'mail.payment_received.intro' => ['Text', $md],
                ],
            ],
            'mail_payment_reminder' => [
                'label' => 'E-Mail: Zahlungserinnerung',
                'area' => 'E-Mails',
                'extra' => ['mail_payment_reminder', 'E-Mail Zahlungserinnerung: Zusatztext'],
                'texts' => [
                    'mail.payment_reminder.subject' => ['Betreff', $t, ['number']],
                    'mail.payment_reminder.intro' => ['Text', $md, ['number', 'date']],
                    'mail.payment_reminder.invoice' => ['Hinweis QR-Rechnung im Anhang', $md],
                    'mail.payment_reminder.already_paid' => ['«Schon bezahlt?»', $md],
                ],
            ],
            'mail_moved' => [
                'label' => 'E-Mail: In andere Aktion verschoben',
                'area' => 'E-Mails',
                'extra' => ['mail_moved', 'E-Mail In diese Aktion verschoben: Zusatztext'],
                'texts' => [
                    'mail.moved.subject' => ['Betreff', $t, ['number']],
                    'mail.moved.intro' => ['Text', $md, ['aktion']],
                    'mail.moved.unpaid' => ['Hinweis bei offener Zahlung', $md, ['date']],
                ],
            ],
            'mail_signed' => [
                'label' => 'E-Mail: Buch gewidmet (nur wenn in den Einstellungen aktiviert)',
                'area' => 'E-Mails',
                'extra' => ['mail_signed', 'E-Mail Buch gewidmet: Zusatztext'],
                'texts' => [
                    'mail.signed.subject' => ['Betreff', $t, ['number']],
                    'mail.signed.intro' => ['Text', $md],
                    'mail.signed.next' => ['Wie es weitergeht', $md, ['date']],
                ],
            ],
            'mail_shipped' => [
                'label' => 'E-Mail: Versendet',
                'area' => 'E-Mails',
                'extra' => ['mail_shipped', 'E-Mail Versendet: Zusatztext'],
                'texts' => [
                    'mail.shipped.subject' => ['Betreff', $t, ['number']],
                    'mail.shipped.intro' => ['Text', $md],
                    'mail.shipped.intro_gift' => ['Text bei Geschenken', $md, ['name']],
                    'mail.shipped.letter' => ['Versand als Brief', $md],
                    'mail.shipped.parcel' => ['Versand als Paket', $md],
                    'mail.shipped.tracking' => ['Sendungsnummer', $t, ['number']],
                    'mail.shipped.invoice_in_parcel' => ['Hinweis Rechnung im Paket', $md],
                ],
            ],
            'mail_abandoned' => [
                'label' => 'E-Mail: Bestellung nicht abgeschlossen',
                'area' => 'E-Mails',
                'extra' => ['mail_abandoned', 'E-Mail Bestellung nicht abgeschlossen: Zusatztext'],
                'texts' => [
                    'mail.abandoned.subject' => ['Betreff', $t],
                    'mail.abandoned.greeting' => ['Anrede ohne Namen', $t],
                    'mail.abandoned.intro' => ['Text', $md],
                    'mail.abandoned.deadline' => ['Bestellschluss', $md, ['date']],
                    'mail.abandoned.resume' => ['Button «Bestellung fortsetzen»', $t],
                    'mail.abandoned.no_more' => ['«Einzige Erinnerung»', $md],
                    'mail.abandoned.opt_out_text' => ['Abmelden: Text', $t],
                    'mail.abandoned.opt_out' => ['Abmelden: Link', $t],
                ],
            ],
            'mail_cancelled' => [
                'label' => 'E-Mail: Storniert',
                'area' => 'E-Mails',
                'texts' => [
                    'mail.cancelled.subject' => ['Betreff', $t, ['number']],
                    'mail.cancelled.intro' => ['Text', $md, ['number']],
                    'mail.cancelled.reason' => ['Grund (falls angegeben)', $md, ['reason']],
                    'mail.cancelled.unpaid' => ['Hinweis, wenn keine Zahlung kam', $md],
                ],
            ],

            // Print -----------------------------------------------------------
            'letter' => [
                'label' => 'Begleitbrief',
                'area' => 'Druck',
                'extra' => ['letter', 'Begleitbrief: P.S. vor der Grussformel'],
                'texts' => [
                    'letter.title' => ['Titel', $t],
                    'letter.title_gift' => ['Titel bei Geschenken', $t],
                    'letter.greeting' => ['Anrede', $t, ['name']],
                    'letter.intro' => ['Text (ein Buch)', $p],
                    'letter.intro_many' => ['Text (mehrere Bücher)', $p],
                    'letter.gift_intro' => ['Text bei Geschenken (ein Buch)', $p, ['sender']],
                    'letter.gift_intro_many' => ['Text bei Geschenken (mehrere Bücher)', $p, ['sender']],
                    'letter.gift_message' => ['Vor der Geschenknachricht', $t],
                    'letter.contents' => ['Titel «Inhalt»', $t],
                    'letter.paid' => ['Schon bezahlt', $t],
                    'letter.invoice_enclosed' => ['Rechnung liegt bei', $t, ['date']],
                    'letter.signature' => ['Grussformel (darunter steht der Absendername)', $t],
                    'letter.order' => ['Fusszeile: Bestellnummer', $t, ['number']],
                    'letter.qr' => ['Beschriftung QR-Code', $t],
                ],
            ],
            'invoice' => [
                'label' => 'Rechnung',
                'area' => 'Druck',
                'extra' => ['invoice', 'Rechnung: Hinweis unter der Tabelle'],
                'texts' => [
                    'invoice.title' => ['Titel', $t, ['number']],
                    'invoice.date' => ['Ort und Datum', $t, ['town', 'date']],
                    'invoice.greeting' => ['Anrede', $t, ['name']],
                    'invoice.intro_advance' => ['Text (Rechnung per E-Mail)', $p, ['date']],
                    'invoice.intro_parcel' => ['Text (Rechnung im Paket)', $p, ['date']],
                    'invoice.book' => ['Spalte «Buch»', $t],
                    'invoice.dedication_for' => ['«Widmung für …»', $t, ['name']],
                    'invoice.signature_only' => ['«Signiert» (ohne Widmung)', $t],
                    'invoice.shipping' => ['Zeile «Versand»', $t],
                    'invoice.total' => ['Zeile «Total»', $t],
                    'invoice.reference' => ['Beschriftung «Referenz»', $t],
                    'invoice.aktion' => ['Beschriftung «Widmungsaktion»', $t],
                    'invoice.questions' => ['«Fragen? Schreib uns …»', $t, ['email']],
                    'invoice.paid' => ['Schon bezahlt', $t],
                    'invoice.cancelled' => ['Hinweis bei Stornierung', $t],
                    'invoice.cancelled_stamp' => ['Stempel «STORNIERT»', $t],
                ],
            ],
        ];
    }

    /**
     * All texts, flat: key => [label, kind, placeholders, group].
     *
     * @return array<string, array{label: string, kind: string, placeholders: array<int, string>, group: string}>
     */
    public static function texts(): array
    {
        static $texts;

        return $texts ??= collect(self::groups())
            ->flatMap(fn (array $group, string $name) => collect($group['texts'])->map(fn (array $text) => [
                'label' => $text[0],
                'kind' => $text[1],
                'placeholders' => ['shop', ...($text[2] ?? [])],
                'group' => $name,
            ]))
            ->all();
    }

    /**
     * Texts an Aktion can add: name => admin label.
     *
     * @return array<string, string>
     */
    public static function extras(): array
    {
        return collect(self::groups())
            ->filter(fn (array $group) => isset($group['extra']))
            ->mapWithKeys(fn (array $group) => [$group['extra'][0] => $group['extra'][1]])
            ->all();
    }

    /**
     * Print extras get the print toolbar (no links).
     */
    public static function extraKind(string $name): string
    {
        return in_array($name, ['letter', 'invoice'], true) ? self::PRINT : self::MARKDOWN;
    }

    public static function isMarkdown(string $kind): bool
    {
        return in_array($kind, [self::MARKDOWN, self::PRINT], true);
    }
}
