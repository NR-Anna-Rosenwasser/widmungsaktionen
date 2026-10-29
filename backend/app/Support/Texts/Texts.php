<?php

namespace App\Support\Texts;

use App\Models\Aktion;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Resolves the editable texts (TextCatalog): the shop's override from
 * settings.texts.<locale>.<key>, else the default from lang/. An Aktion
 * adds optional extra texts in aktionen.texts.<locale>.<name>.
 *
 * Placeholders are written {name}. Values are always escaped, so customer
 * data (names, gift messages) can never add formatting or HTML.
 */
class Texts
{
    public function for(Tenant $tenant, ?Aktion $aktion, string $locale): TextSet
    {
        return new TextSet($this, $tenant, $aktion, $tenant->resolveLocale($locale));
    }

    public function default(string $key, string $locale): string
    {
        return (string) __($key, [], $locale);
    }

    public function override(Tenant $tenant, string $key, string $locale): ?string
    {
        $value = trim(str_replace("\r\n", "\n", (string) data_get($tenant->settings, "texts.$locale.$key")));

        return $value === '' ? null : $value;
    }

    public function raw(Tenant $tenant, string $key, string $locale): string
    {
        return $this->override($tenant, $key, $locale) ?? $this->default($key, $locale);
    }

    public function extra(?Aktion $aktion, string $name, string $locale): ?string
    {
        $value = trim(str_replace("\r\n", "\n", (string) data_get($aktion?->texts, "$locale.$name")));

        return $value === '' ? null : $value;
    }

    /**
     * Replace {placeholders} that have a value; others stay as they are.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fill(string $text, array $values, ?callable $escape = null): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($values, $escape) {
            if (! array_key_exists($m[1], $values)) {
                return $m[0];
            }

            return $escape ? $escape((string) $values[$m[1]]) : (string) $values[$m[1]];
        }, $text);
    }

    /**
     * Markdown to safe HTML: raw HTML in the text is escaped, unsafe links dropped.
     */
    public static function markdownToHtml(string $markdown): string
    {
        return trim(Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }

    /**
     * Escape a value for use inside Markdown (mails): HTML plus the characters
     * that start inline formatting.
     */
    public static function escapeMarkdown(string $value): string
    {
        return addcslashes(e($value, false), '\\`*_[]');
    }

    /**
     * Placeholders used in a text that the catalog does not offer for it.
     *
     * @return array<int, string>
     */
    public static function unknownPlaceholders(string $text, array $allowed): array
    {
        preg_match_all('/\{(\w+)\}/', $text, $m);

        return array_values(array_diff(array_unique($m[1]), $allowed));
    }

    /**
     * All storefront texts for the API, in the shop's languages:
     * locale => frontend key => text.
     * Markdown texts arrive as HTML. {shop} is filled in, other placeholders
     * are left for the storefront (values escaped there).
     *
     * @return array<string, array<string, string>>
     */
    public function storefront(Tenant $tenant): array
    {
        $result = [];

        foreach ($tenant->locales() as $locale) {
            foreach (TextCatalog::texts() as $key => $text) {
                if (! str_starts_with($key, 'storefront.')) {
                    continue;
                }

                $raw = $this->raw($tenant, $key, $locale);
                $result[$locale][Str::after($key, 'storefront.')] = TextCatalog::isMarkdown($text['kind'])
                    ? self::fill(self::markdownToHtml($raw), ['shop' => $tenant->name], e(...))
                    : self::fill($raw, ['shop' => $tenant->name]);
            }
        }

        return $result;
    }

    /**
     * An Aktion's extra texts as HTML for the API: locale => name => html|null.
     *
     * @param  array<int, string>  $names
     * @return array<string, array<string, string|null>>
     */
    public function storefrontExtras(Aktion $aktion, array $names): array
    {
        $result = [];

        foreach ($aktion->tenant->locales() as $locale) {
            foreach ($names as $name) {
                $extra = $this->extra($aktion, $name, $locale);
                $result[$locale][$name] = $extra === null
                    ? null
                    : self::fill(self::markdownToHtml($extra), ['shop' => $aktion->tenant->name], e(...));
            }
        }

        return $result;
    }
}
