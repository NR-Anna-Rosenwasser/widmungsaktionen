<?php

namespace App\Livewire\Versand;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Filament\Actions\OrderActions;
use App\Filament\Pages\Versand;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Aktion;
use App\Models\Order;
use App\Services\SigningService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use RuntimeException;

/**
 * «Vorbereiten & Versand»: every order of the Aktion that isn't shipped or
 * cancelled yet, with the reason and what can be done about it. Before the
 * stack is prepared, only unpaid orders (the rest is waiting for the stack).
 */
class OpenOrdersTable extends TableWidget
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

    /**
     * Why an order is still open: label, colour and the matching query.
     *
     * @return array<string, array{label: string, color: string, query: callable(Builder): void}>
     */
    public static function reasons(): array
    {
        $notApproved = fn (Builder $q) => $q->where('moderation_status', '!=', ModerationStatus::Approved);

        return [
            'unpaid' => ['label' => 'Unbezahlt', 'color' => 'danger',
                'query' => fn (Builder $q) => $q->where('status', OrderStatus::PendingPayment)],
            'unstacked' => ['label' => 'Bezahlt, noch kein Post-it', 'color' => 'warning',
                'query' => fn (Builder $q) => $q->where('status', OrderStatus::Confirmed)->whereNull('stack_number')],
            'query' => ['label' => 'Rückfrage offen', 'color' => 'warning',
                'query' => fn (Builder $q) => $q->where('status', OrderStatus::Confirmed)->whereNotNull('stack_number')->whereHas('items', $notApproved)],
            'unsigned' => ['label' => 'Noch nicht fertig gewidmet', 'color' => 'gray',
                'query' => fn (Builder $q) => $q->where('status', OrderStatus::Confirmed)->whereNotNull('stack_number')->whereDoesntHave('items', $notApproved)],
            'unshipped' => ['label' => 'Gewidmet, nicht versendet', 'color' => 'primary',
                'query' => fn (Builder $q) => $q->where('status', OrderStatus::Signed)],
        ];
    }

    public static function reasonOf(Order $order): string
    {
        return match (true) {
            $order->status === OrderStatus::PendingPayment => 'unpaid',
            $order->status === OrderStatus::Signed => 'unshipped',
            $order->stack_number === null => 'unstacked',
            $order->items->contains(fn ($item) => $item->moderation_status !== ModerationStatus::Approved) => 'query',
            default => 'unsigned',
        };
    }

    public function table(Table $table): Table
    {
        $refresh = fn () => $this->dispatch('orders-updated');
        $prepared = (bool) $this->aktion()->prepared_at;

        return $table
            ->heading('Offene Bestellungen')
            ->description($prepared
                ? 'Alles, was noch nicht versendet oder storniert ist. Entscheide pro Bestellung: auf den Stapel, bezahlt markieren, verschieben, stornieren oder zurückerstatten.'
                : 'Unbezahlte Bestellungen nach dem Bestellschluss. Spät eingegangene Zahlungen jetzt als bezahlt markieren, dann kommen sie beim Vorbereiten auf den Stapel.')
            ->query(fn () => $this->aktion()->orders()
                ->getQuery()
                ->when(
                    $prepared,
                    fn (Builder $q) => $q->whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Confirmed, OrderStatus::Signed]),
                    fn (Builder $q) => $q->where('status', OrderStatus::PendingPayment),
                )
                ->with('items', 'aktion'))
            ->defaultSort('submitted_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('number')
                    ->label('Bestellung')
                    ->description(fn (Order $record) => $record->billingName())
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
                    ->searchable(['number', 'billing_first_name', 'billing_last_name']),
                TextColumn::make('reason')
                    ->label('Offen, weil')
                    ->state(fn (Order $record) => self::reasons()[self::reasonOf($record)]['label'])
                    ->badge()
                    ->color(fn (Order $record) => self::reasons()[self::reasonOf($record)]['color'])
                    ->description(fn (Order $record) => $record->moved_count > 0 ? 'Schon einmal verschoben' : null),
                TextColumn::make('stack_number')->label('Post-it')->formatStateUsing(fn (?int $state) => $state ? "#{$state}" : null)->placeholder('–'),
                TextColumn::make('payment_method')
                    ->label('Zahlung')
                    ->description(fn (Order $record) => 'CHF '.number_format($record->total / 100, 2, '.', '')),
                TextColumn::make('submitted_at')->label('Bestellt')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label('Offen, weil')
                    ->options(collect(self::reasons())->map(fn (array $reason) => $reason['label'])->all())
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? (self::reasons()[$data['value']]['query'])($query)
                        : $query),
            ])
            ->recordActions([
                Action::make('addToStack')
                    ->label('Auf den Stapel')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->color('primary')
                    ->visible(fn (Order $record) => $prepared && $record->status === OrderStatus::Confirmed && $record->stack_number === null)
                    ->requiresConfirmation()
                    ->modalDescription('Die Bestellung bekommt die nächste Post-it-Nummer und kommt ans Ende des Stapels.')
                    ->action(function (Order $record) use ($refresh) {
                        try {
                            $number = app(SigningService::class)->addToStack($record, auth()->user());
                            Notification::make()->title("Auf dem Stapel: Post-it #{$number}")->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                        $refresh();
                    }),
                OrderActions::markPaid()->after($refresh),
                ActionGroup::make([
                    OrderActions::move()->after($refresh),
                    OrderActions::refund()->after($refresh),
                    OrderActions::cancel()->after($refresh),
                ]),
            ])
            ->toolbarActions([
                OrderActions::moveBulk($this->aktion())->after($refresh),
                OrderActions::cancelBulk()->after($refresh),
            ])
            ->emptyStateHeading('Nichts offen')
            ->emptyStateDescription($prepared ? 'Alle Bestellungen dieser Aktion sind versendet oder storniert.' : 'Keine unbezahlten Bestellungen.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
