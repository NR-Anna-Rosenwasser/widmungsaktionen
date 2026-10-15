<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ __('mail.cancelled.intro', ['number' => $order->number]) }}

@if ($order->cancel_reason)
{{ __('mail.cancelled.reason', ['reason' => $order->cancel_reason]) }}
@elseif ($order->payment_status->value === 'unpaid')
{{ __('mail.cancelled.unpaid') }}
@endif

{{ __('mail.questions') }}

{{ __('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}
</x-mail::message>
