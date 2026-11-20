<?php

namespace App\Filament\Pages;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Enums\TenantRole;
use App\Filament\Exports\OrderExporter;
use App\Livewire\Versand\OpenOrdersTable;
use App\Livewire\Versand\ShippingTable;
use App\Models\Aktion;
use App\Services\Pdf\PrintService;
use App\Services\SigningService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Everything after the order deadline: prepare the stack (post-it numbers),
 * print documents and mark orders as shipped.
 */
class Versand extends Page
{
    protected static ?string $slug = 'vorbereiten-versand';

    protected static ?string $title = 'Vorbereiten & Versand';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|UnitEnum|null $navigationGroup = 'Widmen';

    protected static ?int $navigationSort = 2;

    #[Url]
    public ?int $aktion = null;

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()?->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    public function mount(): void
    {
        $this->aktion ??= filament()->getTenant()->currentAktion()?->id;
    }

    /**
     * @return Collection<int, Aktion>
     */
    protected function aktionen(): Collection
    {
        return Aktion::query()
            ->whereBelongsTo(filament()->getTenant())
            ->whereNotIn('status', [AktionStatus::Draft])
            ->orderByDesc('signing_date')
            ->get();
    }

    #[Computed]
    public function selected(): ?Aktion
    {
        return $this->aktion
            ? Aktion::query()->whereBelongsTo(filament()->getTenant())->find($this->aktion)
            : null;
    }

    protected function print(): PrintService
    {
        return app(PrintService::class);
    }

    public function content(Schema $schema): Schema
    {
        $aktion = $this->selected;
        $mode = filament()->getTenant()->setting('print.envelope_mode');

        if (! $aktion) {
            return $schema->components([Text::make('Noch keine Aktion vorhanden.')]);
        }

        if (! $aktion->prepared_at) {
            return $schema->components([
                Actions::make([$this->chooseAktionAction()])->key('aktionActions'),
                $this->prepareSection($aktion),
                // After the deadline: record late payments before preparing.
                Livewire::make(OpenOrdersTable::class, ['aktionId' => $aktion->id])
                    ->key("open-{$aktion->id}")
                    ->visible(app(SigningService::class)->readiness($aktion)['deadline_passed']),
            ]);
        }

        $orders = $this->print()->orders($aktion);
        $count = fn (OrderStatus $status) => $orders->where('status', $status)->count();

        return $schema->components([
            Actions::make([$this->chooseAktionAction()])->key('aktionActions'),

            Section::make('Übersicht')
                ->description("Widmungstag {$aktion->signing_date->format('d.m.Y')} · Versand ca. {$aktion->estimated_shipping_date->format('d.m.Y')}")
                ->schema([
                    Grid::make(4)->schema([
                        Text::make("{$orders->count()} auf dem Stapel"),
                        Text::make($count(OrderStatus::Confirmed).' noch zu widmen'),
                        Text::make($count(OrderStatus::Signed).' gewidmet, bereit zum Versand'),
                        Text::make($count(OrderStatus::Shipped).' versendet'),
                    ]),
                    Text::make('Bücher auf dem Stapel: '.app(SigningService::class)->bookCountsLine($orders))
                        ->visible($orders->isNotEmpty()),
                    Text::make('Stapelliste: zum Beschriften der Post-its und zum Abhaken beim Vorbereiten und Verpacken.'),
                    Actions::make([
                        $this->downloadAction('stackList', 'Stapelliste (PDF)', Heroicon::OutlinedQueueList),
                        $this->exportAction(),
                        $this->unprepareAction(),
                    ])->key('prepareActions'),
                ]),

            Section::make('1. Briefe & Adressen')
                ->description(match ($mode) {
                    'window_right', 'window_left' => 'Fenstercouverts: Die Adresse steht im Begleitbrief. Etiketten braucht es nur für Pakete.',
                    'direct_print' => 'Briefe: Couverts C5 direkt bedrucken. Pakete bekommen Etiketten.',
                    default => 'Normale Couverts: Jede Sendung bekommt eine Adressetikette.',
                }.' Einstellungen unter «Einstellungen → Drucken».')
                ->schema([Actions::make(array_values(array_filter([
                    $this->downloadAction('letters', 'Begleitbriefe (PDF)', Heroicon::OutlinedEnvelopeOpen),
                    $this->labelsAction(),
                    $mode === 'direct_print' ? $this->downloadAction('envelopes', 'Couverts C5 (PDF)', Heroicon::OutlinedEnvelope) : null,
                ])))->key('printActions')]),

            Livewire::make(ShippingTable::class, ['aktionId' => $aktion->id])->key("shipping-{$aktion->id}"),
            Livewire::make(OpenOrdersTable::class, ['aktionId' => $aktion->id])->key("open-{$aktion->id}"),
        ]);
    }

