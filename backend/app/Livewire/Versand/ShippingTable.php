<?php

namespace App\Livewire\Versand;

use App\Enums\OrderStatus;
use App\Filament\Pages\Versand;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Aktion;
use App\Models\Order;
use App\Services\Pdf\PrintService;
use App\Services\ShippingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * «Vorbereiten & Versand»: the stack in post-it order, to mark orders as
 * shipped one by one or in bulk (select all, untick the exceptions).
 */
class ShippingTable extends TableWidget
{
    #[Locked]
    public int $aktionId;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Versand::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canView(), 403);
    }

    #[On('orders-updated')]
    public function refreshTable(): void {}

    protected function aktion(): Aktion
    {
        return Aktion::query()->whereBelongsTo(filament()->getTenant())->findOrFail($this->aktionId);
    }

    public function table(Table $table): Table
    {
        $shipping = app(ShippingService::class);
        $print = app(PrintService::class);

        return $table
            ->heading('Versand')
            ->description('Gewidmete Bestellungen als versendet markieren: alle auswählen, Ausnahmen abwählen. Kund*innen bekommen eine E-Mail (abschaltbar).')
            ->query(fn () => $this->aktion()->orders()
                ->getQuery()
                ->whereNotNull('stack_number')
                ->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled])
                ->with('items.book', 'shipments'))
            ->defaultSort('stack_number')
            ->paginated(false)
            ->columns([
                TextColumn::make('stack_number')->label('Post-it')->formatStateUsing(fn (int $state) => "#{$state}")->weight('bold')->sortable(),
                TextColumn::make('number')
                    ->label('Bestellung')
                    ->description(fn (Order $record) => $record->billingName())
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
                    ->searchable(['number', 'billing_first_name', 'billing_last_name']),
                TextColumn::make('books')
                    ->label('Bücher')
                    ->state(fn (Order $record) => $record->items->countBy(fn ($item) => $item->book->title)->map(fn ($n, $title) => "{$n}× {$title}")->values()->all())
                    ->listWithLineBreaks(),
                TextColumn::make('method')
                    ->label('Versand')
                    ->state(fn (Order $record) => $shipping->methodFor($record) === 'letter' ? 'Brief' : 'Paket')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('invoice')
                    ->label('Rechnung')
                    ->state(fn (Order $record) => $print->needsInvoicePage($record) ? 'beilegen' : null)
                    ->badge()
                    ->color('warning')
                    ->placeholder('–'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Signed => 'primary',
                        OrderStatus::Shipped => 'success',
                        default => 'gray',
                    })
                    ->description(fn (Order $record) => $record->shipments->first()?->tracking_number),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        OrderStatus::Signed->value => 'Gewidmet, nicht versendet',
                        OrderStatus::Confirmed->value => 'Noch nicht fertig gewidmet',
                        OrderStatus::Shipped->value => 'Versendet',
                    ])
                    ->default(OrderStatus::Signed->value),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Order $record) => $record->status === OrderStatus::Signed)
            ->recordActions([
                Action::make('ship')
                    ->label('Versenden')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status === OrderStatus::Signed)
                    ->modalHeading(fn (Order $record) => "#{$record->stack_number} versenden")
                    ->schema([
                        DatePicker::make('date')->label('Versanddatum')->default(now())->required(),
                        TextInput::make('tracking_number')->label('Sendungsnummer (optional)'),
                        Toggle::make('notify')->label('Kund*in per E-Mail informieren')->default(true),
                    ])
                    ->action(function (Order $record, array $data) use ($shipping) {
                        $shipping->ship($record, Carbon::parse($data['date']), auth()->user(), $data['tracking_number'] ?? null, notify: $data['notify']);
                        $shipping->syncAktionStatus($record->aktion);
                        Notification::make()->title("#{$record->stack_number} versendet")->success()->send();
                        $this->dispatch('orders-updated');
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('shipSelected')
                    ->label('Als versendet markieren')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Collection $records) => $records->count().' Bestellungen werden als versendet markiert.')
                    ->schema([
                        DatePicker::make('date')->label('Versanddatum')->default(now())->required(),
                        Toggle::make('notify')->label('Kund*innen per E-Mail informieren')->default(true),
                    ])
                    ->action(function (Collection $records, array $data) use ($shipping) {
                        $count = $shipping->shipMany($records, Carbon::parse($data['date']), auth()->user(), $data['notify']);
                        Notification::make()->title("{$count} Bestellungen versendet")->success()->send();
                        $this->dispatch('orders-updated');
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nichts in dieser Auswahl')
            ->emptyStateDescription('Sobald Bestellungen gewidmet sind, erscheinen sie hier zum Versenden.')
            ->emptyStateIcon(Heroicon::OutlinedTruck);
    }
}
