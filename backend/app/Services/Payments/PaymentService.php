<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Events\OrderCancelled;
use App\Events\OrderPaid;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * Payment methods a tenant offers; online only with a configured gateway.
     *
     * @return array<int, string>
     */
    public function availableMethods(Tenant $tenant): array
    {
        return collect($tenant->setting('payment_methods'))
            ->filter(fn ($method) => PaymentMethod::tryFrom($method) !== null)
            ->reject(fn ($method) => $method === PaymentMethod::Online->value && ! $this->gateway->isConfigured($tenant))
            ->values()
            ->all();
    }

    public function canPayOnline(Order $order): bool
    {
        return $order->status === OrderStatus::PendingPayment
            && in_array(PaymentMethod::Online->value, $this->availableMethods($order->tenant), true);
    }

    /**
     * Create the payment record after an order was submitted. Invoices get a
     * QR reference; online payments get a checkout URL to redirect to.
     *
     * @return string|null Checkout URL for online payments
     */
    public function initiate(Order $order): ?string
    {
        if ($order->payment_method === PaymentMethod::Online) {
            return $this->startCheckout($order);
        }

        $order->payments()->create([
            'method' => $order->payment_method,
            'amount' => $order->total,
            'status' => PaymentState::Pending,
            'reference' => PaymentReference::for($order),
        ]);

        return null;
    }

    /**
     * Start (or restart) an online payment, e.g. from the tracking page.
     */
    public function startCheckout(Order $order): string
    {
        if (! $this->canPayOnline($order)) {
            throw new RuntimeException('Online payment is not available for this order.');
        }

        // Older unfinished checkouts of this order are superseded.
        $order->payments()
            ->where('method', PaymentMethod::Online)
            ->where('status', PaymentState::Pending)
            ->update(['status' => PaymentState::Expired]);

        $payment = $order->payments()->create([
            'method' => PaymentMethod::Online,
            'amount' => $order->total,
            'status' => PaymentState::Pending,
        ]);

        $tracking = $order->storefrontUrl();
        $url = $this->gateway->createCheckout($order, $payment, "$tracking?neu=1&bezahlt=1", "$tracking?neu=1");
        $order->log('checkout_started');

        return $url;
    }

    /**
     * Mark a payment (and its order) as paid. Safe to call more than once.
     */
    public function markPaid(Payment $payment, ?CarbonInterface $at = null, ?User $by = null, array $meta = []): void
    {
        $paid = DB::transaction(function () use ($payment, $at, $by, $meta) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === PaymentState::Paid) {
                return false;
            }

            $payment->update([
                'status' => PaymentState::Paid,
                'paid_at' => $at ?? now(),
                'meta' => array_merge($payment->meta ?? [], $meta),
            ]);

            $order = $payment->order;

            // Any other open payment of this order (e.g. the invoice) is no longer needed.
            $order->payments()
                ->whereKeyNot($payment->id)
                ->where('status', PaymentState::Pending)
                ->update(['status' => PaymentState::Cancelled]);

            $order->payment_status = PaymentStatus::Paid;
            if ($order->status === OrderStatus::PendingPayment) {
                $order->status = OrderStatus::Confirmed;
            }
            $order->save();

            $order->log('payment_received', ['method' => $payment->method->value, 'amount' => $payment->amount], public: true, user: $by);

            return true;
        });

        if ($paid) {
            OrderPaid::dispatch($payment->order);
        }
    }

    /**
     * Record a bank transfer or cash payment entered by an admin.
     */
    public function markOrderPaid(Order $order, CarbonInterface $at, User $by, ?string $note = null): void
    {
        $payment = $order->payments()
            ->whereIn('method', [PaymentMethod::InvoiceAdvance, PaymentMethod::InvoiceInParcel])
            ->where('status', PaymentState::Pending)
            ->latest()
            ->first();

        $payment ??= $order->payments()->create([
            'method' => $order->payment_method ?? PaymentMethod::InvoiceAdvance,
            'amount' => $order->total,
            'status' => PaymentState::Pending,
            'reference' => PaymentReference::for($order),
        ]);

        $this->markPaid($payment, $at, $by, array_filter(['note' => $note, 'recorded_by' => $by->id]));
    }

    /**
     * Refund (part of) a paid order. Online payments are refunded through
     * Stripe; bank payments are only recorded (the money is sent manually).
     */
    public function refund(Order $order, int $amount, ?string $reason, User $by): void
    {
        DB::transaction(function () use ($order, $amount, $reason, $by) {
            // Locked so a Stripe refund webhook arriving meanwhile waits and sees our booking.
            $payment = $order->payments()
                ->where('status', PaymentState::Paid)
                ->latest('paid_at')
                ->lockForUpdate()
                ->firstOrFail();
            $refundable = $payment->amount - $payment->refunded_amount;

            if ($amount < 1 || $amount > $refundable) {
                throw new RuntimeException('Maximal CHF '.number_format($refundable / 100, 2, '.', '').' können erstattet werden.');
            }

            $refundId = $payment->method === PaymentMethod::Online && $payment->stripe_payment_intent_id
                ? $this->gateway->refund($payment, $amount)
                : null;

            $this->bookRefund($payment, $amount, array_filter([
                'reason' => $reason,
                'stripe_refund_id' => $refundId,
                'manual' => $refundId === null,
            ]), $by);
        });

        OrderRefunded::dispatch($order->refresh(), $amount);
    }

    /**
     * Sync refunds made in the Stripe dashboard (charge.refunded webhook).
     */
    public function syncStripeRefunds(string $paymentIntentId, int $amountRefunded): void
    {
        [$order, $missing] = DB::transaction(function () use ($paymentIntentId, $amountRefunded) {
            $payment = Payment::query()->where('stripe_payment_intent_id', $paymentIntentId)->lockForUpdate()->first();
            $missing = $payment ? $amountRefunded - $payment->refunded_amount : 0;

            if ($missing <= 0) {
                return [null, 0];
            }

            $this->bookRefund($payment, $missing, ['source' => 'stripe_dashboard']);

            return [$payment->order, $missing];
        });

        if ($order) {
            OrderRefunded::dispatch($order, $missing);
        }
    }

    protected function bookRefund(Payment $payment, int $amount, array $data = [], ?User $by = null): void
    {
        $payment->increment('refunded_amount', $amount);
        $order = $payment->order;
        $order->payment_status = $payment->refunded_amount >= $payment->amount
            ? PaymentStatus::Refunded
            : PaymentStatus::PartiallyRefunded;
        $order->save();
        $order->log('refunded', ['amount' => $amount, ...$data], public: true, user: $by);
    }

    public function cancel(Order $order, ?string $reason, ?User $by = null, bool $notify = true): void
    {
        DB::transaction(function () use ($order, $reason, $by) {
            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $order->payments()->where('status', PaymentState::Pending)->update(['status' => PaymentState::Cancelled]);
            $order->log('cancelled', array_filter(['reason' => $reason]), public: true, user: $by);
        });

        if ($notify) {
            OrderCancelled::dispatch($order);
        }
    }

    /**
     * The manually recorded payment that can still be undone, if any:
     * bank payments only (online payments are refunded instead), nothing refunded yet.
     */
    public function revertablePayment(Order $order): ?Payment
    {
        return $order->payments()
            ->where('status', PaymentState::Paid)
            ->where('method', '!=', PaymentMethod::Online)
            ->where('refunded_amount', 0)
            ->latest('paid_at')
            ->first();
    }

    /**
     * Undo "mark as paid", e.g. when the wrong order was marked.
     */
    public function revertPayment(Order $order, User $by, ?string $reason = null): void
    {
        $payment = $this->revertablePayment($order)
            ?? throw new RuntimeException('Diese Zahlung kann nicht zurückgesetzt werden.');

        DB::transaction(function () use ($order, $payment, $by, $reason) {
            $payment->update([
                'status' => PaymentState::Pending,
                'paid_at' => null,
                'meta' => array_merge($payment->meta ?? [], ['reverted_at' => now()->toIso8601String(), 'reverted_by' => $by->id]),
            ]);

            $order->payment_status = PaymentStatus::Unpaid;
            // Invoice-in-parcel orders are confirmed without payment, all others wait for it again.
            if ($order->status === OrderStatus::Confirmed && $order->payment_method !== PaymentMethod::InvoiceInParcel) {
                $order->status = OrderStatus::PendingPayment;
            }
            $order->save();

            $order->log('payment_reverted', array_filter(['amount' => $payment->amount, 'reason' => $reason]), user: $by);
        });
    }

    public function canReopen(Order $order): bool
    {
        return $order->status === OrderStatus::Cancelled
            && ! in_array($order->payment_status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true);
    }

    /**
     * Undo a cancellation. The order goes back to where it was before.
     */
    public function reopen(Order $order, User $by): void
    {
        if (! $this->canReopen($order)) {
            throw new RuntimeException('Diese Bestellung kann nicht wiederhergestellt werden.');
        }

        DB::transaction(function () use ($order, $by) {
            $paid = $order->payment_status === PaymentStatus::Paid;

            if (! $paid && $order->payment_method !== PaymentMethod::Online) {
                // Reactivate the invoice so it can be paid (and printed) again.
                $order->payments()
                    ->where('status', PaymentState::Cancelled)
                    ->whereNotNull('reference')
                    ->latest()
                    ->first()
                    ?->update(['status' => PaymentState::Pending]);
            }

            $order->update([
                'status' => $paid || $order->payment_method === PaymentMethod::InvoiceInParcel
                    ? OrderStatus::Confirmed
                    : OrderStatus::PendingPayment,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]);

            $order->log('reopened', public: true, user: $by);
        });
    }
}