    /**
     * Before the stack exists: what's still open, the books to prepare, and the button.
     */
    protected function prepareSection(Aktion $aktion): Section
    {
        $signing = app(SigningService::class);
        $r = $signing->readiness($aktion);
        $unstacked = $signing->unstacked($aktion);

        $lines = [
            $r['deadline_passed']
                ? '✓ Bestellschluss vorbei'.($aktion->is_test ? ' (Testaktion: jederzeit möglich)' : '')
                : "✗ Bestellschluss ist erst am {$aktion->order_deadline->format('d.m.Y H:i')}",
            $r['pending_moderation'] > 0
                ? "✗ {$r['pending_moderation']} Widmungswünsche sind noch nicht geprüft"
                : '✓ Alle Widmungswünsche geprüft',
            ...($r['flagged'] > 0 ? ["• {$r['flagged']} Wünsche mit Rückfrage: Diese Bestellungen bekommen die letzten Post-it-Nummern."] : []),
            ...($r['unpaid'] > 0 ? ["• {$r['unpaid']} Bestellungen sind unbezahlt und kommen nicht auf den Stapel. Entscheide unten unter «Offene Bestellungen»."] : []),
            "{$unstacked->count()} bestätigte Bestellungen".($unstacked->isEmpty() ? '' : ' · Bücher bereitlegen: '.$signing->bookCountsLine($unstacked)),
        ];

        return Section::make('Widmungsaktion vorbereiten')
            ->description('Vergibt die Post-it-Nummern. Danach findest du hier Stapelliste, Briefe, Etiketten und den Versand. Die Aktion nimmt dann keine Bestellungen mehr an.')
            ->schema([
                ...array_map(fn (string $line) => Text::make($line), $lines),
                Actions::make(array_values(array_filter([
                    $this->prepareAction(),
                    $r['pending_moderation'] > 0
                        ? Action::make('review')->label('Widmungswünsche prüfen')->color('gray')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->url(ModerationReview::getUrl())
                        : null,
                ])))->key('prepareActions'),
            ]);
    }

