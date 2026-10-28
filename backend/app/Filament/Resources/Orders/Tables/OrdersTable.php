<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Aktion;
use App\Models\Order;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                TextColumn::make('stack_number')->label('Post-it')->prefix('#')->sortable()->placeholder('–')->toggleable(),
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
                TextColumn::make('aktion.name')->label('Aktion')->toggleable()
                    ->badge()
                    ->color(fn (Order $record) => $record->isTest() ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state, Order $record) => $record->isTest() ? "TEST · $state" : $state),
                TextColumn::make('created_at')->label('Erstellt')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('aktion')->label('Aktion')
                    ->relationship('aktion', 'name', fn ($query) => $query->whereBelongsTo(filament()->getTenant()))
                    ->getOptionLabelFromRecordUsing(fn (Aktion $record) => $record->adminLabel()),
                SelectFilter::make('payment_method')->label('Zahlungsart')->options(PaymentMethod::class),
                SelectFilter::make('payment_status')->label('Zahlung')->options(PaymentStatus::class),
                TernaryFilter::make('test')
                    ->label('Testbestellungen')
                    ->placeholder('Alle')
                    ->trueLabel('Nur Testbestellungen')
                    ->falseLabel('Ohne Testbestellungen')
                    ->queries(
                        true: fn ($query) => $query->whereHas('aktion', fn ($q) => $q->where('is_test', true)),
                        false: fn ($query) => $query->whereHas('aktion', fn ($q) => $q->where('is_test', false)),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                // Test orders only (OrderPolicy).
                DeleteAction::make()->iconButton()->modalHeading('Testbestellung löschen'),
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
