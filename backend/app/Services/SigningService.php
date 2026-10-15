<?php

namespace App\Services;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Events\OrderSigned;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The signing stack of an Aktion: post-it numbers and the order in which
 * copies are signed.
 */
class SigningService
{
    /**
     * What stands between an Aktion and signing day.
     *
     * @return array{ready: int, stacked: int, pending_moderation: int, flagged: int, unpaid: int}
     */
    public function readiness(Aktion $aktion): array
    {
        $confirmed = $aktion->orders()->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Signed]);
        $items = fn (ModerationStatus $status) => OrderItem::query()
            ->whereIn('order_id', (clone $confirmed)->select('id'))
            ->where('moderation_status', $status)
            ->count();

        return [
            'ready' => (clone $confirmed)->whereNull('stack_number')->count(),
            'stacked' => (clone $confirmed)->whereNotNull('stack_number')->count(),
            'pending_moderation' => $items(ModerationStatus::Pending),
            'flagged' => $items(ModerationStatus::Flagged),
            'unpaid' => $aktion->orders()->where('status', OrderStatus::PendingPayment)->count(),
        ];
    }

    /**
     * Give every confirmed, fully approved order without number the next
     * post-it number. Can be run again for orders paid later.
     *
     * @return int Number of orders added to the stack
     */
    public function prepare(Aktion $aktion, ?User $by = null): int
    {
        if ($this->readiness($aktion)['pending_moderation'] > 0) {
            throw new RuntimeException('Es gibt noch Widmungswünsche, die nicht freigegeben sind.');
        }

        return DB::transaction(function () use ($aktion, $by) {
            Aktion::query()->lockForUpdate()->findOrFail($aktion->id);

            $next = (int) $aktion->orders()->max('stack_number');
            $orders = $aktion->orders()
                ->where('status', OrderStatus::Confirmed)
                ->whereNull('stack_number')
                // Orders with a query on a dedication wait until it is resolved.
                ->whereDoesntHave('items', fn ($q) => $q->where('moderation_status', '!=', ModerationStatus::Approved))
                ->with('items.book')
                ->get()
                // Group by book so the stack on the table is sorted.
                ->sortBy(fn (Order $order) => sprintf(
                    '%05d-%s-%010d',
                    $order->items->min(fn ($item) => $item->book->sort),
                    $order->submitted_at?->format('YmdHis'),
                    $order->id,
                ));

            foreach ($orders as $order) {
                $order->update(['stack_number' => ++$next]);
                $order->log('stacked', ['stack_number' => $next], user: $by);
            }

            if (in_array($aktion->status, [AktionStatus::Open, AktionStatus::Closed, AktionStatus::Scheduled], true)) {
                $aktion->update(['status' => AktionStatus::Signing]);
            }

            return $orders->count();
        });
    }

    /**
     * Copies on the stack, in signing order.
     */
    public function queue(Aktion $aktion): Builder
    {
        return OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.aktion_id', $aktion->id)
            ->whereNotNull('orders.stack_number')
            ->whereIn('orders.status', [OrderStatus::Confirmed, OrderStatus::Signed])
            ->orderBy('orders.stack_number')
            ->orderBy('order_items.position')
            ->with('order', 'book');
    }

    /**
     * The next copy to sign: unsigned and without open query.
     */
    public function current(Aktion $aktion): ?OrderItem
    {
        return $this->queue($aktion)
            ->whereNull('order_items.signed_at')
            ->where('order_items.moderation_status', ModerationStatus::Approved)
            ->first();
    }

    /**
     * Copies around the given one, for finding one's place in the stack.
     *
     * @return Collection<int, OrderItem>
     */
    public function around(Aktion $aktion, OrderItem $item, int $radius = 2): Collection
    {
        $all = $this->queue($aktion)->get();
        $index = $all->search(fn (OrderItem $other) => $other->id === $item->id);

        return $all->slice(max(0, $index - $radius), $radius * 2 + 1)->values();
    }

    /**
     * @return array{signed: int, total: int}
     */
    public function progress(Aktion $aktion): array
    {
        $queue = $this->queue($aktion);

        return [
            'signed' => (clone $queue)->whereNotNull('order_items.signed_at')->count(),
            'total' => $queue->count(),
        ];
    }

    public function sign(OrderItem $item, User $by): void
    {
        $order = DB::transaction(function () use ($item, $by) {
            // Guard against double key presses.
            $updated = OrderItem::query()
                ->whereKey($item->id)
                ->whereNull('signed_at')
                ->update(['signed_at' => now(), 'signed_by' => $by->id]);

            if (! $updated) {
                return null;
            }

            $order = $item->order()->lockForUpdate()->first();
            if ($order->status === OrderStatus::Confirmed && $order->items()->whereNull('signed_at')->doesntExist()) {
                $order->update(['status' => OrderStatus::Signed]);
                $order->log('signed', public: true, user: $by);

                return $order;
            }

            return null;
        });

        if ($order) {
            OrderSigned::dispatch($order);
        }
    }

    public function unsign(OrderItem $item, User $by): void
    {
        DB::transaction(function () use ($item, $by) {
            $item->update(['signed_at' => null, 'signed_by' => null]);
            $order = $item->order;
            $order->log('item_unsigned', ['item' => $item->id], user: $by);

            if ($order->status === OrderStatus::Signed) {
                $order->update(['status' => OrderStatus::Confirmed]);
            }
        });
    }

    /**
     * Put a copy aside with a query; it disappears from the signing queue
     * until approved again on the moderation page.
     */
    public function flag(OrderItem $item, User $by, ?string $note): void
    {
        $item->update(['moderation_status' => ModerationStatus::Flagged, 'moderation_note' => $note ?: 'Beim Widmen markiert']);
        $item->order->log('moderation_flagged', ['item' => $item->id, 'note' => $note, 'while_signing' => true], user: $by);
    }
}
