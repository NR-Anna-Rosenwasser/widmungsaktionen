<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\TenantRole;
use App\Filament\Widgets\Concerns\ResolvesAktion;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class BooksChart extends ChartWidget
{
    use ResolvesAktion;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Bücher';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $aktion = $this->aktion();
        if (! $aktion) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = OrderItem::query()
            ->join('books', 'books.id', '=', 'order_items.book_id')
            ->whereIn('order_id', $aktion->orders()->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled])->select('id'))
            ->selectRaw('books.title, count(*) as copies, sum(order_items.unit_price) as revenue')
            ->groupBy('books.title', 'books.sort')
            ->orderBy('books.sort')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Exemplare',
                    'data' => $rows->pluck('copies')->map(fn ($n) => (int) $n)->all(),
                    'backgroundColor' => '#D989B2',
                    'borderRadius' => 3,
                ],
            ],
            'labels' => $rows->map(fn ($row) => $row->title.' (CHF '.number_format($row->revenue / 100, 0, '.', "'").')')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
