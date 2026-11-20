<?php

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderSubmitted;
use App\Exceptions\OrderNotAllowed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\Dedication;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Orders edited or entered by hand in the admin, e.g. to fix an order that
 * should have gone to two addresses: change the address, or cancel it and
 * enter replacement orders that are already paid.
 */
class AdminOrders
{
    /**
     * Contact, addresses and gift fields that can be edited after submitting.
     */
    public const FIELDS = [
        'email', 'phone', 'locale',
        'billing_company', 'billing_first_name', 'billing_last_name', 'billing_street', 'billing_postal_code', 'billing_town', 'billing_country',
        'is_gift',
        'shipping_company', 'shipping_first_name', 'shipping_last_name', 'shipping_street', 'shipping_postal_code', 'shipping_town', 'shipping_country',
        'gift_message',
    ];

    public function __construct(
        protected OrderPricer $pricer,
        protected PaymentService $payments,
        protected SigningService $signing,
    ) {}

    public static function canEdit(Order $order): bool
    {
        return $order->status !== OrderStatus::Draft;
    }

    /**
     * @param  array<string, mixed>  $data  FIELDS, `items` (id => signature_only, recipient_name, wish), `dedications_checked`
     */
    public function update(Order $order, array $data, User $by): void
    {
        if (! self::canEdit($order)) {
            throw new OrderNotAllowed('Entwürfe können nicht bearbeitet werden.');
        }

        $this->assertGiftAllowed((bool) ($data['is_gift'] ?? $order->is_gift), $order->payment_method);

        DB::transaction(function () use ($order, $data, $by) {
            $tenant = $order->tenant;
            $order->fill(self::attributes($data));
            $changed = array_keys($order->getDirty());
            $order->save();

            foreach ($data['items'] ?? [] as $values) {
                /** @var OrderItem|null $item */
                $item = $order->items()->find($values['id'] ?? null);
                if (! $item || $item->signed_at) {
                    continue;
                }

                $item->fill(Dedication::normalize($tenant, $values));
                if (! $item->isDirty()) {
                    continue;
                }

                $item->moderation_status = ($data['dedications_checked'] ?? false)
                    ? ModerationStatus::Approved
                    : Dedication::moderationStatus($tenant, $item->only(['recipient_name', 'wish']));
                $item->save();
                $changed[] = "dedication_{$item->position}";
            }

            if ($changed) {
                $order->log('edited', ['fields' => $changed], user: $by);
            }
        });
    }

    /**
     * Enter a new, submitted order. Paid orders (e.g. replacing a cancelled
     * order that wasn't refunded) are confirmed right away.
     *
     * @param  array<string, mixed>  $data  aktion_id, FIELDS, payment_method, `items` (book_id, signature_only,
     *                                      recipient_name, wish), dedications_checked, shipping_cost (CHF, optional),
     *                                      mark_paid, paid_at, paid_note, add_to_stack, notify, source_order_id
     */
    public function create(Tenant $tenant, array $data, User $by): Order
    {
        $aktion = $tenant->aktionen()->findOrFail($data['aktion_id']);
        $source = filled($data['source_order_id'] ?? null) ? $tenant->orders()->find($data['source_order_id']) : null;
        $method = PaymentMethod::from($data['payment_method'] instanceof PaymentMethod ? $data['payment_method']->value : $data['payment_method']);
        $this->assertGiftAllowed((bool) ($data['is_gift'] ?? false), $method);

        if (empty($data['items'])) {
            throw new OrderNotAllowed('Mindestens ein Buch auswählen.');
        }

        $order = DB::transaction(function () use ($tenant, $aktion, $data, $method, $source, $by) {
            /** @var Order $order */
            $order = $tenant->orders()->create([
                ...self::attributes($data),
                'aktion_id' => $aktion->id,
                'locale' => $tenant->resolveLocale($data['locale'] ?? null),
                'payment_method' => $method,
                'number' => $tenant->nextOrderNumber(test: $aktion->is_test),
                'status' => $method === PaymentMethod::InvoiceInParcel ? OrderStatus::Confirmed : OrderStatus::PendingPayment,
                'submitted_at' => now(),
            ]);

            foreach (array_values($data['items']) as $index => $copy) {
                $dedication = Dedication::normalize($tenant, $copy);
                $order->items()->create([
                    'book_id' => $copy['book_id'],
                    'unit_price' => 0,
                    'position' => $index + 1,
                    ...$dedication,
                    'moderation_status' => ($data['dedications_checked'] ?? false)
                        ? ModerationStatus::Approved
                        : Dedication::moderationStatus($tenant, $dedication),
                ]);
            }

            $this->pricer->price($order);
            if (filled($data['shipping_cost'] ?? null)) {
                $order->shipping_cost = (int) round((float) $data['shipping_cost'] * 100);
                $order->total = $order->subtotal + $order->shipping_cost;
            }
            $order->save();

            $order->log('submitted', ['payment_method' => $method->value], public: true);
            $order->log('created_by_admin', array_filter(['from' => $source?->number]), user: $by);
            $source?->log('replacement_created', ['number' => $order->number], user: $by);

            return $order;
        });

        if ($data['mark_paid'] ?? false) {
            // Silently: the customer gets one confirmation (below) that already shows it as paid.
            $this->payments->markOrderPaid($order, Carbon::parse($data['paid_at'] ?? now()), $by, $data['paid_note'] ?? null, notify: false);
        } elseif ($method !== PaymentMethod::Online) {
            // The QR invoice. Online orders are paid from the tracking page.
            $this->payments->initiate($order);
        }

        $order->refresh();

        if (($data['add_to_stack'] ?? false) && $aktion->prepared_at && $order->status === OrderStatus::Confirmed) {
            $this->signing->addToStack($order, $by);
        }

        if ($data['notify'] ?? false) {
            OrderSubmitted::dispatch($order, confirmNow: true);
        }

        return $order->refresh();
    }

    /**
     * The editable fields present in $data. An empty country keeps the
     * stored one (or the database default «CH» for new orders).
     *
     * @return array<string, mixed>
     */
    protected static function attributes(array $data): array
    {
        return array_filter(
            Arr::only($data, self::FIELDS),
            fn ($value, string $key) => ! (str_ends_with($key, '_country') && blank($value)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    protected function assertGiftAllowed(bool $isGift, ?PaymentMethod $method): void
    {
        if ($isGift && $method === PaymentMethod::InvoiceInParcel) {
            throw new OrderNotAllowed('Bei Geschenken ist «Rechnung im Paket» nicht möglich.');
        }
    }
}
