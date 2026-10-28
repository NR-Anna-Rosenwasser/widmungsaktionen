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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
     * @return array{ready: int, stacked: int, pending_moderation: int, flagged: int, unpaid: int, deadline_passed: bool}
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
            // Test Aktionen can be prepared any time, to click through without waiting.
            'deadline_passed' => $aktion->is_test || $aktion->order_deadline->isPast(),
        ];
    }

    /**
     * Why «Widmungsaktion vorbereiten» can't be pressed (null: it can).
     */
    public function cannotPrepare(Aktion $aktion): ?string
    {
        $r = $this->readiness($aktion);

        return match (true) {
            $aktion->prepared_at !== null => 'Diese Aktion ist bereits vorbereitet.',
            ! $r['deadline_passed'] => 'Der Bestellschluss ist noch nicht vorbei.',
            $r['pending_moderation'] > 0 => 'Es gibt noch Widmungswünsche, die nicht geprüft sind.',
            $r['ready'] === 0 => 'Es gibt keine bestätigten Bestellungen.',
            default => null,
        };
    }

    /**
     * Books to prepare per title, in catalogue order: «Herz» => 42.
     *
     * @param  EloquentCollection<int, Order>  $orders
     * @return Collection<string, int>
     */
    public function bookCounts(EloquentCollection $orders): Collection
    {
        return $orders
            ->loadMissing('items.book')
            ->flatMap(fn (Order $order) => $order->items)
            ->sortBy(fn (OrderItem $item) => [$item->book->sort, $item->book->title])
            ->countBy(fn (OrderItem $item) => $item->book->title);
    }

    /**
     * «42× Herz · 17× Rosa Buch»
     */
    public function bookCountsLine(EloquentCollection $orders): string
    {
        return $this->bookCounts($orders)->map(fn (int $count, string $title) => "{$count}× {$title}")->join(' · ');
    }

    /**
     * Confirmed orders that «Widmungsaktion vorbereiten» would number now.
     *
     * @return EloquentCollection<int, Order>
     */
    public function unstacked(Aktion $aktion): EloquentCollection
    {
        return $aktion->orders()->where('status', OrderStatus::Confirmed)->whereNull('stack_number')->with('items.book')->get();
    }

    /**
     * «Widmungsaktion vorbereiten», once per Aktion after the order deadline:
     * every confirmed order gets a post-it number. Orders with an open query
     * get the last numbers: they lie at the end of the stack and are signed
     * once the query is resolved, nothing has to be put aside. Orders paid
     * later are added one by one (addToStack).
     *
     * @return int Number of orders on the stack
     */
    public function prepare(Aktion $aktion, ?User $by = null): int
    {
        if ($reason = $this->cannotPrepare($aktion)) {
            throw new RuntimeException($reason);
        }

        return DB::transaction(function () use ($aktion, $by) {
            Aktion::query()->lockForUpdate()->findOrFail($aktion->id);

            $next = 0;
            $orders = $aktion->orders()
                ->where('status', OrderStatus::Confirmed)
                ->whereNull('stack_number')
                ->with('items.book')
                ->get()
                // Open queries last, then grouped by book so the stack on the table is sorted.
                ->sortBy(fn (Order $order) => sprintf(
                    '%d-%05d-%s-%010d',
                    $order->items->contains(fn ($item) => $item->moderation_status === ModerationStatus::Flagged),
                    $order->items->min(fn ($item) => $item->book->sort),
                    $order->submitted_at?->format('YmdHis'),
                    $order->id,
                ));

            foreach ($orders as $order) {
                $order->update(['stack_number' => ++$next]);
                $order->log('stacked', ['stack_number' => $next], user: $by);
            }

            $aktion->update(array_filter([
                'prepared_at' => now(),
                'status' => in_array($aktion->status, [AktionStatus::Open, AktionStatus::Closed, AktionStatus::Scheduled], true) ? AktionStatus::Signing : null,
            ]));

            return $orders->count();
        });
    }

    /**
     * Undo «Widmungsaktion vorbereiten» (e.g. pressed too early), as long as
     * no book has been signed: the post-it numbers are removed.
     */
    public function canUnprepare(Aktion $aktion): bool
    {
        return $aktion->prepared_at !== null
            && OrderItem::query()->whereIn('order_id', $aktion->orders()->select('id'))->whereNotNull('signed_at')->doesntExist();
    }

    public function unprepare(Aktion $aktion, ?User $by = null): void
    {
        if (! $this->canUnprepare($aktion)) {
            throw new RuntimeException('Es wurde schon gewidmet; die Vorbereitung lässt sich nicht mehr zurücksetzen.');
        }

        DB::transaction(function () use ($aktion, $by) {
            $aktion->orders()->whereNotNull('stack_number')->each(function (Order $order) use ($by) {
                $order->log('unstacked', ['stack_number' => $order->stack_number], user: $by);
                $order->update(['stack_number' => null]);
            });

            $aktion->update([
                'prepared_at' => null,
                'status' => $aktion->status === AktionStatus::Signing
                    ? ($aktion->order_deadline->isPast() ? AktionStatus::Closed : AktionStatus::Open)
                    : $aktion->status,
            ]);
        });
    }

    /**
     * Put a single confirmed order on a prepared stack, with the next number
     * (e.g. a bank transfer that arrived after preparing).
     */
    public function addToStack(Order $order, ?User $by = null): int
    {
        if (! $order->aktion->prepared_at || $order->stack_number || $order->status !== OrderStatus::Confirmed) {
            throw new RuntimeException("Bestellung {$order->number} kann nicht auf den Stapel.");
        }

        return DB::transaction(function () use ($order, $by) {
            Aktion::query()->lockForUpdate()->findOrFail($order->aktion_id);
            $next = (int) $order->aktion->orders()->max('stack_number') + 1;
            $order->update(['stack_number' => $next]);
            $order->log('stacked', ['stack_number' => $next, 'late' => true], user: $by);

            return $next;
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
     * Copies still to sign, in the order they come up: the rest of the stack
     * first, then copies the signing has already passed (a query resolved
     * later), so nobody has to dig back into the stack. An order with an open
     * query is held as a whole: its books stay together under one post-it.
     *
     * @return Collection<int, OrderItem>
     */
    public function toSign(Aktion $aktion): Collection
    {
        $queue = $this->queue($aktion)->get()->values();
        $front = $this->front($queue);
        $held = $this->heldOrderIds($queue);

        [$ahead, $passed] = $queue
            ->filter(fn (OrderItem $item) => ! $item->signed_at && ! $held->contains($item->order_id))
            ->partition(fn (OrderItem $item, int $index) => $index > $front);

        return $ahead->concat($passed)->values();
    }

    /**
     * The next copy to sign.
     */
    public function current(Aktion $aktion): ?OrderItem
    {
        return $this->toSign($aktion)->first();
    }

    /**
     * Whether the signing has already passed this copy («Nachgeholt»).
     */
    public function isPassed(Aktion $aktion, OrderItem $item): bool
    {
        $queue = $this->queue($aktion)->get()->values();

        return $queue->search(fn (OrderItem $other) => $other->id === $item->id) < $this->front($queue);
    }

    /**
     * Orders with an open query that the signing skips on its way to the
     * given copy: they lie in front of it on the stack and must be put aside.
     *
     * @return Collection<int, Order>
     */
    public function heldBefore(Aktion $aktion, OrderItem $item): Collection
    {
        $queue = $this->queue($aktion)->get()->values();
        $front = $this->front($queue);
        $held = $this->heldOrderIds($queue);
        $index = $queue->search(fn (OrderItem $other) => $other->id === $item->id);

        return $queue
            ->filter(fn (OrderItem $other, int $i) => $i > $front && $i < $index && ! $other->signed_at && $held->contains($other->order_id))
            ->map(fn (OrderItem $other) => $other->order)
            ->unique('id')
            ->values();
    }

    /**
     * Orders on the stack with an open query and books still to sign, in
     * stack order, with the flagged copies.
     *
     * @return Collection<int, Order>
     */
    public function heldOrders(Aktion $aktion): Collection
    {
        return $aktion->orders()
            ->whereNotNull('stack_number')
            ->where('status', OrderStatus::Confirmed)
            ->whereHas('items', fn ($q) => $q->where('moderation_status', ModerationStatus::Flagged))
            ->whereHas('items', fn ($q) => $q->whereNull('signed_at'))
            ->with(['items' => fn ($q) => $q->orderBy('position'), 'items.book'])
            ->orderBy('stack_number')
            ->get();
    }

    /**
     * Orders in the queue with a copy that isn't approved (query, or not yet checked).
     *
     * @param  Collection<int, OrderItem>  $queue
     * @return Collection<int, int>
     */
    protected function heldOrderIds(Collection $queue): Collection
    {
        return $queue->where('moderation_status', '!==', ModerationStatus::Approved)->pluck('order_id')->unique()->values();
    }

    /**
     * Index of the last signed copy in the queue (-1 if none).
     */
    protected function front(Collection $queue): int
    {
        return $queue->filter(fn (OrderItem $item) => $item->signed_at)->keys()->max() ?? -1;
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
     * Mark a copy with a query; its whole order leaves the signing queue
     * until approved again on the moderation page, then comes up at the end.
     */
    public function flag(OrderItem $item, User $by, ?string $note): void
    {
        $item->update(['moderation_status' => ModerationStatus::Flagged, 'moderation_note' => $note ?: 'Beim Widmen markiert', 'flagged_at' => now()]);
        $item->order->log('moderation_flagged', ['item' => $item->id, 'note' => $note, 'while_signing' => true], user: $by);
    }
}
