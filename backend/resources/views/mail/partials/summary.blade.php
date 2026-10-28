@php($chf = fn (int $rappen) => number_format($rappen / 100, 2, '.', "'"))
<x-mail::table>
| {{ $text('mail.book') }} | {{ $text('mail.dedication') }} | CHF |
|:--|:--|--:|
@foreach ($order->items as $item)
| {{ $item->book->title }} | {{ $item->signature_only || ! $item->recipient_name ? $text('mail.signature_only') : $item->recipient_name.($item->wish ? '' : ' · '.$text('mail.surprise')) }} | {{ $chf($item->unit_price) }} |
@endforeach
| {{ $text('mail.shipping') }} | | {{ $chf($order->shipping_cost) }} |
| **{{ $text('mail.total') }}** | | **{{ $chf($order->total) }}** |
</x-mail::table>

@if ($order->is_gift)
{{ $text('mail.deliver_to', ['name' => $order->shippingName(), 'town' => $order->shipping_town]) }}
@endif

{{ $text('mail.dates', ['signing' => $date($order->aktion->signing_date), 'shipping' => $date($order->aktion->estimated_shipping_date)]) }}
