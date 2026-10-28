<x-mail::button :url="$trackingUrl">
{{ $text('mail.view_order') }}
</x-mail::button>

{{ $text('mail.questions') }}

{{ $text('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}
