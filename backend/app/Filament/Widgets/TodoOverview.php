<?php

namespace App\Filament\Widgets;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Filament\Pages\Moderation;
use App\Filament\Pages\Versand;
use App\Filament\Pages\Widmen;
use App\Filament\Widgets\Concerns\ResolvesAktion;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What needs doing next, with links into the workflow.
 */
class TodoOverview extends StatsOverviewWidget
{
    use ResolvesAktion;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Zu tun';

    protected function getStats(): array
    {
        $aktion = $this->aktion();
        if (! $aktion) {
            return [];
        }

        $active = $aktion->orders()->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled])->select('id');
        $items = fn () => OrderItem::query()->whereIn('order_id', $active);

        $toReview = $items()->where('moderation_status', ModerationStatus::Pending)->count();
        $flagged = $items()->where('moderation_status', ModerationStatus::Flagged)->count();
        $toSign = $items()
            ->whereNull('signed_at')
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Confirmed))
            ->count();
        $notStacked = $aktion->orders()->where('status', OrderStatus::Confirmed)->whereNull('stack_number')->count();
        $toShip = $aktion->orders()->where('status', OrderStatus::Signed)->count();
        // After the deadline: orders waiting for a decision in «Offene Bestellungen».
        $undecided = 0;
        if ($aktion->order_deadline->isPast()) {
            $undecided = $aktion->orders()->where('status', OrderStatus::PendingPayment)->count()
                + ($aktion->prepared_at ? $notStacked : 0);
        }

        return [
            Stat::make('Widmungswünsche prüfen', $toReview)
                ->description($flagged ? "$flagged mit Rückfrage" : 'Namen und Wünsche freigeben')
                ->color($toReview || $flagged ? 'warning' : 'success')
                ->url(Moderation::getUrl()),
            Stat::make('Bücher zu widmen', $toSign)
                ->description($notStacked ? "$notStacked Bestellungen noch ohne Post-it-Nummer" : 'Stapel vorbereitet')
                ->color($toSign ? 'primary' : 'success')
                ->url(Widmen::getUrl(['aktion' => $aktion->id])),
            Stat::make('Bereit zum Versand', $toShip)
                ->description('gewidmet, noch nicht versendet')
                ->color($toShip ? 'primary' : 'gray')
                ->url(Versand::getUrl(['aktion' => $aktion->id])),
            ...($undecided ? [
                Stat::make('Offene Bestellungen', $undecided)
                    ->description('unbezahlt oder noch ohne Post-it: entscheiden')
                    ->color('warning')
                    ->url(Versand::getUrl(['aktion' => $aktion->id])),
            ] : []),
        ];
    }
}