    protected function prepareAction(): Action
    {
        return Action::make('prepare')
            ->label('Widmungsaktion vorbereiten')
            ->icon(Heroicon::OutlinedQueueList)
            ->disabled(fn () => app(SigningService::class)->cannotPrepare($this->selected) !== null)
            ->tooltip(fn () => app(SigningService::class)->cannotPrepare($this->selected))
            ->requiresConfirmation()
            ->modalHeading('Widmungsaktion vorbereiten?')
            ->modalDescription(fn () => app(SigningService::class)->unstacked($this->selected)->count().' Bestellungen bekommen eine Post-it-Nummer. Das geht nur einmal; Admins können es zurücksetzen, solange noch nichts gewidmet ist.')
            ->modalSubmitActionLabel('Post-it-Nummern vergeben')
            ->action(function () {
                try {
                    $count = app(SigningService::class)->prepare($this->selected, auth()->user());
                    Notification::make()->title("$count Bestellungen haben eine Post-it-Nummer")->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
                $this->reloadPage();
            });
    }

    protected function unprepareAction(): Action
    {
        return Action::make('unprepare')
            ->label('Vorbereitung zurücksetzen')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn () => auth()->user()->hasRoleIn(filament()->getTenant(), [TenantRole::Admin])
                && app(SigningService::class)->canUnprepare($this->selected))
            ->requiresConfirmation()
            ->modalDescription('Alle Post-it-Nummern dieser Aktion werden entfernt. Geht nur, solange noch kein Buch gewidmet ist. Bereits beschriftete Post-its stimmen danach nicht mehr.')
            ->action(function () {
                try {
                    app(SigningService::class)->unprepare($this->selected, auth()->user());
                    Notification::make()->title('Vorbereitung zurückgesetzt')->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
                $this->reloadPage();
            });
    }

    /**
     * Preparing and undoing swap the whole page; reload it instead of
     * rebuilding the content in the middle of the action.
     */
    protected function reloadPage(): void
    {
        $this->redirect(static::getUrl(['aktion' => $this->aktion]));
    }

    /**
     * Orders shipped in the table below: update the overview.
     */
    #[On('orders-updated')]
    public function ordersUpdated(): void
    {
        $this->refreshContent();
    }

    /**
     * The page content was built before the action ran during this request.
     */
    protected function refreshContent(): void
    {
        unset($this->selected);
        unset($this->cachedSchemas['content']);
    }

    protected function chooseAktionAction(): Action
    {
        return Action::make('chooseAktion')
            ->label(fn () => 'Aktion: '.($this->selected?->adminLabel() ?? 'wählen'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->fillForm(['aktion' => $this->aktion])
            ->schema([
                Select::make('aktion')->label('Aktion')->options($this->aktionen()->mapWithKeys(fn (Aktion $aktion) => [$aktion->id => $aktion->adminLabel()]))->required(),
            ])
            ->action(function (array $data) {
                $this->aktion = (int) $data['aktion'];
                $this->refreshContent();
            });
    }

    protected function downloadAction(string $document, string $label, Heroicon $icon): Action
    {
        return Action::make($document)
            ->label($label)
            ->icon($icon)
            ->action(fn () => $this->pdfResponse(
                match ($document) {
                    'stackList' => $this->print()->stackListHtml($this->selected),
                    'letters' => $this->print()->lettersHtml($this->selected),
                    'envelopes' => $this->print()->envelopesHtml($this->selected),
                },
                str($label)->before(' (')->slug().'-'.str($this->selected->name)->slug().'.pdf',
            ));
    }

    protected function labelsAction(): Action
    {
        return Action::make('labels')
            ->label('Adressetiketten (PDF)')
            ->icon(Heroicon::OutlinedTag)
            ->modalHeading('Adressetiketten')
            ->modalDescription(fn () => $this->print()->ordersNeedingLabels($this->selected)->count().' Etiketten')
            ->schema([
                TextInput::make('skip')
                    ->label('Leere Felder am Anfang überspringen')
                    ->helperText('Für angebrauchte Bogen: Anzahl bereits benutzter Etiketten.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ])
            ->modalSubmitActionLabel('PDF erstellen')
            ->action(fn (array $data) => $this->pdfResponse(
                $this->print()->labelsHtml($this->selected, (int) $data['skip']),
                'etiketten-'.str($this->selected->name)->slug().'.pdf',
            ));
    }

    protected function exportAction(): ExportAction
    {
        return ExportAction::make('export')
            ->label('Bestellungen exportieren')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->modalDescription('Alle abgeschickten Bestellungen dieser Aktion. Der Download-Link kommt in den Benachrichtigungen.')
            ->exporter(OrderExporter::class)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('orders.tenant_id', filament()->getTenant()->getKey())
                ->where('aktion_id', $this->selected->id)
                ->whereNot('status', OrderStatus::Draft)
                ->orderBy('submitted_at'));
    }

    protected function pdfResponse(string $html, string $filename): StreamedResponse
    {
        try {
            $pdf = $this->print()->render($html);
        } catch (RuntimeException $e) {
            Notification::make()->title('PDF konnte nicht erstellt werden')->body($e->getMessage())->danger()->send();
            throw $e;
        }

        return response()->streamDownload(fn () => print ($pdf), $filename, ['Content-Type' => 'application/pdf']);
    }
}
