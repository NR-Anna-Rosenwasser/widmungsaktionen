<?php

namespace App\Filament\Pages;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\TenantRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Aktion;
use App\Models\OrderItem;
use App\Services\ModerationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Review what customers asked to have written in their books.
 */
class Moderation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'widmungswuensche';

    protected static ?string $title = 'Widmungswünsche';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Widmen';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()?->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Widmen]);
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = filament()->getTenant();
        if (! $tenant) {
            return null;
        }

        $count = OrderItem::query()
            ->activeForTenant($tenant)
            ->where('moderation_status', ModerationStatus::Pending)
            ->count();

        return $count ?: null;
    }

    public function getSubheading(): ?string
    {
        return 'Alles, was Kund*innen für ihre Widmung geschrieben haben. Freigeben, anpassen oder für eine Rückfrage markieren. «Widmungsaktion vorbereiten» geht erst, wenn alles geprüft ist. Wünsche mit Rückfrage kommen ans Ende des Stapels und werden gewidmet, sobald die Rückfrage geklärt ist.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')
                ->label('Einzeln prüfen')
                ->icon(Heroicon::OutlinedPlayCircle)
                ->url(ModerationReview::getUrl())
                ->visible(fn () => (bool) static::getNavigationBadge()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $tenant = filament()->getTenant();

        return $table
            ->query(OrderItem::query()
                ->activeForTenant($tenant)
                ->withDedication()
                ->with('order', 'book'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('order.number')
                    ->label('Bestellung')
                    ->url(fn (OrderItem $record) => OrderResource::getUrl('view', ['record' => $record->order_id]))
                    ->description(fn (OrderItem $record) => $record->order->billingName()),
                TextColumn::make('book.title')->label('Buch'),
                TextColumn::make('recipient_name')->label('Für')->placeholder('–')->weight('bold'),
                TextColumn::make('wish')->label('Wunsch')->placeholder('–')->wrap()->grow(),
                TextColumn::make('moderation_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ModerationStatus $state) => match ($state) {
                        ModerationStatus::Approved => 'success',
                        ModerationStatus::Flagged => 'danger',
                        ModerationStatus::Pending => 'warning',
                    })
                    ->description(fn (OrderItem $record) => $record->moderation_note),
            ])
            ->filters([
                SelectFilter::make('moderation_status')
                    ->label('Status')
                    ->options(ModerationStatus::class)
                    ->default(ModerationStatus::Pending->value),
                SelectFilter::make('aktion')
                    ->label('Aktion')
                    ->options(fn () => Aktion::query()
                        ->whereBelongsTo($tenant)
                        ->whereNot('status', AktionStatus::Done)
                        ->orderByDesc('order_deadline')
                        ->get()
                        ->mapWithKeys(fn (Aktion $aktion) => [$aktion->id => $aktion->adminLabel()]))
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->whereHas('order', fn ($q) => $q->where('aktion_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Freigeben')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->hidden(fn (OrderItem $record) => $record->moderation_status === ModerationStatus::Approved)
                    ->action(fn (OrderItem $record) => $this->approve($record)),
                Action::make('edit')
                    ->label('Anpassen')
                    ->icon(Heroicon::OutlinedPencil)
                    ->fillForm(fn (OrderItem $record) => $record->only('recipient_name', 'wish'))
                    ->schema([
                        TextInput::make('recipient_name')->label('Für')->maxLength(100),
                        Textarea::make('wish')->label('Wunsch')->rows(3)
                            ->maxLength(fn () => (int) $tenant->setting('dedication_max_length')),
                    ])
                    ->modalDescription('Die Änderung wird gespeichert und gleich freigegeben. Die Kund*in sieht die angepasste Widmung auf ihrer Bestellseite.')
                    ->action(function (OrderItem $record, array $data) {
                        app(ModerationService::class)->adjust($record, $data, auth()->user());
                        Notification::make()->title('Angepasst und freigegeben')->success()->send();
                    }),
                Action::make('flag')
                    ->label('Rückfrage')
                    ->icon(Heroicon::OutlinedFlag)
                    ->color('danger')
                    ->hidden(fn (OrderItem $record) => $record->moderation_status === ModerationStatus::Flagged)
                    ->schema([
                        Textarea::make('note')->label('Notiz (intern)')->rows(2)->placeholder('z. B. bei Kund*in nachfragen, was gemeint ist'),
                    ])
                    ->action(function (OrderItem $record, array $data) {
                        app(ModerationService::class)->flag($record, auth()->user(), $data['note'] ?? null);
                        Notification::make()->title('Für Rückfrage markiert')->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('approveAll')
                    ->label('Ausgewählte freigeben')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(fn (OrderItem $record) => $this->approve($record, notify: false));
                        Notification::make()->title($records->count().' freigegeben')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Nichts zu prüfen')
            ->emptyStateDescription('Alle Widmungswünsche in dieser Auswahl sind erledigt.')
            ->emptyStateIcon(Heroicon::OutlinedSparkles);
    }

    protected function approve(OrderItem $record, bool $notify = true): void
    {
        app(ModerationService::class)->approve($record, auth()->user());

        if ($notify) {
            Notification::make()->title('Freigegeben')->success()->send();
        }
    }
}
