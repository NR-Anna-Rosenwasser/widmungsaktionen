@php
    $chf = fn (int $rappen) => number_format($rappen / 100, 2, '.', "'");
@endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Begleitbriefe</title>
<style>
@include('pdf.partials.styles')
    .letter h1 { margin-bottom: 5mm; }
    .gift-message { margin: 5mm 0; padding: 5mm 6mm; background: #f8e8ef; border-left: 2mm solid #D989B2; font-size: 12pt; line-height: 1.5; white-space: pre-line; }
    .contents { margin-top: 6mm; }
    .contents li { margin-bottom: 1mm; }
    .closing { margin-top: 8mm; }
    .extra { margin-top: 6mm; }
    .qr { position: absolute; right: 20mm; bottom: 22mm; width: 28mm; text-align: center; font-size: 7.5pt; color: #4a5a85; }
    .qr img { width: 26mm; height: 26mm; display: block; margin: 0 auto 1.5mm; }
    .footer-ref { position: absolute; left: 22mm; bottom: 22mm; font-size: 8pt; color: #4a5a85; }
</style>
</head>
<body>
@foreach ($pages as $page)
    @php
        $order = $page['order'];
        $text = $page['text'];
        $locale = $text->locale;
        $t = fn (string $key, array $replace = []) => $text("letter.$key", $replace);
        $many = $order->items->count() > 1;
        $recipientFirst = $order->is_gift ? $order->shipping_first_name : $order->billing_first_name;
    @endphp
    <div class="page letter">
        <div class="accent"></div>
        <div class="sender">{{ $tenant->letterhead }}</div>
        <div class="address {{ $envelope === 'window_left' ? 'window-left' : 'window-right' }}">
            @foreach ($order->shippingAddressLines() as $line)<div>{{ $line }}</div>@endforeach
        </div>

        <div class="meta">
            <div class="date">{{ $tenant->creditor_town }}, {{ now()->locale(\App\Enums\Locale::from($locale)->carbon())->isoFormat('LL') }}</div>
            <h1>{{ $order->is_gift ? $t('title_gift') : $t('title') }}</h1>
            <p>{{ $t('greeting', ['name' => $recipientFirst]) }}</p>

            @if ($order->is_gift)
                {{ $text->html($many ? 'letter.gift_intro_many' : 'letter.gift_intro', ['sender' => $order->billingName()]) }}
                @if ($order->gift_message)
                    <p style="margin-top: 5mm">{{ $t('gift_message') }}</p>
                    <div class="gift-message">{{ $order->gift_message }}</div>
                @endif
            @else
                {{ $text->html($many ? 'letter.intro_many' : 'letter.intro') }}
            @endif

            <div class="contents">
                <strong>{{ $t('contents') }}</strong>
                <ul>
                    @foreach ($order->items as $item)
                        <li>{{ $item->book->title }}@if (! $item->signature_only && $item->recipient_name) · {{ $item->recipient_name }}@endif</li>
                    @endforeach
                </ul>
            </div>

            @unless ($order->is_gift)
                @if ($order->payment_status->value === 'paid')
                    <p class="muted">{{ $t('paid') }}</p>
                @elseif ($page['invoice'])
                    <p>{{ $t('invoice_enclosed', ['date' => $page['invoice']['dueDate']->locale(\App\Enums\Locale::from($locale)->carbon())->isoFormat('LL')]) }}</p>
                @endif
            @endunless

            @if ($extra = $text->extraHtml('letter'))
                <div class="extra">{{ $extra }}</div>
            @endif

            <div class="closing">
                {{ $t('signature') }}<br>
                {{ $tenant->mail_from_name ?: $tenant->name }}
            </div>
        </div>

        <div class="footer-ref">#{{ $order->stack_number }} · {{ $t('order', ['number' => $order->number]) }}</div>
        @if ($page['qr'])
            <div class="qr"><img src="{{ $page['qr'] }}" alt="">{{ $t('qr') }}</div>
        @endif
    </div>

    @if ($page['invoice'])
        @include('pdf.partials.invoice-page', $page['invoice'])
    @endif
@endforeach
</body>
</html>
