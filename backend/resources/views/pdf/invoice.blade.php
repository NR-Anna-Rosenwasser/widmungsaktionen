@php
    $t = fn (string $key, array $replace = []) => __("invoice.$key", $replace, $locale);
    $date = fn ($d) => $d->locale($locale === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL');
    $chf = fn (int $rappen) => number_format($rappen / 100, 2, '.', "'");
    // Address block position for window envelopes (SN 010130), otherwise right-aligned like a window right.
    $addressLeft = $envelope === 'window_left' ? '22mm' : '118mm';
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<title>{{ $t('title', ['number' => $order->number]) }}</title>
<style>
    @font-face { font-family: 'Barlow'; font-weight: 600; src: url('barlow-latin-600-normal.woff2'); }
    @font-face { font-family: 'Barlow'; font-weight: 700; src: url('barlow-latin-700-normal.woff2'); }
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 400; src: url('hanken-grotesk-latin-400-normal.woff2'); }
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 600; src: url('hanken-grotesk-latin-600-normal.woff2'); }

    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'Hanken Grotesk', Arial, sans-serif; font-size: 10pt; line-height: 1.45; color: #2B3C69; }
    .page { position: relative; width: 210mm; height: 297mm; overflow: hidden; }
    .sender { position: absolute; top: 15mm; left: 22mm; width: 85mm; font-size: 8.5pt; white-space: pre-line; color: #4a5a85; }
    .address { position: absolute; top: 45mm; left: {{ $addressLeft }}; width: 80mm; font-size: 10.5pt; }
    .meta { position: absolute; top: 78mm; left: 22mm; right: 20mm; }
    p { margin: 0 0 2.5mm; }
    h1 { font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 18pt; margin: 0 0 3mm; letter-spacing: -0.01em; }
    .date { text-align: right; margin-bottom: 4mm; }
    /* Scoped: the QR payment part brings its own table markup. */
    table.items { width: 100%; border-collapse: collapse; margin-top: 4mm; }
    .items th { text-align: left; font-family: 'Barlow', Arial, sans-serif; font-weight: 600; border-bottom: 1.5pt solid #2B3C69; padding: 1.5mm 0; }
    .items td { padding: 1.3mm 0; border-bottom: 0.5pt solid #EEC6D8; vertical-align: top; }
    .items td.amount, .items th.amount { text-align: right; white-space: nowrap; }
    .items tr.total td { font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 12pt; border-bottom: none; padding-top: 3mm; }
    .muted { color: #4a5a85; font-size: 9pt; }
    .facts { margin-top: 4mm; font-size: 9pt; color: #4a5a85; }
    .paid { margin-top: 8mm; font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 14pt; color: #b8668f; }
    .cancelled { margin-top: 8mm; padding: 4mm 5mm; border: 1.5pt solid #a8324a; border-radius: 1mm; font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 13pt; color: #a8324a; }
    .cancelled-stamp { position: absolute; bottom: 40mm; left: 0; width: 210mm; text-align: center; font-family: 'Barlow', Arial, sans-serif; font-weight: 700; font-size: 44pt; letter-spacing: 0.08em; color: rgba(168, 50, 74, 0.18); transform: rotate(-8deg); }
    .payment-part { position: absolute; bottom: 0; left: 0; width: 210mm; height: 105mm; }
    .accent { position: absolute; top: 0; left: 0; width: 210mm; height: 5mm; background: #D989B2; }
</style>
</head>
<body>
<div class="page">
    <div class="accent"></div>

    <div class="sender">{{ $tenant->letterhead }}</div>

    <div class="address">
        @if ($order->billing_company)<div>{{ $order->billing_company }}</div>@endif
        <div>{{ $order->billingName() }}</div>
        <div>{{ $order->billing_street }}</div>
        <div>{{ $order->billing_postal_code }} {{ $order->billing_town }}</div>
    </div>

    <div class="meta">
        <div class="date">{{ $t('date', ['town' => $tenant->creditor_town, 'date' => $date($order->submitted_at ?? now())]) }}</div>

        <h1>{{ $t('title', ['number' => $order->number]) }}</h1>
        <p>{{ $t('greeting', ['name' => $order->billing_first_name]) }}</p>
        @unless ($cancelled)
            <p>{{ $t($payment->method->value === 'invoice_advance' ? 'intro_advance' : 'intro_parcel', ['date' => $date($dueDate)]) }}</p>
        @endunless

        <table class="items">
            <thead>
                <tr><th>{{ $t('book') }}</th><th class="amount">CHF</th></tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->book->title }}
                            <span class="muted">·
                                {{ $item->signature_only || ! $item->recipient_name ? $t('signature_only') : $t('dedication_for', ['name' => $item->recipient_name]) }}
                            </span>
                        </td>
                        <td class="amount">{{ $chf($item->unit_price) }}</td>
                    </tr>
                @endforeach
                <tr><td>{{ $t('shipping') }}</td><td class="amount">{{ $chf($order->shipping_cost) }}</td></tr>
                <tr class="total"><td>{{ $t('total') }}</td><td class="amount">{{ $chf($order->total) }}</td></tr>
            </tbody>
        </table>

        <div class="facts">
            {{ $t('reference') }}: {{ $reference }} ·
            {{ $t('aktion') }}: {{ $order->aktion->name }}
            @if ($tenant->contact_email)<br>{{ $t('questions', ['email' => $tenant->contact_email]) }}@endif
        </div>

        @if ($cancelled)
            <div class="cancelled">{{ $t('cancelled') }}</div>
        @elseif ($payment->status->value === 'paid')
            <div class="paid">{{ $t('paid') }}</div>
        @endif
    </div>

    @if ($cancelled)
        <div class="cancelled-stamp">{{ $t('cancelled_stamp') }}</div>
    @elseif ($payment->status->value !== 'paid')
        <div class="payment-part">{!! $paymentPart !!}</div>
    @endif
</div>
</body>
</html>
