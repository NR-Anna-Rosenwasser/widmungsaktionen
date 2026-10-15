@php
    $t = fn (string $key, array $replace = []) => __("invoice.$key", $replace, $locale);
    $date = fn ($d) => $d->locale($locale === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL');
    $chf = fn (int $rappen) => number_format($rappen / 100, 2, '.', "'");
@endphp
<div class="page">
    <div class="accent"></div>

    <div class="sender">{{ $tenant->letterhead }}</div>

    <div class="address {{ $envelope === 'window_left' ? 'window-left' : 'window-right' }}">
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
