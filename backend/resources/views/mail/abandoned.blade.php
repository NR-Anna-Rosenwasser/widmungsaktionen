<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $order->billing_first_name ? $text('mail.greeting', ['name' => $order->billing_first_name]) : $text('mail.abandoned.greeting') }}

{!! $text->markdown('mail.abandoned.intro') !!}

{!! $text->markdown('mail.abandoned.deadline', ['date' => $date($order->aktion->order_deadline)]) !!}

@include('mail.partials.extra', ['name' => 'mail_abandoned'])

<x-mail::button :url="$resumeUrl" color="primary">
{{ $text('mail.abandoned.resume') }}
</x-mail::button>

{!! $text->markdown('mail.abandoned.no_more') !!}

{{ $text('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}

<x-slot:subcopy>
{{ $text('mail.abandoned.opt_out_text') }} [{{ $text('mail.abandoned.opt_out') }}]({{ $optOutUrl }})
</x-slot:subcopy>
</x-mail::message>
