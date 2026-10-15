<x-mail::button :url="$trackingUrl">
{{ __('mail.view_order') }}
</x-mail::button>

{{ __('mail.questions') }}

{{ __('mail.signature') }}<br>
{{ $tenant->mail_from_name ?: $tenant->name }}
