@php($date = fn ($d) => $d->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL'))
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ __('mail.confirmation.intro') }}

@include('mail.partials.summary')

@if ($order->payment_status->value !== 'unpaid')
{{ __('mail.confirmation.paid') }}
@elseif ($order->payment_method?->value === 'invoice_advance')
{!! __('mail.confirmation.invoice_advance', ['date' => $date($order->aktion->order_deadline)]) !!}
@if ($canPayOnline)

{{ __('mail.confirmation.invoice_advance_online') }}

<x-mail::button :url="$trackingUrl" color="primary">
{{ __('mail.pay_online') }}
</x-mail::button>
@endif
@elseif ($order->payment_method?->value === 'invoice_in_parcel')
{{ __('mail.confirmation.invoice_in_parcel') }}
@elseif ($order->payment_method?->value === 'online')
{{ __('mail.confirmation.online_pending') }}
@endif

@include('mail.partials.closing')
</x-mail::message>
