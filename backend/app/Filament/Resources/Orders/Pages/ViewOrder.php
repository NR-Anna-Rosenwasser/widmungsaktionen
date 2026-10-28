<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\InvoicePdf;
use App\Services\ShippingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

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
}
