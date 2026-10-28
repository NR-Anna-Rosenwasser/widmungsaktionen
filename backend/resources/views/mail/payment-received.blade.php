<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $text->markdown('mail.payment_received.intro') !!}

@include('mail.partials.summary')

@include('mail.partials.extra', ['name' => 'mail_payment_received'])

@include('mail.partials.closing')
</x-mail::message>
