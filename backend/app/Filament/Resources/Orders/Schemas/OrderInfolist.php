<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Services\Payments\PaymentReference;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make('Bücher & Widmungen')->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('book.title')->label('Buch'),
                                TextEntry::make('recipient_name')->label('Für')->placeholder('–'),
                                TextEntry::make('wish')
                                    ->label('Wunsch')
                                    ->placeholder(fn ($record) => match (true) {
                                        (bool) $record->signature_only => 'Nur Unterschrift',
                                        (bool) $record->recipient_name => 'Kein Wunsch: frei widmen',
                                        default => '–',
                                    })
                                    ->columnSpan(2),
                                TextEntry::make('moderation_status')->label('Moderation')->badge(),
                                TextEntry::make('signed_at')->label('Gewidmet')->dateTime('d.m.Y H:i')->placeholder('–'),
                            ]),
                    ]),
                    Section::make('Adressen')->columns(2)->schema([
                        TextEntry::make('billing_address')
                            ->label('Rechnungsadresse')
                            ->state(fn (Order $record) => self::address($record, 'billing'))
                            ->html(),
                        TextEntry::make('shipping_address')
                            ->label('Lieferadresse')
                            ->state(fn (Order $record) => $record->is_gift ? self::address($record, 'shipping') : 'Wie Rechnungsadresse')
                            ->html(),
                        TextEntry::make('gift_message')
                            ->label('Nachricht im Begleitbrief')
                            ->visible(fn (Order $record) => $record->is_gift)
                            ->columnSpanFull(),
                    ]),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Status')->schema([
                        TextEntry::make('status')->badge()->color(fn (OrderStatus $state) => OrdersTable::statusColor($state)),
                        TextEntry::make('payment_status')->label('Zahlung')->badge(),
                        TextEntry::make('payment_method')->label('Zahlungsart')->placeholder('–'),
                        TextEntry::make('aktion.name')->label('Aktion'),
                        TextEntry::make('stack_number')->label('Stapelnummer')->placeholder('noch nicht vergeben'),
                        TextEntry::make('email')->label('E-Mail')->copyable(),
                        IconEntry::make('newsletter_opt_in')->label('Newsletter')->boolean(),
                    ]),
                    Section::make('Betrag')->schema([
                        TextEntry::make('subtotal')->label('Bücher')->money('CHF', divideBy: 100),
                        TextEntry::make('shipping_cost')->label('Versand')->money('CHF', divideBy: 100),
                        TextEntry::make('total')->label('Total')->money('CHF', divideBy: 100)->weight('bold'),
                    ]),
                    Section::make('Zahlungen')
                        ->visible(fn (Order $record) => $record->payments()->exists())
                        ->schema([
                            RepeatableEntry::make('payments')
                                ->hiddenLabel()
                                ->schema([
                                    TextEntry::make('method')->hiddenLabel()->weight('bold'),
                                    TextEntry::make('status')->hiddenLabel()->badge(),
                                    TextEntry::make('amount')->label('Betrag')->money('CHF', divideBy: 100)->inlineLabel(),
                                    TextEntry::make('refunded_amount')->label('Erstattet')->money('CHF', divideBy: 100)->inlineLabel()
                                        ->visible(fn ($state) => $state > 0),
                                    TextEntry::make('reference')->label('Referenz')->inlineLabel()->copyable()
                                        ->formatStateUsing(fn (string $state) => PaymentReference::format($state))
                                        ->visible(fn ($state) => filled($state)),
                                    TextEntry::make('paid_at')->label('Bezahlt am')->date('d.m.Y')->inlineLabel()
                                        ->visible(fn ($state) => filled($state)),
                                    TextEntry::make('meta.note')->label('Notiz')->inlineLabel()
                                        ->visible(fn ($state) => filled($state)),
                                ]),
                        ]),
                    Section::make('Verlauf')->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->contained(false)
                            ->schema([
                                TextEntry::make('type')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn (string $state) => __("events.$state"))
                                    ->helperText(fn ($record) => $record->created_at->format('d.m.Y H:i').($record->user ? ' · '.$record->user->name : '')),
                            ]),
                    ]),
                ]),
            ]);
    }

    protected static function address(Order $order, string $prefix): string
    {
        return collect([
            $order->{"{$prefix}_company"},
            trim($order->{"{$prefix}_first_name"}.' '.$order->{"{$prefix}_last_name"}),
            $order->{"{$prefix}_street"},
            trim($order->{"{$prefix}_postal_code"}.' '.$order->{"{$prefix}_town"}),
        ])->filter()->map(fn ($line) => e($line))->implode('<br>') ?: '–';
    }
}
