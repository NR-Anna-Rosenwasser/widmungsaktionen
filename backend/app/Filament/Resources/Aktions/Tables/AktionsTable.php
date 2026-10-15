<?php

namespace App\Filament\Resources\Aktions\Tables;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Aktions\Pages\EditAktion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AktionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orders_open_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'orders as submitted_orders_count' => fn ($q) => $q->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]),
            ]))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('status')->label('Status')->badge()->color(fn (AktionStatus $state) => match ($state) {
                    AktionStatus::Open => 'success',
                    AktionStatus::Scheduled => 'info',
                    AktionStatus::Closed, AktionStatus::Signing, AktionStatus::Shipping => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('order_deadline')->label('Bestellschluss')->dateTime('d.m.Y H:i'),
                TextColumn::make('signing_date')->label('Widmungstag')->date('d.m.Y'),
                TextColumn::make('estimated_shipping_date')->label('Versand ca.')->date('d.m.Y'),
                TextColumn::make('submitted_orders_count')->label('Bestellungen'),
            ])
            ->recordActions([
                EditAktion::prepareAction()->iconButton()->tooltip('Widmen vorbereiten'),
                EditAction::make(),
            ]);
    }
}
