<?php

namespace App\Filament\Resources\Aktions\Tables;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Aktions\Pages\EditAktion;
use App\Models\Aktion;
use App\Services\TestMode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AktionsTable
{
    /**
     * Copy an Aktion with its texts into a new test Aktion (never a live one).
     */
    public static function duplicateAsTestAction(): Action
    {
        return Action::make('duplicateAsTest')
            ->label('Als Testaktion duplizieren')
            ->icon(Heroicon::OutlinedBeaker)
            ->iconButton()
            ->tooltip('Als Testaktion duplizieren')
            ->authorize(fn () => auth()->user()->can('create', Aktion::class))
            ->modalHeading('Als Testaktion duplizieren')
            ->modalDescription('Übernimmt Daten, Bücher mit Preisen, Kapazität und Zusatztexte in eine neue Aktion im Testmodus. Die neue Aktion startet als Entwurf: Status und Daten anpassen, dann den geheimen Link öffnen.')
            ->fillForm(fn (Aktion $record) => ['name' => "{$record->name} (Kopie)"])
            ->schema([TextInput::make('name')->label('Name')->required()->maxLength(255)])
            ->modalSubmitActionLabel('Duplizieren')
            ->action(function (array $data, Aktion $record, TestMode $testMode) {
                // Fresh record: the table query adds counts that must not be copied.
                $copy = $testMode->duplicate(Aktion::query()->findOrFail($record->getKey()), $data['name']);
                Notification::make()->title('Testaktion erstellt')->success()->send();

                return redirect(EditAktion::getUrl(['record' => $copy]));
            });
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orders_open_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'orders as submitted_orders_count' => fn ($q) => $q->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]),
            ]))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()
                    ->description(fn (Aktion $record) => $record->is_test ? 'Testmodus' : null),
                TextColumn::make('status')->label('Status')->badge()->color(fn (AktionStatus $state) => match ($state) {
                    AktionStatus::Open => 'success',
                    AktionStatus::Scheduled => 'info',
                    AktionStatus::Closed, AktionStatus::Signing, AktionStatus::Shipping => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('order_deadline')->label('Bestellschluss')->dateTime('d.m.Y H:i'),
                TextColumn::make('signing_date')->label('Widmungstag')->date('d.m.Y'),
                TextColumn::make('estimated_shipping_date')->label('Versand ca.')->date('d.m.Y'),
                TextColumn::make('submitted_orders_count')->label('Bestellungen'),
            ])
            ->recordActions([
                EditAktion::prepareAction()->iconButton()->tooltip('Widmen vorbereiten'),
                self::duplicateAsTestAction(),
                EditAction::make(),
            ]);
    }
}
