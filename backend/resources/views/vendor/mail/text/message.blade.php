@props(['shopName' => null, 'shopUrl' => null, 'footerText' => null])
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$shopUrl ?? config('app.url')">
{{ $shopName ?? config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ $shopName ?? config('app.name') }} · {{ $footerText ?? __('mail.footer') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
