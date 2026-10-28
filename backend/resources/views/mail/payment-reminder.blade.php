<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $text->markdown('mail.payment_reminder.intro', ['number' => $order->number, 'date' => $date($order->aktion->order_deadline)]) !!}

@if ($order->payment_method?->value === 'invoice_advance')
{!! $text->markdown('mail.payment_reminder.invoice') !!}
@endif

@if ($canPayOnline)
<x-mail::button :url="$trackingUrl" color="primary">
{{ $text('mail.pay_online') }}
</x-mail::button>
@endif

{!! $text->markdown('mail.payment_reminder.already_paid') !!}

@include('mail.partials.extra', ['name' => 'mail_payment_reminder'])

{{ $text('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}
</x-mail::message>
