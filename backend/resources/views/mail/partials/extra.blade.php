{{-- The Aktion's optional extra text for this mail. --}}
@if ($extra = $text->extraMarkdown($name))

{!! $extra !!}

@endif
