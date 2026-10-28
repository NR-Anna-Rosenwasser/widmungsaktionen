@php($shipment = $order->shipments()->latest('shipped_at')->first())
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl" :footer-text="$text('mail.footer')">
# {{ $text('mail.greeting', ['name' => $order->billing_first_name]) }}

{!! $order->is_gift ? $text->markdown('mail.shipped.intro_gift', ['name' => $order->shippingName()]) : $text->markdown('mail.shipped.intro') !!}

@if ($shipment)
{!! $text->markdown('mail.shipped.'.($shipment->method === 'letter' ? 'letter' : 'parcel')) !!}
@if ($shipment->tracking_number)

{{ $text('mail.shipped.tracking', ['number' => $shipment->tracking_number]) }}
@endif
@endif

@if ($order->payment_method?->value === 'invoice_in_parcel' && $order->payment_status->value === 'unpaid')
{!! $text->markdown('mail.shipped.invoice_in_parcel') !!}
@endif

@include('mail.partials.extra', ['name' => 'mail_shipped'])

@include('mail.partials.closing')
</x-mail::message>
