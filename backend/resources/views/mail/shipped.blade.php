@php($shipment = $order->shipments()->latest('shipped_at')->first())
<x-mail::message :shop-name="$shopName" :shop-url="$shopUrl">
# {{ __('mail.greeting', ['name' => $order->billing_first_name]) }}

{{ $order->is_gift ? __('mail.shipped.intro_gift', ['name' => $order->shippingName()]) : __('mail.shipped.intro') }}

@if ($shipment)
{{ __('mail.shipped.'.($shipment->method === 'letter' ? 'letter' : 'parcel')) }}
@if ($shipment->tracking_number)

{{ __('mail.shipped.tracking', ['number' => $shipment->tracking_number]) }}
@endif
@endif

@if ($order->payment_method?->value === 'invoice_in_parcel' && $order->payment_status->value === 'unpaid')
{{ __('mail.shipped.invoice_in_parcel') }}
@endif

@include('mail.partials.closing')
</x-mail::message>
