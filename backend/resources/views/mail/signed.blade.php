@php($date = fn ($d) => $d->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL'))
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ __('mail.signed.intro') }}

{{ __('mail.signed.next', ['date' => $date($order->aktion->estimated_shipping_date)]) }}

@include('mail.partials.closing')
</x-mail::message>
