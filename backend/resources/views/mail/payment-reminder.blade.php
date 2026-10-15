@php($date = fn ($d) => $d->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL'))
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! __('mail.payment_reminder.intro', ['number' => $order->number, 'date' => $date($order->aktion->order_deadline)]) !!}

@if ($order->payment_method?->value === 'invoice_advance')
{{ __('mail.payment_reminder.invoice') }}
@endif

@if ($canPayOnline)
<x-mail::button :url="$trackingUrl" color="primary">
{{ __('mail.pay_online') }}
</x-mail::button>
@endif

{{ __('mail.payment_reminder.already_paid') }}

{{ __('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}
</x-mail::message>
