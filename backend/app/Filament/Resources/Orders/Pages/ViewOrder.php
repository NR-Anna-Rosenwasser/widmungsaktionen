<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantRole;
use App\Filament\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\AdminOrders;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\InvoicePdf;
use App\Services\Pdf\PrintService;
use App\Services\ShippingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property Order $record
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->number ?? 'Entwurf #'.$this->record->id;
    }

    protected function getHeaderActions(): array
    {
        $payments = app(PaymentService::class);

        return [
            OrderActions::markPaid()->after(fn () => $this->refreshRecord()),

            EditAction::make()
                ->label('Bearbeiten')
                ->color('gray')
                ->visible(fn () => AdminOrders::canEdit($this->record)),

            Action::make('ship')
                ->label('Versenden')
                ->icon(Heroicon::OutlinedTruck)
                ->color('success')
                ->visible(fn () => $this->record->status === OrderStatus::Signed)
                ->schema([
                    DatePicker::make('date')->label('Versanddatum')->default(now())->required(),
                    TextInput::make('tracking_number')->label('Sendungsnummer (optional)'),
                    Toggle::make('notify')->label('Kund*in per E-Mail informieren')->default(true),
                ])
                ->action(function (array $data) {
                    $shipping = app(ShippingService::class);
                    $shipping->ship($this->record, Carbon::parse($data['date']), auth()->user(), $data['tracking_number'] ?? null, notify: $data['notify']);
                    $shipping->syncAktionStatus($this->record->aktion);
                    Notification::make()->title('Als versendet markiert')->success()->send();
                    $this->refreshRecord();
                }),

            Action::make('reopen')
                ->label('Stornierung aufheben')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn () => $payments->canReopen($this->record))
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Die Bestellung wird wieder aktiv'
                    .($this->record->payment_status === PaymentStatus::Unpaid ? ' und wartet wieder auf die Zahlung.' : '.')
                    .($this->record->aktion->order_deadline->isPast() ? ' Achtung: Der Bestellschluss dieser Aktion ist vorbei. Verschiebe sie allenfalls in eine andere Aktion.' : '')
                    .' Die Kund*in wird nicht automatisch informiert.')
                ->action(function () use ($payments) {
                    $payments->reopen($this->record, auth()->user());
                    Notification::make()->title('Stornierung aufgehoben')->success()->send();
                    $this->refreshRecord();
                }),

            ActionGroup::make([
                Action::make('printLetter')
                    ->label('Begleitbrief (PDF)')
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->visible(fn () => $this->canPrint())
                    ->action(fn () => $this->pdfResponse(
                        fn (PrintService $print, Order $order) => $print->lettersHtml($order->aktion, collect([$order])),
                        'begleitbrief-'.str($this->record->number)->slug().'.pdf',
                    )),

                Action::make('printLabel')
                    ->label('Adressetikett (PDF)')
                    ->icon(Heroicon::OutlinedTag)
                    ->visible(fn () => $this->canPrint())
                    ->modalHeading('Adressetikett')
                    ->modalDescription(fn (PrintService $print) => $print->needsLabel($this->record)
                        ? null
                        : 'Hinweis: Mit der eingestellten Couvert-Art braucht diese Bestellung eigentlich kein Etikett. Du kannst es trotzdem drucken.')
                    ->schema(fn (PrintService $print) => [
                        TextInput::make('position')
                            ->label('Position auf dem Etikettenbogen')
                            ->helperText('1 = oben links, dann zeilenweise nach rechts. Dieser Bogen hat '.$print->labelsPerSheet($this->record->aktion).' Etiketten.')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue($print->labelsPerSheet($this->record->aktion))
                            ->default(1),
                    ])
                    ->modalSubmitActionLabel('PDF erstellen')
                    ->action(fn (array $data) => $this->pdfResponse(
                        fn (PrintService $print, Order $order) => $print->labelsHtml($order->aktion, (int) $data['position'] - 1, collect([$order])),
                        'etikett-'.str($this->record->number)->slug().'.pdf',
                    )),

                Action::make('printEnvelope')
                    ->label('Couvert C5 (PDF)')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn () => $this->canPrint())
                    // Straight to the PDF, unless the settings would do it differently.
                    ->requiresConfirmation(fn (PrintService $print) => $this->envelopeHint($print) !== null)
                    ->modalHeading('Couvert C5')
                    ->modalDescription(fn (PrintService $print) => $this->envelopeHint($print))
                    ->modalSubmitActionLabel('PDF erstellen')
                    ->action(fn () => $this->pdfResponse(
                        fn (PrintService $print, Order $order) => $print->envelopesHtml($order->aktion, collect([$order])),
                        'couvert-'.str($this->record->number)->slug().'.pdf',
                    )),
            ])
                ->label('Drucken')
                ->icon(Heroicon::OutlinedPrinter)
                ->button()
                ->color('gray')
                ->visible(fn () => $this->canPrint()),

            ActionGroup::make([
                Action::make('revertPayment')
                    ->label('Zahlung zurücksetzen')
                    ->icon(Heroicon::OutlinedBackward)
                    ->color('warning')
                    ->visible(fn () => $payments->revertablePayment($this->record) !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Zahlung zurücksetzen?')
                    ->modalDescription('Für den Fall, dass die falsche Bestellung als bezahlt markiert wurde: Die Zahlung wird wieder als offen geführt. Die Kund*in wird nicht informiert. Hat sie bereits eine «Zahlung erhalten»-Mail bekommen, melde dich am besten persönlich.')
                    ->schema([
                        TextInput::make('reason')->label('Grund (optional)')->placeholder('z. B. falsche Bestellung markiert'),
                    ])
                    ->action(function (array $data) use ($payments) {
                        $payments->revertPayment($this->record, auth()->user(), $data['reason'] ?? null);
                        Notification::make()->title('Zahlung zurückgesetzt')->success()->send();
                        $this->refreshRecord();
                    }),

                Action::make('invoice')
                    ->label(fn () => $this->record->status === OrderStatus::Cancelled ? 'Rechnung (PDF, storniert)' : 'Rechnung (PDF)')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn () => $this->record->payments()->whereNotNull('reference')->exists())
                    ->action(function (InvoicePdf $invoices) {
                        $pdf = $invoices->render($this->record);

                        return response()->streamDownload(fn () => print ($pdf), $invoices->filename($this->record), ['Content-Type' => 'application/pdf']);
                    }),

                Action::make('duplicate')
                    ->label('Neue Bestellung auf Basis dieser')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn () => $this->record->status !== OrderStatus::Draft)
                    ->url(fn () => OrderResource::getUrl('create', ['from' => $this->record->id])),

                OrderActions::refund()->after(fn () => $this->refreshRecord()),
                OrderActions::move()->after(fn () => $this->refreshRecord()),
                OrderActions::cancel()->after(fn () => $this->refreshRecord()),
            ])->label('Weitere Aktionen')->button()->color('gray'),
            // Test orders only (OrderPolicy).
            DeleteAction::make()->label('Testbestellung löschen')->modalHeading('Testbestellung löschen'),
        ];
    }

    protected function refreshRecord(): void
    {
        $this->record->refresh();
    }

    /**
     * Letter and label as on «Vorbereiten & Versand», for a single order on
     * the stack (e.g. a late one).
     */
    protected function canPrint(): bool
    {
        return $this->record->stack_number !== null
            && ! in_array($this->record->status, [OrderStatus::Draft, OrderStatus::Cancelled], true)
            && auth()->user()->hasRoleIn(filament()->getTenant(), [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    /**
     * Why a C5 envelope is unusual for this order (null: it's the normal way).
     */
    protected function envelopeHint(PrintService $print): ?string
    {
        return match (true) {
            ! $print->isLetter($this->record) => 'Hinweis: Diese Bestellung geht als Paket, ein C5-Couvert passt dafür normalerweise nicht. Du kannst es trotzdem drucken.',
            $this->record->tenant->setting('print.envelope_mode') !== 'direct_print' => 'Hinweis: In den Einstellungen werden Couverts normalerweise nicht direkt bedruckt. Du kannst es trotzdem drucken.',
            default => null,
        };
    }

    /**
     * @param  callable(PrintService, Order): string  $html
     */
    protected function pdfResponse(callable $html, string $filename): ?StreamedResponse
    {
        $print = app(PrintService::class);
        $order = $print->stackedOrder($this->record);

        if (! $order) {
            Notification::make()->title('Diese Bestellung ist nicht (mehr) auf dem Stapel')->warning()->send();

            return null;
        }

        try {
            $pdf = $print->render($html($print, $order));
        } catch (RuntimeException $e) {
            Notification::make()->title('PDF konnte nicht erstellt werden')->body($e->getMessage())->danger()->send();
            throw $e;
        }

        return response()->streamDownload(fn () => print ($pdf), $filename, ['Content-Type' => 'application/pdf']);
    }
}
