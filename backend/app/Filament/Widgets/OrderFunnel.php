<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantRole;
use App\Filament\Widgets\Concerns\ResolvesAktion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * From starting the order form to paying, and where unfinished orders stop.
 */
class OrderFunnel extends StatsOverviewWidget
{
    use ResolvesAktion;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Bestellformular';

    protected int|array|null $columns = 2;

    protected const STEPS = [
        'email' => 'E-Mail',
        'dedication' => 'Widmung',
        'address' => 'Adresse',
        'payment' => 'Zahlung',
        'summary' => 'Übersicht',
    ];

    public static function canView(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    protected function getStats(): array
    {
        $aktion = $this->aktion();
        if (! $aktion) {
            return [];
        }

        // Drafts are deleted after 30 days, so this is most meaningful for running Aktionen.
        $started = $aktion->orders()->count();
        $submitted = $aktion->orders()->whereNotNull('submitted_at')->count();
        // Same definition as the overview: paid and not cancelled.
        $paid = $aktion->orders()
            ->whereNot('status', OrderStatus::Cancelled)
            ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
            ->count();
        $percent = fn (int $part) => $started ? round($part / $started * 100).'%' : '–';

        $dropOff = $aktion->orders()
            ->where('status', OrderStatus::Draft)
            ->selectRaw('current_step, count(*) as total')
            ->groupBy('current_step')
            ->orderByDesc('total')
            ->first();

        return [
            Stat::make('Begonnen', $started)->description('mit E-Mail-Adresse'),
            Stat::make('Abgeschickt', $submitted)->description($percent($submitted).' der Begonnenen'),
            Stat::make('Bezahlt', $paid)->description($percent($paid).' der Begonnenen'),
            Stat::make('Nicht abgeschlossen', $started - $submitted)
                ->description($dropOff ? 'meist beim Schritt «'.(self::STEPS[$dropOff->current_step] ?? $dropOff->current_step ?? '?').'»' : 'keine'),
        ];
    }
}
