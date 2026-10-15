<?php

namespace App\Filament\Pages;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Enums\TenantRole;
use App\Models\Aktion;
use App\Services\Pdf\PrintService;
use App\Services\ShippingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Everything after signing day: print documents and mark orders as shipped.
 */
class Versand extends Page
{
    protected static ?string $slug = 'drucken-versand';

    protected static ?string $title = 'Drucken & Versand';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|UnitEnum|null $navigationGroup = 'Widmen';

    protected static ?int $navigationSort = 3;

    #[Url]
    public ?int $aktion = null;

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()?->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    public function mount(): void
    {
        // Default to the Aktion that is currently being worked on.
        $priority = [AktionStatus::Signing, AktionStatus::Shipping, AktionStatus::Closed, AktionStatus::Open, AktionStatus::Scheduled, AktionStatus::Done];

        $this->aktion ??= $this->aktionen()
            ->sortBy(fn (Aktion $aktion) => array_search($aktion->status, $priority, true))
            ->first()?->id;
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
                ]),

            Section::make('1. Vorbereiten')
                ->description('Liste zum Beschriften der Post-its und zum Abhaken beim Widmen und Verpacken.')
                ->schema([Actions::make([$this->downloadAction('stackList', 'Stapelliste (PDF)', Heroicon::OutlinedQueueList)])->key('prepareActions')]),

            Section::make('2. Briefe & Adressen')
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

            Section::make('3. Versand')
                ->description('Markiert alle gewidmeten Bestellungen dieser Aktion als versendet. Kund*innen bekommen eine E-Mail. Sendungsnummern kannst du einzeln bei der Bestellung erfassen.')
                ->schema([Actions::make([$this->shipAllAction(), $this->exportAction()])->key('shipActions')]),
        ]);
    }

    protected function chooseAktionAction(): Action
    {
        return Action::make('chooseAktion')
            ->label(fn () => 'Aktion: '.($this->selected?->name ?? 'wählen'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->fillForm(['aktion' => $this->aktion])
            ->schema([
                Select::make('aktion')->label('Aktion')->options($this->aktionen()->pluck('name', 'id'))->required(),
            ])
            ->action(function (array $data) {
                $this->aktion = (int) $data['aktion'];
                unset($this->selected);
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

    protected function shipAllAction(): Action
    {
        return Action::make('shipAll')
            ->label('Gewidmete als versendet markieren')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn () => $this->selected->orders()->where('status', OrderStatus::Signed)->count().' Bestellungen werden als versendet markiert.')
            ->schema([
                DatePicker::make('date')->label('Versanddatum')->default(now())->required(),
                Toggle::make('notify')->label('Kund*innen per E-Mail informieren')->default(true),
            ])
            ->action(function (array $data) {
                $shipping = app(ShippingService::class);
                $orders = $this->selected->orders()->where('status', OrderStatus::Signed)->get();

                foreach ($orders as $order) {
                    $shipping->ship($order, Carbon::parse($data['date']), auth()->user(), notify: $data['notify']);
                }

                if ($this->selected->orders()->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Signed])->doesntExist()) {
                    $this->selected->update(['status' => AktionStatus::Done]);
                } elseif ($orders->isNotEmpty()) {
                    $this->selected->update(['status' => AktionStatus::Shipping]);
                }

                Notification::make()->title($orders->count().' Bestellungen versendet')->success()->send();
            });
    }

    protected function exportAction(): Action
    {
        return Action::make('export')
            ->label('Bestellungen exportieren (CSV)')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->action(fn () => $this->csvResponse());
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

    /**
     * All submitted orders of the Aktion, Excel-friendly (semicolon, UTF-8 BOM).
     */
    protected function csvResponse(): StreamedResponse
    {
        $orders = $this->selected->orders()
            ->whereNot('status', OrderStatus::Draft)
            ->with('items.book', 'payments')
            ->orderBy('submitted_at')
            ->get();

        $filename = 'bestellungen-'.str($this->selected->name)->slug().'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nr.', 'Post-it', 'Datum', 'Status', 'Zahlung', 'Zahlungsart', 'Bezahlt am', 'Bücher', 'Exemplare', 'Bücher CHF', 'Versand CHF', 'Total CHF', 'Erstattet CHF', 'Name', 'E-Mail', 'PLZ', 'Ort', 'Geschenk', 'Newsletter'], ';');

            foreach ($orders as $order) {
                $paid = $order->payments->where('status.value', 'paid')->sortByDesc('paid_at')->first();
                fputcsv($out, [
                    $order->number,
                    $order->stack_number,
                    $order->submitted_at?->format('d.m.Y H:i'),
                    $order->status->getLabel(),
                    $order->payment_status->getLabel(),
                    $order->payment_method?->getLabel(),
                    $paid?->paid_at?->format('d.m.Y'),
                    $order->items->map(fn ($item) => $item->book->title)->countBy()->map(fn ($n, $title) => "{$n}× {$title}")->implode(', '),
                    $order->items->count(),
                    number_format($order->subtotal / 100, 2, '.', ''),
                    number_format($order->shipping_cost / 100, 2, '.', ''),
                    number_format($order->total / 100, 2, '.', ''),
                    number_format($order->payments->sum('refunded_amount') / 100, 2, '.', ''),
                    $order->billingName(),
                    $order->email,
                    $order->billing_postal_code,
                    $order->billing_town,
                    $order->is_gift ? 'ja' : 'nein',
                    $order->newsletter_opt_in ? 'ja' : 'nein',
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
