<?php

namespace App\Filament\Exports;

use App\Enums\PaymentState;
use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Orders as CSV/XLSX: from the order list (current tab, filters, search or
 * selection) and per Aktion on «Vorbereiten & Versand». Runs on the queue;
 * the download link arrives as a notification.
 */
class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        $chf = fn (?int $rappen) => number_format(($rappen ?? 0) / 100, 2, '.', '');
        $yesNo = fn (bool $value) => $value ? 'ja' : 'nein';

        return [
            ExportColumn::make('number')->label('Nr.'),
            ExportColumn::make('stack_number')->label('Post-it'),
            ExportColumn::make('submitted_at')->label('Datum')
                ->state(fn (Order $record) => $record->submitted_at?->format('d.m.Y H:i')),
            ExportColumn::make('status')->label('Status')
                ->state(fn (Order $record) => $record->status->getLabel()),
            ExportColumn::make('payment_status')->label('Zahlung')
                ->state(fn (Order $record) => $record->payment_status->getLabel()),
            ExportColumn::make('payment_method')->label('Zahlungsart')
                ->state(fn (Order $record) => $record->payment_method?->getLabel()),
            ExportColumn::make('paid_at')->label('Bezahlt am')
                ->state(fn (Order $record) => $record->payments->where('status', PaymentState::Paid)->sortByDesc('paid_at')->first()?->paid_at?->format('d.m.Y')),
            ExportColumn::make('books')->label('Bücher')
                ->state(fn (Order $record) => self::booksLine($record)),
            ExportColumn::make('copies')->label('Exemplare')
                ->state(fn (Order $record) => $record->items->count()),
            ExportColumn::make('subtotal')->label('Bücher CHF')
                ->state(fn (Order $record) => $chf($record->subtotal)),
            ExportColumn::make('shipping_cost')->label('Versand CHF')
                ->state(fn (Order $record) => $chf($record->shipping_cost)),
            ExportColumn::make('total')->label('Total CHF')
                ->state(fn (Order $record) => $chf($record->total)),
            ExportColumn::make('refunded')->label('Erstattet CHF')
                ->state(fn (Order $record) => $chf($record->payments->sum('refunded_amount'))),
            ExportColumn::make('name')->label('Name')
                ->state(fn (Order $record) => $record->billingName()),
            ExportColumn::make('email')->label('E-Mail'),
            ExportColumn::make('billing_postal_code')->label('PLZ'),
            ExportColumn::make('billing_town')->label('Ort'),
            ExportColumn::make('is_gift')->label('Geschenk')
                ->state(fn (Order $record) => $yesNo($record->is_gift)),
            ExportColumn::make('newsletter_opt_in')->label('Newsletter')
                ->state(fn (Order $record) => $yesNo($record->newsletter_opt_in)),
            ExportColumn::make('aktion')->label('Aktion')
                ->state(fn (Order $record) => $record->aktion?->adminLabel()),
            ExportColumn::make('billing_street')->label('Strasse'),
            ExportColumn::make('billing_country')->label('Land'),
            ExportColumn::make('shipping_name')->label('Lieferung an')
                ->state(fn (Order $record) => $record->is_gift ? $record->shippingName() : null),
            ExportColumn::make('shipping_address')->label('Lieferadresse')
                ->state(fn (Order $record) => $record->is_gift
                    ? implode(', ', array_slice($record->shippingAddressLines(), $record->shipping_company ? 2 : 1))
                    : null),
            ExportColumn::make('gift_message')->label('Geschenknachricht'),
            ExportColumn::make('cancelled_at')->label('Storniert am')
                ->state(fn (Order $record) => $record->cancelled_at?->format('d.m.Y')),
            ExportColumn::make('cancel_reason')->label('Storno-Grund'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['items.book', 'payments', 'aktion']);
    }

    /**
     * «2× Herz, 1× Ganz schön queer»
     */
    public static function booksLine(Order $order): string
    {
        return $order->items
            ->map(fn ($item) => $item->book->title)
            ->countBy()
            ->map(fn (int $count, string $title) => "{$count}× {$title}")
            ->implode(', ');
    }

    public static function getCsvDelimiter(): string
    {
        // Excel with Swiss settings expects semicolons.
        return ';';
    }

    public function getFileDisk(): string
    {
        // Customer data: never on the public disk.
        return 'local';
    }

    public function getFileName(Export $export): string
    {
        return 'bestellungen-'.now()->format('Y-m-d').'-'.$export->getKey();
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Export fertig';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = Number::format($export->successful_rows).' '.($export->successful_rows === 1 ? 'Bestellung' : 'Bestellungen').' exportiert.';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed).' konnten nicht exportiert werden.';
        }

        return $body;
    }
}
