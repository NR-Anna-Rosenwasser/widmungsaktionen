@php($date = fn ($d) => $d->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL'))
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ $order->billing_first_name ? __('mail.greeting', ['name' => $order->billing_first_name]) : __('mail.abandoned.greeting') }}

{{ __('mail.abandoned.intro') }}

{!! __('mail.abandoned.deadline', ['date' => $date($order->aktion->order_deadline)]) !!}

<x-mail::button :url="$resumeUrl" color="primary">
{{ __('mail.abandoned.resume') }}
</x-mail::button>

{{ __('mail.abandoned.no_more') }}

{{ __('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}

<x-slot:subcopy>
{{ __('mail.abandoned.opt_out_text') }} [{{ __('mail.abandoned.opt_out') }}]({{ $optOutUrl }})
</x-slot:subcopy>
</x-mail::message>
