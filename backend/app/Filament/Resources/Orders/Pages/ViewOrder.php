<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderMoved;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Aktion;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\InvoicePdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RuntimeException;

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
            Action::make('markPaid')
                ->label('Als bezahlt markieren')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn () => $this->record->status !== OrderStatus::Draft
                    && $this->record->status !== OrderStatus::Cancelled
                    && $this->record->payment_status === PaymentStatus::Unpaid)
                ->modalDescription(function () {
                    $ref = $this->record->payments()->whereNotNull('reference')->latest()->value('reference');

                    return 'Total CHF '.number_format($this->record->total / 100, 2, '.', '').($ref ? " · Referenz $ref" : '');
                })
                ->schema([
                    DatePicker::make('paid_at')->label('Zahlungseingang')->default(now())->maxDate(now())->required(),
                    TextInput::make('note')->label('Notiz (optional)')->placeholder('z. B. ZKB-Auszug vom …'),
                ])
                ->action(function (array $data) use ($payments) {
                    $payments->markOrderPaid($this->record, Carbon::parse($data['paid_at']), auth()->user(), $data['note'] ?? null);
                    Notification::make()->title('Als bezahlt markiert')->success()->send();
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

                Action::make('refund')
                    ->label('Rückerstattung')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn () => in_array($this->record->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true))
                    ->modalDescription(fn () => $this->record->payment_method?->value === 'online'
                        ? 'Der Betrag wird über Stripe zurückerstattet.'
                        : 'Die Rückerstattung wird nur verbucht. Bitte überweise den Betrag selbst.')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Betrag (CHF)')
                            ->numeric()
                            ->step(0.05)
                            ->minValue(0.05)
                            ->default(fn () => number_format($this->refundable() / 100, 2, '.', ''))
                            ->required(),
                        Textarea::make('reason')->label('Grund')->rows(2),
                        Toggle::make('cancel')->label('Bestellung zusätzlich stornieren'),
                    ])
                    ->action(function (array $data, Action $action) use ($payments) {
                        try {
                            $payments->refund($this->record, (int) round($data['amount'] * 100), $data['reason'] ?? null, auth()->user());
                            if ($data['cancel']) {
                                $payments->cancel($this->record->refresh(), $data['reason'] ?? null, auth()->user());
                            }
                            Notification::make()->title('Rückerstattung verbucht')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            $action->halt();
                        }
                        $this->refreshRecord();
                    }),

                Action::make('move')
                    ->label('In andere Aktion verschieben')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->visible(fn () => in_array($this->record->status, [OrderStatus::PendingPayment, OrderStatus::Confirmed], true))
                    ->schema([
                        Select::make('aktion_id')
                            ->label('Aktion')
                            ->options(fn () => Aktion::query()
                                ->whereBelongsTo($this->record->tenant)
                                ->whereKeyNot($this->record->aktion_id)
                                ->whereIn('status', [AktionStatus::Draft, AktionStatus::Scheduled, AktionStatus::Open])
                                ->pluck('name', 'id'))
                            ->required(),
                        Toggle::make('notify')->label('Kund*in per E-Mail informieren')->default(true),
                    ])
                    ->action(function (array $data) {
                        $from = $this->record->aktion->name;
                        $this->record->update(['aktion_id' => $data['aktion_id'], 'stack_number' => null]);
                        $this->record->log('moved', ['from' => $from, 'to' => $this->record->aktion->name], public: true, user: auth()->user());
                        if ($data['notify']) {
                            OrderMoved::dispatch($this->record);
                        }
                        Notification::make()->title('Bestellung verschoben')->success()->send();
                        $this->refreshRecord();
                    }),

                Action::make('cancel')
                    ->label('Stornieren')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn () => ! in_array($this->record->status, [OrderStatus::Draft, OrderStatus::Cancelled, OrderStatus::Shipped], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn () => $this->record->payment_status === PaymentStatus::Paid
                        ? 'Die Bestellung ist bezahlt. Eine Rückerstattung musst du separat auslösen.'
                        : 'Offene Zahlungen werden storniert.')
                    ->schema([
                        Textarea::make('reason')->label('Grund')->rows(2),
                        Toggle::make('notify')->label('Kund*in per E-Mail informieren')->default(true),
                    ])
                    ->action(function (array $data) use ($payments) {
                        $payments->cancel($this->record, $data['reason'] ?? null, auth()->user(), $data['notify']);
                        Notification::make()->title('Bestellung storniert')->success()->send();
                        $this->refreshRecord();
                    }),
            ])->label('Weitere Aktionen')->button()->color('gray'),
        ];
    }

    protected function refundable(): int
    {
        $payment = $this->record->payments()->where('status', 'paid')->latest('paid_at')->first();

        return $payment ? $payment->amount - $payment->refunded_amount : 0;
    }

    protected function refreshRecord(): void
    {
        $this->record->refresh();
    }
}
