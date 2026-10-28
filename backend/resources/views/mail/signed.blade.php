<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $text->markdown('mail.signed.intro') !!}

{!! $text->markdown('mail.signed.next', ['date' => $date($order->aktion->estimated_shipping_date)]) !!}

@include('mail.partials.extra', ['name' => 'mail_signed'])

@include('mail.partials.closing')
</x-mail::message>
