<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Widgets\Concerns\ResolvesAktion;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AktionOverview extends StatsOverviewWidget
{
    use ResolvesAktion;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    protected function getHeading(): ?string
    {
        return $this->aktion()?->name;
    }

    protected function getDescription(): ?string
    {
        $aktion = $this->aktion();

        return $aktion
            ? "Bestellschluss {$aktion->order_deadline->format('d.m.Y H:i')} · Widmungstag {$aktion->signing_date->format('d.m.Y')} · Status: {$aktion->status->getLabel()}"
            : null;
    }

    protected function getStats(): array
    {
        $aktion = $this->aktion();
        if (! $aktion) {
            return [Stat::make('Aktion', 'Noch keine Aktion')];
        }

        $orders = $aktion->orders()->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]);
        $copies = OrderItem::query()->whereIn('order_id', (clone $orders)->select('id'))->count();
        $paid = (clone $orders)->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])->get();
        $revenue = $paid->sum('total') - $paid->sum(fn ($order) => $order->payments()->sum('refunded_amount'));
        $unpaid = (clone $orders)->where('status', OrderStatus::PendingPayment);
        $inParcel = (clone $orders)->where('status', OrderStatus::Confirmed)->where('payment_status', PaymentStatus::Unpaid)->sum('total');
        $chf = fn (int $rappen) => 'CHF '.number_format($rappen / 100, 0, '.', "'");

        return [
            Stat::make('Bestellungen', (clone $orders)->count())
                ->description("$copies Bücher".($aktion->capacity ? " von {$aktion->capacity}" : ''))
                ->url(OrderResource::getUrl()),
            Stat::make('Bezahlt', $chf($revenue))
                ->description($paid->count().' Bestellungen'.($inParcel ? ' · '.$chf($inParcel).' per Rechnung im Paket' : '')),
            Stat::make('Zahlung offen', (clone $unpaid)->count())
                ->description($chf((clone $unpaid)->sum('total')))
                ->color((clone $unpaid)->exists() ? 'warning' : 'gray')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'pending_payment'])),
            Stat::make('Geschenke', (clone $orders)->where('is_gift', true)->count())
                ->description('an andere Adressen'),
        ];
    }
}
