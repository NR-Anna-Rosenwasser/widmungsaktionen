<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ __('mail.payment_received.intro') }}

@include('mail.partials.summary')

@include('mail.partials.closing')
</x-mail::message>
