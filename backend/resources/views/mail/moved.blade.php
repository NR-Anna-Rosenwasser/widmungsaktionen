<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $text->markdown('mail.moved.intro', ['aktion' => $order->aktion->name]) !!}

@if ($order->payment_status->value === 'unpaid')
{!! $text->markdown('mail.moved.unpaid', ['date' => $date($order->aktion->order_deadline)]) !!}
@endif

@include('mail.partials.summary')

@include('mail.partials.extra', ['name' => 'mail_moved'])

@include('mail.partials.closing')
</x-mail::message>
