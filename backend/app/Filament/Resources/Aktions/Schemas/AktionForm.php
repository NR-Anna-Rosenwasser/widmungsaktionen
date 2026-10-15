<?php

namespace App\Filament\Resources\Aktions\Schemas;

use App\Enums\AktionStatus;
use App\Models\Aktion;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AktionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->helperText('z. B. «Herbst-Widmungsaktion 2026»')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Text im Shop (optional)')
                            ->rows(3),
                        Grid::make(2)->schema([
                            DateTimePicker::make('orders_open_at')
                                ->label('Bestellungen ab')
                                ->seconds(false)
                                ->required(),
                            DateTimePicker::make('order_deadline')
                                ->label('Bestellschluss')
                                ->seconds(false)
                                ->after('orders_open_at')
                                ->required(),
                            DatePicker::make('signing_date')
                                ->label('Widmungstag')
                                ->afterOrEqual('order_deadline')
                                ->required(),
                            DatePicker::make('estimated_shipping_date')
                                ->label('Voraussichtlicher Versand')
                                ->afterOrEqual('signing_date')
                                ->required(),
                        ]),
                        CheckboxList::make('books')
                            ->label('Bestellbare Bücher')
                            ->relationship(
                                'books',
                                'title',
                                fn (Builder $query) => $query->whereBelongsTo(filament()->getTenant())->where('is_active', true),
                            )
                            ->required()
                            ->columns(2),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(AktionStatus::class)
                            ->default(AktionStatus::Draft)
                            ->required()
                            ->helperText('«Geplant» öffnet automatisch zum Startdatum. Pro Shop kann nur eine Aktion offen sein.')
                            ->rule(fn (?Aktion $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                                $value = $value instanceof AktionStatus ? $value : AktionStatus::from($value);

                                $otherOpen = Aktion::query()
                                    ->whereBelongsTo(filament()->getTenant())
                                    ->where('status', AktionStatus::Open)
                                    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                    ->first();

                                if ($value === AktionStatus::Open && $otherOpen) {
                                    $fail("«{$otherOpen->name}» ist bereits offen. Bitte zuerst schliessen.");
                                }
                            }),
                        TextInput::make('capacity')
                            ->label('Max. Bücher (optional)')
                            ->helperText('Leer = unbegrenzt')
                            ->numeric()
                            ->minValue(1),
                    ]),
            ]);
    }
}
