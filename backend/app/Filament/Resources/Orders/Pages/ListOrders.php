<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $status = fn (OrderStatus ...$statuses) => fn (Builder $query) => $query->whereIn('status', $statuses);

        return [
            'active' => Tab::make('Aktiv')->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled])),
            'pending_payment' => Tab::make('Zahlung offen')->modifyQueryUsing($status(OrderStatus::PendingPayment)),
            'confirmed' => Tab::make('Zum Widmen')->modifyQueryUsing($status(OrderStatus::Confirmed)),
            'signed' => Tab::make('Zum Versand')->modifyQueryUsing($status(OrderStatus::Signed)),
            'shipped' => Tab::make('Versendet')->modifyQueryUsing($status(OrderStatus::Shipped)),
            'drafts' => Tab::make('Abgebrochen')->modifyQueryUsing($status(OrderStatus::Draft)),
            'cancelled' => Tab::make('Storniert')->modifyQueryUsing($status(OrderStatus::Cancelled)),
        ];
    }
}
