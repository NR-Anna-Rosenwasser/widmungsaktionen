<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Languages the platform offers. Each shop picks a subset and a default
 * (settings.locales, settings.default_locale, see Tenant::locales()).
 */
enum Locale: string implements HasLabel
{
    case De = 'de';
    case Fr = 'fr';
    case It = 'it';
    case En = 'en';

    public function getLabel(): string
    {
        return match ($this) {
            self::De => 'Deutsch',
            self::Fr => 'Français',
            self::It => 'Italiano',
            self::En => 'English',
        };
    }

    /** Carbon locale for dates, e.g. «15. Oktober 2026». */
    public function carbon(): string
    {
        return $this->value.'_CH';
    }

    /** BCP 47 tag for lang attributes. */
    public function tag(): string
    {
        return $this === self::En ? 'en' : $this->value.'-CH';
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
