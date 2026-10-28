<?php

namespace App\Support\Texts;

use App\Models\Aktion;
use App\Models\Tenant;
use Illuminate\Support\HtmlString;

/**
 * The texts of one shop (and Aktion) in one language, for mail and PDF views:
 *
 *   {{ $text('mail.greeting', ['name' => $first]) }}       plain text (Blade escapes)
 *   {!! $text->markdown('mail.signed.intro') !!}           Markdown source for mails
 *   {!! $text->html('letter.intro') !!}                    rendered HTML for PDFs
 *   $text->extraMarkdown('mail_signed') / extraHtml('letter')   the Aktion's extra text or null
 */
class TextSet
{
    public function __construct(
        protected Texts $texts,
        public readonly Tenant $tenant,
        public readonly ?Aktion $aktion,
        public readonly string $locale,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function __invoke(string $key, array $values = []): string
    {
        return Texts::fill($this->texts->raw($this->tenant, $key, $this->locale), $this->values($values));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function markdown(string $key, array $values = []): string
    {
        return Texts::fill($this->texts->raw($this->tenant, $key, $this->locale), $this->values($values), Texts::escapeMarkdown(...));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function html(string $key, array $values = []): HtmlString
    {
        return $this->toHtml($this->texts->raw($this->tenant, $key, $this->locale), $values);
    }

    public function extraMarkdown(string $name): ?string
    {
        $extra = $this->texts->extra($this->aktion, $name, $this->locale);

        return $extra === null ? null : Texts::fill($extra, $this->values([]), Texts::escapeMarkdown(...));
    }

    public function extraHtml(string $name): ?HtmlString
    {
        $extra = $this->texts->extra($this->aktion, $name, $this->locale);

        return $extra === null ? null : $this->toHtml($extra, []);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function toHtml(string $markdown, array $values): HtmlString
    {
        return new HtmlString(Texts::fill(Texts::markdownToHtml($markdown), $this->values($values), e(...)));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function values(array $values): array
    {
        return ['shop' => $this->tenant->name, ...$values];
    }
}
