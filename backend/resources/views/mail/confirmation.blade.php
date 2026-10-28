<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $text->markdown('mail.confirmation.intro') !!}

@include('mail.partials.summary')

@if ($order->payment_status->value !== 'unpaid')
{!! $text->markdown('mail.confirmation.paid') !!}
@elseif ($order->payment_method?->value === 'invoice_advance')
{!! $text->markdown('mail.confirmation.invoice_advance', ['date' => $date($order->aktion->order_deadline)]) !!}
@if ($canPayOnline)

{!! $text->markdown('mail.confirmation.invoice_advance_online') !!}

<x-mail::button :url="$trackingUrl" color="primary">
{{ $text('mail.pay_online') }}
</x-mail::button>
@endif
@elseif ($order->payment_method?->value === 'invoice_in_parcel')
{!! $text->markdown('mail.confirmation.invoice_in_parcel') !!}
@elseif ($order->payment_method?->value === 'online')
{!! $text->markdown('mail.confirmation.online_pending') !!}
@endif

@include('mail.partials.extra', ['name' => 'mail_confirmation'])

@include('mail.partials.closing')
</x-mail::message>
