@php($chf = fn (int $rappen) => number_format($rappen / 100, 2, '.', "'"))
<x-mail::table>
| {{ __('mail.book') }} | {{ __('mail.dedication') }} | CHF |
|:--|:--|--:|
@foreach ($order->items as $item)
| {{ $item->book->title }} | {{ $item->signature_only || ! $item->recipient_name ? __('mail.signature_only') : $item->recipient_name }} | {{ $chf($item->unit_price) }} |
@endforeach
| {{ __('mail.shipping') }} | | {{ $chf($order->shipping_cost) }} |
| **{{ __('mail.total') }}** | | **{{ $chf($order->total) }}** |
</x-mail::table>

@if ($order->is_gift)
{{ __('mail.deliver_to', ['name' => $order->shippingName(), 'town' => $order->shipping_town]) }}
@endif

{{ __('mail.dates', ['signing' => $order->aktion->signing_date->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL'), 'shipping' => $order->aktion->estimated_shipping_date->locale(app()->getLocale() === 'en' ? 'en_CH' : 'de_CH')->isoFormat('LL')]) }}
