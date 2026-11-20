<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\AktionStatus;
use App\Enums\Locale;
use App\Enums\PaymentMethod;
use App\Models\Aktion;
use App\Models\Book;
use App\Models\Order;
use App\Services\OrderPricer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Admin form for entering a new order or correcting a submitted one
 * (contact, addresses, dedications; books and prices stay as ordered).
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $creating = $schema->getOperation() === 'create';
        $tenant = filament()->getTenant();

        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    ...($creating ? [self::aktionSection()] : []),
                    self::itemsSection($creating),
                    Section::make('Rechnungsadresse')->columns(2)->schema(self::address('billing')),
                    Section::make('Geschenk')->columns(2)->schema([
                        Toggle::make('is_gift')
                            ->label('Als Geschenk / an jemand anderes senden')
                            ->live()
                            ->columnSpanFull()
                            ->disabled(fn (?Order $record) => $record?->payment_method === PaymentMethod::InvoiceInParcel && ! $record->is_gift)
                            ->helperText(fn (?Order $record) => $record?->payment_method === PaymentMethod::InvoiceInParcel && ! $record->is_gift
                                ? 'Nicht möglich mit «Rechnung im Paket».'
                                : 'Aus: Das Paket geht an die Rechnungsadresse.'),
                        Grid::make(2)->columnSpanFull()->visible(fn (Get $get) => (bool) $get('is_gift'))->schema([
                            ...self::address('shipping', required: fn (Get $get) => (bool) $get('is_gift')),
                            Textarea::make('gift_message')
                                ->label('Nachricht im Begleitbrief')
                                ->rows(3)
                                ->maxLength(500)
                                ->required(fn (Get $get) => (bool) $get('is_gift'))
                                ->columnSpanFull(),
                        ]),
                    ]),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Kontakt')->schema([
                        TextInput::make('email')->label('E-Mail')->email()->required()->maxLength(255),
                        TextInput::make('phone')->label('Telefon')->tel()->maxLength(255),
                        Select::make('locale')
                            ->label('Sprache')
                            ->options(collect($tenant->locales())->mapWithKeys(fn (string $locale) => [$locale => Locale::from($locale)->getLabel()]))
                            ->default($tenant->defaultLocale())
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),
                    ...($creating ? [self::paymentSection()] : []),
                ]),
            ]);
    }

    protected static function aktionSection(): Section
    {
        return Section::make('Aktion')->schema([
            Select::make('aktion_id')
                ->hiddenLabel()
                ->options(fn () => Aktion::query()
                    ->whereBelongsTo(filament()->getTenant())
                    ->whereNot('status', AktionStatus::Done)
                    ->orderByDesc('order_deadline')
                    ->get()
                    ->mapWithKeys(fn (Aktion $aktion) => [$aktion->id => $aktion->adminLabel()]))
                ->default(fn () => filament()->getTenant()->currentAktion()?->id)
                ->required()
                ->live()
                ->helperText(fn (Get $get) => self::capacityHint($get)),
        ]);
    }

    protected static function itemsSection(bool $creating): Section
    {
        $tenant = filament()->getTenant();

        $dedicationFields = [
            Toggle::make('signature_only')
                ->label('Nur Unterschrift')
                ->live()
                ->visible((bool) $tenant->setting('dedication_signature_only')),
            TextInput::make('recipient_name')
                ->label('Für')
                ->maxLength(100)
                ->hidden(fn (Get $get) => (bool) $get('signature_only')),
            Textarea::make('wish')
                ->label('Wunsch')
                ->rows(2)
                ->maxLength((int) $tenant->setting('dedication_max_length'))
                ->hidden(fn (Get $get) => (bool) $get('signature_only') || ! $tenant->setting('dedication_wish'))
                ->columnSpanFull(),
        ];

        $repeater = $creating
            ? Repeater::make('items')
                ->hiddenLabel()
                ->columns(2)
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel('Buch hinzufügen')
                ->reorderable(false)
                ->live()
                ->schema([
                    Select::make('book_id')
                        ->label('Buch')
                        ->options(fn (Get $get) => self::bookOptions($get('../../aktion_id')))
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    ...$dedicationFields,
                ])
            : Repeater::make('items')
                ->hiddenLabel()
                ->columns(2)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->itemLabel(fn (array $state) => ($state['title'] ?? 'Buch').(($state['signed'] ?? false) ? ' · gewidmet, nicht mehr änderbar' : ''))
                ->schema([
                    Hidden::make('id'),
                    Hidden::make('title'),
                    Hidden::make('signed'),
                    ...array_map(fn ($field) => $field->disabled(fn (Get $get) => (bool) $get('signed')), $dedicationFields),
                ]);

        return Section::make('Bücher & Widmungen')
            ->description($creating ? null : 'Bücher und Anzahl lassen sich nicht ändern. Sonst die Bestellung stornieren und neu erfassen.')
            ->schema([
                $repeater,
                Toggle::make('dedications_checked')
                    ->label('Widmungen sind geprüft')
                    ->helperText('An: gelten als freigegeben. Aus: geänderte Widmungen kommen in «Widmungswünsche prüfen».')
                    ->default(true),
            ]);
    }

    protected static function paymentSection(): Section
    {
        return Section::make('Zahlung')->schema([
            Select::make('payment_method')
                ->label('Zahlungsart')
                ->options(PaymentMethod::class)
                ->default(PaymentMethod::InvoiceAdvance->value)
                ->disableOptionWhen(fn (string $value, Get $get) => $value === PaymentMethod::InvoiceInParcel->value && $get('is_gift'))
                ->required()
                ->live(),
            TextInput::make('shipping_cost')
                ->label('Versandkosten (CHF)')
                ->numeric()
                ->minValue(0)
                ->step(0.05)
                ->live(onBlur: true)
                ->placeholder(fn (Get $get) => self::chf(self::tierPrice($get)))
                ->helperText('Leer = gemäss Versandtarif. Z. B. 0 bei einer Ersatzbestellung, deren Versand schon bezahlt ist.'),
            TextEntry::make('total_preview')
                ->label('Total')
                ->state(fn (Get $get) => 'CHF '.self::chf(self::total($get)))
                ->weight('bold'),
            Toggle::make('mark_paid')
                ->label('Bereits bezahlt')
                ->live(),
            DatePicker::make('paid_at')
                ->label('Zahlungseingang')
                ->default(now())
                ->maxDate(now())
                ->required(fn (Get $get) => (bool) $get('mark_paid'))
                ->visible(fn (Get $get) => (bool) $get('mark_paid')),
            TextInput::make('paid_note')
                ->label('Notiz (optional)')
                ->visible(fn (Get $get) => (bool) $get('mark_paid')),
            Toggle::make('add_to_stack')
                ->label('Direkt auf den Stapel')
                ->helperText('Bekommt die nächste Post-it-Nummer, sobald die Bestellung bestätigt ist.')
                ->default(true)
                ->visible(fn (Get $get) => (bool) Aktion::find($get('aktion_id'))?->prepared_at),
            Toggle::make('notify')
                ->label('Kund*in per E-Mail informieren')
                ->helperText('Bestellbestätigung: «bereits bezahlt», mit QR-Rechnung oder mit Link zur Online-Zahlung.')
                ->default(true),
        ]);
    }

    /**
     * @return array<int, TextInput|Select>
     */
    protected static function address(string $prefix, bool|\Closure $required = true): array
    {
        return [
            TextInput::make("{$prefix}_first_name")->label('Vorname')->required($required)->maxLength(100),
            TextInput::make("{$prefix}_last_name")->label('Nachname')->required($required)->maxLength(100),
            TextInput::make("{$prefix}_company")->label('Firma (optional)')->maxLength(100)->columnSpanFull(),
            TextInput::make("{$prefix}_street")->label('Strasse und Nr.')->required($required)->maxLength(100)->columnSpanFull(),
            TextInput::make("{$prefix}_postal_code")->label('PLZ')->required($required)->regex('/^\d{4}$/'),
            TextInput::make("{$prefix}_town")->label('Ort')->required($required)->maxLength(100),
            Select::make("{$prefix}_country")
                ->label('Land')
                ->options(['CH' => 'Schweiz', 'LI' => 'Liechtenstein'])
                ->default('CH')
                ->required($required)
                ->selectablePlaceholder(false),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function bookOptions(mixed $aktionId): array
    {
        $aktion = $aktionId ? Aktion::query()->whereBelongsTo(filament()->getTenant())->find($aktionId) : null;

        return $aktion
            ? $aktion->books()->where('is_active', true)->get()
                ->mapWithKeys(fn (Book $book) => [$book->id => "{$book->title} · CHF ".self::chf($aktion->priceFor($book))])
                ->all()
            : [];
    }

    protected static function tierPrice(Get $get): int
    {
        $copies = count(array_filter($get('items') ?? [], fn ($item) => filled($item['book_id'] ?? null)));

        return $copies ? (int) (app(OrderPricer::class)->shippingTier(filament()->getTenant(), $copies)['price'] ?? 0) : 0;
    }

    protected static function total(Get $get): int
    {
        $aktion = Aktion::find($get('aktion_id'));
        $books = $aktion?->books()->get()->keyBy('id');
        $subtotal = collect($get('items') ?? [])
            ->sum(fn ($item) => ($book = $books?->get($item['book_id'] ?? null)) ? $aktion->priceFor($book) : 0);
        $shipping = filled($get('shipping_cost')) ? (int) round((float) $get('shipping_cost') * 100) : self::tierPrice($get);

        return $subtotal + $shipping;
    }

    protected static function capacityHint(Get $get): ?string
    {
        $remaining = Aktion::find($get('aktion_id'))?->remainingCapacity();
        $copies = count($get('items') ?? []);

        return $remaining !== null && $copies > $remaining
            ? "Achtung: In dieser Aktion sind nur noch {$remaining} Bücher frei. Die Bestellung wird trotzdem erfasst."
            : null;
    }

    protected static function chf(int $rappen): string
    {
        return number_format($rappen / 100, 2, '.', '');
    }
}
