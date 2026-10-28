<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{{-- The refund texts themselves are not editable. --}}
{{ __('mail.refunded.intro', ['amount' => number_format($amount / 100, 2, '.', "'")]) }}

{{ $order->payment_method?->value === 'online' ? __('mail.refunded.online') : __('mail.refunded.manual') }}

@include('mail.partials.closing')
</x-mail::message>
