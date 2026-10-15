<?php

namespace App\Filament\Schemas;

use App\Enums\PaymentMethod;
use App\Models\Tenant;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * Field groups shared by the "Neuer Shop" wizard and the tenant settings page.
 */
class TenantSettingsForm
{
    public static function general(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->label('Name des Shops')
                    ->helperText('z. B. «Anna Rosenwasser»')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, ?Tenant $record) {
                        if (! $record?->exists) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->helperText('Teil der URL: widmungen.rose-water.ch/<slug>')
                    ->required()
                    ->alphaDash()
                    ->maxLength(60)
                    ->unique(Tenant::class, 'slug', ignoreRecord: true)
                    ->notIn(config('widmungen.reserved_slugs')),
                TextInput::make('order_prefix')
                    ->label('Präfix für Bestellnummern')
                    ->helperText('z. B. «AR» → AR-2026-0042')
                    ->required()
                    ->regex('/^[A-Z0-9]{1,8}$/')
                    ->maxLength(8),
                TextInput::make('contact_email')
                    ->label('Kontakt-E-Mail')
                    ->helperText('Für Fragen und Bestellungen ab 5 Büchern')
                    ->email()
                    ->required(),
                Toggle::make('is_active')
                    ->label('Shop aktiv')
                    ->default(true),
            ]),
        ];
    }

    public static function seller(): array
    {
        return [
            Textarea::make('letterhead')
                ->label('Absender (Briefkopf)')
                ->helperText('Erscheint so auf Begleitbriefen und Rechnungen.')
                ->rows(4)
                ->required(),
            Grid::make(6)->schema([
                TextInput::make('creditor_name')
                    ->label('Zahlungsempfänger*in (QR-Rechnung)')
                    ->required()
                    ->maxLength(70)
                    ->columnSpan(6),
                TextInput::make('creditor_street')->label('Strasse')->required()->maxLength(70)->columnSpan(4),
                TextInput::make('creditor_building_number')->label('Nr.')->maxLength(16)->columnSpan(2),
                TextInput::make('creditor_postal_code')->label('PLZ')->required()->maxLength(16)->columnSpan(2),
                TextInput::make('creditor_town')->label('Ort')->required()->maxLength(35)->columnSpan(3),
                Select::make('creditor_country')->label('Land')->options(['CH' => 'Schweiz', 'LI' => 'Liechtenstein'])->default('CH')->required()->columnSpan(1),
                TextInput::make('iban')
                    ->label('IBAN oder QR-IBAN')
                    ->helperText('Mit QR-IBAN werden QR-Referenzen verwendet, sonst SCOR-Referenzen.')
                    ->regex('/^(CH|LI)\d{2}\s?([0-9A-Z]{4}\s?){4}[0-9A-Z]$/i')
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(preg_replace('/\s+/', '', $state)) : null)
                    ->columnSpan(6),
            ]),
        ];
    }

    public static function mail(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('mail_from_name')->label('Absendername')->maxLength(255),
                TextInput::make('mail_from_address')->label('Absenderadresse')->email()
                    ->helperText('Muss bei Mailgun für die Domain freigeschaltet sein.'),
                TextInput::make('mail_reply_to')->label('Antwort an')->email(),
            ]),
        ];
    }

    public static function stripe(): array
    {
        return [
            TextInput::make('stripe_publishable_key')
                ->label('Publishable key')
                ->startsWith(['pk_']),
            self::secret('stripe_secret_key', 'Secret key')->startsWith(['sk_', 'rk_']),
            self::secret('stripe_webhook_secret', 'Webhook signing secret')->startsWith(['whsec_']),
        ];
    }

    public static function newsletter(): array
    {
        return [
            TextInput::make('newsletter_webhook_url')
                ->label('Newsletter-Webhook (z. B. n8n)')
                ->url(),
            self::secret('newsletter_webhook_secret', 'Webhook-Secret (HMAC)'),
        ];
    }

    public static function ordering(): array
    {
        return [
            CheckboxList::make('settings.payment_methods')
                ->label('Zahlungsarten')
                ->options(PaymentMethod::class)
                ->required(),
            Grid::make(3)->schema([
                TextInput::make('settings.max_copies_per_order')
                    ->label('Max. Bücher pro Bestellung')
                    ->numeric()->minValue(1)->maxValue(20)->required(),
                TextInput::make('settings.dedication_max_length')
                    ->label('Max. Zeichen pro Widmungswunsch')
                    ->numeric()->minValue(20)->maxValue(1000)->required(),
                TextInput::make('settings.invoice_payment_days')
                    ->label('Zahlungsfrist Rechnung (Tage)')
                    ->numeric()->minValue(1)->required(),
                TextInput::make('settings.checkpoint_interval')
                    ->label('Kontrollfrage beim Widmen alle … Bücher')
                    ->numeric()->minValue(1)->maxValue(50)->required(),
                TextInput::make('settings.dedication_retention_months')
                    ->label('Widmungstexte anonymisieren nach … Monaten')
                    ->helperText('0 = nie')
                    ->numeric()->minValue(0)->required(),
            ]),
            Repeater::make('settings.shipping_tiers')
                ->label('Versandkosten')
                ->helperText('Die erste Stufe, deren Maximum die Anzahl Bücher abdeckt, wird verwendet.')
                ->schema([
                    TextInput::make('max_copies')->label('Bis Anzahl Bücher')->numeric()->minValue(1)->required(),
                    Select::make('method')->label('Versandart')->options([
                        'letter' => 'Brief',
                        'parcel' => 'Paket',
                    ])->required(),
                    self::money('price', 'Preis (CHF)'),
                ])
                ->columns(3)
                ->minItems(1)
                ->reorderable(false),
        ];
    }

    public static function legal(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('settings.legal.terms_url')->label('AGB (URL)')->url(),
                TextInput::make('settings.legal.privacy_url')->label('Datenschutz (URL)')->url(),
                TextInput::make('settings.legal.imprint_url')->label('Impressum (URL)')->url(),
            ]),
            Textarea::make('settings.branding.intro')
                ->label('Einleitungstext im Shop')
                ->rows(3),
        ];
    }

    /**
     * Merge stored settings over the defaults so every field has a value.
     */
    public static function fillDefaults(array $data): array
    {
        $data['settings'] = array_replace_recursive(
            config('widmungen.tenant_defaults'),
            $data['settings'] ?? [],
        );

        // Lists must not be merged index by index.
        foreach (['payment_methods', 'shipping_tiers'] as $list) {
            $data['settings'][$list] = $data['settings'][$list] ?? config("widmungen.tenant_defaults.$list");
        }

        return $data;
    }

    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->step(0.05)
            ->required()
            ->formatStateUsing(fn ($state) => $state === null ? null : number_format($state / 100, 2, '.', ''))
            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100));
    }

    protected static function secret(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->password()
            ->revealable()
            ->placeholder(fn (?Tenant $record) => $record?->getRawOriginal($name) ? '•••••••• (gesetzt, leer lassen zum Beibehalten)' : null)
            ->dehydrated(fn (?string $state) => filled($state));
    }
}
