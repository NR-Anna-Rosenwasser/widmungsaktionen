<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('number')->label('Nr.')->searchable()->placeholder('–'),
                TextColumn::make('billing_last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Order $record) => $record->billingName() ?: '–')
                    ->description(fn (Order $record) => $record->email)
                    ->searchable(['billing_first_name', 'billing_last_name', 'email']),
                IconColumn::make('is_gift')->label('Geschenk')->boolean()->trueIcon('heroicon-o-gift')->falseIcon(null),
                TextColumn::make('items_count')->label('Bücher'),
                TextColumn::make('total')->label('Total')->money('CHF', divideBy: 100),
                TextColumn::make('status')->label('Status')->badge()->color(fn (OrderStatus $state) => self::statusColor($state)),
                TextColumn::make('payment_status')->label('Zahlung')->badge()->color(fn (PaymentStatus $state) => $state === PaymentStatus::Paid ? 'success' : 'gray')
                    ->description(fn (Order $record) => $record->payment_method?->getLabel()),
                TextColumn::make('aktion.name')->label('Aktion')->toggleable(),
                TextColumn::make('created_at')->label('Erstellt')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('aktion')->label('Aktion')->relationship('aktion', 'name', fn ($query) => $query->whereBelongsTo(filament()->getTenant())),
                SelectFilter::make('payment_method')->label('Zahlungsart')->options(PaymentMethod::class),
                SelectFilter::make('payment_status')->label('Zahlung')->options(PaymentStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function statusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft, OrderStatus::Cancelled => 'gray',
            OrderStatus::PendingPayment => 'warning',
            OrderStatus::Confirmed => 'info',
            OrderStatus::Signed => 'primary',
            OrderStatus::Shipped => 'success',
        };
    }
}
