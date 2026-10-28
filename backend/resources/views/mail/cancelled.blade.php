<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $text->markdown('mail.cancelled.intro', ['number' => $order->number]) !!}

@if ($order->cancel_reason)
{!! $text->markdown('mail.cancelled.reason', ['reason' => $order->cancel_reason]) !!}
@elseif ($order->payment_status->value === 'unpaid')
{!! $text->markdown('mail.cancelled.unpaid') !!}
@endif

{{ $text('mail.questions') }}

{{ $text('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}
</x-mail::message>
