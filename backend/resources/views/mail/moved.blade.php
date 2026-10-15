@php($date = fn ($d) => $d->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL'))
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ __('mail.moved.intro', ['aktion' => $order->aktion->name]) }}

@if ($order->payment_status->value === 'unpaid')
{!! __('mail.moved.unpaid', ['date' => $date($order->aktion->order_deadline)]) !!}
@endif

@include('mail.partials.summary')

@include('mail.partials.closing')
</x-mail::message>
