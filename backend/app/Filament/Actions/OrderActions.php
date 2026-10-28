<?php

namespace App\Filament\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Aktion;
use App\Models\Order;
use App\Services\OrderMover;
use App\Services\Payments\PaymentService;
use App\Services\ShippingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Order actions shared by the order page and «Offene Bestellungen».
 * Each one takes the order as `$record`; the Aktion status follows along.
 */
class OrderActions
{
    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label('Als bezahlt markieren')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Order $record) => ! in_array($record->status, [OrderStatus::Draft, OrderStatus::Cancelled], true)
                && $record->payment_status === PaymentStatus::Unpaid)
            ->modalDescription(function (Order $record) {
                $ref = $record->payments()->whereNotNull('reference')->latest()->value('reference');

                return 'Total CHF '.number_format($record->total / 100, 2, '.', '').($ref ? " · Referenz $ref" : '');
            })
            ->schema([
                DatePicker::make('paid_at')->label('Zahlungseingang')->default(now())->maxDate(now())->required(),
                TextInput::make('note')->label('Notiz (optional)')->placeholder('z. B. ZKB-Auszug vom …'),
            ])
            ->action(function (Order $record, array $data) {
                app(PaymentService::class)->markOrderPaid($record, Carbon::parse($data['paid_at']), auth()->user(), $data['note'] ?? null);
                Notification::make()->title('Als bezahlt markiert')->success()->send();
            });
    }

    public static function move(): Action
    {
        return Action::make('move')
            ->label('In andere Aktion verschieben')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->visible(fn (Order $record) => app(OrderMover::class)->canMove($record))
            ->schema([
                Select::make('aktion_id')
                    ->label('Aktion')
                    ->options(fn (Order $record) => app(OrderMover::class)->targets($record->aktion)->mapWithKeys(fn (Aktion $aktion) => [$aktion->id => $aktion->adminLabel()]))
                    ->required(),
                Toggle::make('notify')->label('Kund*in per E-Mail informieren')->default(true),
            ])
            ->action(function (Order $record, array $data) {
                app(OrderMover::class)->move($record, Aktion::findOrFail($data['aktion_id']), auth()->user(), $data['notify']);
                Notification::make()->title('Bestellung verschoben')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Stornieren')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record) => ! in_array($record->status, [OrderStatus::Draft, OrderStatus::Cancelled, OrderStatus::Shipped], true))
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => $record->payment_status === PaymentStatus::Paid
                ? 'Die Bestellung ist bezahlt. Eine Rückerstattung musst du separat auslösen.'
                : 'Offene Zahlungen werden storniert.')
            ->schema([
                Textarea::make('reason')->label('Grund')->rows(2),
                Toggle::make('notify')->label('Kund*in per E-Mail informieren')->default(true),
            ])
            ->action(function (Order $record, array $data) {
                app(PaymentService::class)->cancel($record, $data['reason'] ?? null, auth()->user(), $data['notify']);
                app(ShippingService::class)->syncAktionStatus($record->aktion);
                Notification::make()->title('Bestellung storniert')->success()->send();
            });
    }

    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Rückerstattung')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (Order $record) => in_array($record->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true))
            ->modalDescription(fn (Order $record) => $record->payment_method?->value === 'online'
                ? 'Der Betrag wird über Stripe zurückerstattet.'
                : 'Die Rückerstattung wird nur verbucht. Bitte überweise den Betrag selbst.')
            ->schema([
                TextInput::make('amount')
                    ->label('Betrag (CHF)')
                    ->numeric()
                    ->step(0.05)
                    ->minValue(0.05)
                    ->default(fn (Order $record) => number_format(self::refundable($record) / 100, 2, '.', ''))
                    ->required(),
                Textarea::make('reason')->label('Grund')->rows(2),
                Toggle::make('cancel')->label('Bestellung zusätzlich stornieren'),
            ])
            ->action(function (Order $record, array $data, Action $action) {
                $payments = app(PaymentService::class);
                try {
                    $payments->refund($record, (int) round($data['amount'] * 100), $data['reason'] ?? null, auth()->user());
                    if ($data['cancel']) {
                        $payments->cancel($record->refresh(), $data['reason'] ?? null, auth()->user());
                        app(ShippingService::class)->syncAktionStatus($record->aktion);
                    }
                    Notification::make()->title('Rückerstattung verbucht')->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();
                }
            });
    }

    /**
     * Move the selected orders (those that can move) into one Aktion.
     */
    public static function moveBulk(Aktion $from): BulkAction
    {
        return BulkAction::make('moveSelected')
            ->label('In andere Aktion verschieben')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->schema([
                Select::make('aktion_id')
                    ->label('Aktion')
                    ->options(fn () => app(OrderMover::class)->targets($from)->mapWithKeys(fn (Aktion $aktion) => [$aktion->id => $aktion->adminLabel()]))
                    ->required(),
                Toggle::make('notify')->label('Kund*innen per E-Mail informieren')->default(true),
            ])
            ->action(function (Collection $records, array $data) {
                $mover = app(OrderMover::class);
                $to = Aktion::findOrFail($data['aktion_id']);
                $movable = $records->filter(fn (Order $order) => $mover->canMove($order));
                $movable->each(fn (Order $order) => $mover->move($order, $to, auth()->user(), $data['notify']));
                Notification::make()
                    ->title("{$movable->count()} Bestellungen verschoben")
                    ->body($movable->count() < $records->count() ? ($records->count() - $movable->count()).' konnten nicht verschoben werden (schon gewidmet oder versendet).' : null)
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function cancelBulk(): BulkAction
    {
        return BulkAction::make('cancelSelected')
            ->label('Stornieren')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Offene Zahlungen werden storniert. Bezahlte Bestellungen musst du separat zurückerstatten.')
            ->schema([
                Textarea::make('reason')->label('Grund')->rows(2),
                Toggle::make('notify')->label('Kund*innen per E-Mail informieren')->default(true),
            ])
            ->action(function (Collection $records, array $data) {
                $payments = app(PaymentService::class);
                $cancellable = $records->filter(fn (Order $order) => in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Confirmed, OrderStatus::Signed], true));
                $cancellable->each(fn (Order $order) => $payments->cancel($order, $data['reason'] ?? null, auth()->user(), $data['notify']));
                $cancellable->pluck('aktion')->unique('id')->each(fn (Aktion $aktion) => app(ShippingService::class)->syncAktionStatus($aktion));
                Notification::make()->title("{$cancellable->count()} Bestellungen storniert")->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function refundable(Order $record): int
    {
        $payment = $record->payments()->where('status', 'paid')->latest('paid_at')->first();

        return $payment ? $payment->amount - $payment->refunded_amount : 0;
    }
}
