<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ __('mail.refunded.intro', ['amount' => number_format($amount / 100, 2, '.', "'")]) }}

{{ $order->payment_method?->value === 'online' ? __('mail.refunded.online') : __('mail.refunded.manual') }}

@include('mail.partials.closing')
</x-mail::message>
