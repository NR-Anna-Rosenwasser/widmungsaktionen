<?php

namespace App\Http\Controllers\Api;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Events\OrderSubmitted;
use App\Services\OrderPricer;
use App\Services\Payments\PaymentService;
use App\Services\Pdf\InvoicePdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderPricer $pricer,
        protected PaymentService $payments,
    ) {}

    /**
     * Create a draft as soon as the customer has entered their email address.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($tenant->is_active, 404);

        $aktion = $this->acceptingAktion($tenant);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'locale' => ['nullable', Rule::in(['de', 'en'])],
            ...$this->copiesRules($tenant, $aktion, required: true),
        ]);

        $order = DB::transaction(function () use ($tenant, $aktion, $data) {
            $order = $tenant->orders()->create([
                'aktion_id' => $aktion->id,
                'email' => $data['email'],
                'locale' => $data['locale'] ?? 'de',
                'current_step' => 'email',
            ]);

            $this->syncCopies($order, $data['copies']);
            $this->pricer->price($order)->save();
            $order->log('draft_created');

            return $order;
        });

        return $this->respond($order, 201);
    }

    public function show(Tenant $tenant, string $token): JsonResponse
    {
        return $this->respond($this->findOrder($tenant, $token));
    }

    /**
     * Save the form state after each step. Only drafts can be changed.
     */
    public function update(Request $request, Tenant $tenant, string $token): JsonResponse
    {
        $order = $this->findOrder($tenant, $token);
        abort_unless($order->status === OrderStatus::Draft, 409, 'Diese Bestellung wurde bereits abgeschickt.');

        $data = $request->validate([
            'email' => ['sometimes', 'email:rfc', 'max:255'],
            'current_step' => ['sometimes', 'nullable', 'string', 'max:30'],
            'locale' => ['sometimes', Rule::in(['de', 'en'])],
            ...$this->copiesRules($tenant, $order->aktion, required: false),
            ...$this->addressRules('billing', strict: false),
            'is_gift' => ['sometimes', 'boolean'],
            ...$this->addressRules('shipping', strict: false),
            'gift_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'payment_method' => ['sometimes', 'nullable', Rule::in($this->paymentMethods($tenant))],
            'newsletter_opt_in' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($order, $data) {
            if (array_key_exists('copies', $data)) {
                $this->syncCopies($order, $data['copies']);
            }

            foreach (['billing', 'shipping'] as $prefix) {
                foreach (Arr::get($data, $prefix) ?? [] as $field => $value) {
                    $order->{"{$prefix}_{$field}"} = $value;
                }
            }

            $order->fill(Arr::only($data, ['email', 'current_step', 'locale', 'is_gift', 'gift_message', 'payment_method', 'newsletter_opt_in']))->save();
            $this->pricer->price($order->refresh())->save();
        });

        return $this->respond($order);
    }

    public function submit(Tenant $tenant, string $token): JsonResponse
    {
        $order = $this->findOrder($tenant, $token);
        abort_unless($order->status === OrderStatus::Draft, 409, 'Diese Bestellung wurde bereits abgeschickt.');

        // A draft started in an Aktion that has since closed moves on to the open one.
        if (! $order->aktion->isAcceptingOrders()) {
            $order->aktion()->associate($this->acceptingAktion($tenant));
        }

        $this->validateForSubmit($order);

        DB::transaction(function () use ($order, $tenant) {
            $aktion = Aktion::query()->lockForUpdate()->findOrFail($order->aktion_id);
            $remaining = $aktion->remainingCapacity();
            if ($remaining !== null && $order->items()->count() > $remaining) {
                throw ValidationException::withMessages([
                    'copies' => $remaining > 0
                        ? "In dieser Aktion sind nur noch {$remaining} Bücher verfügbar."
                        : 'Diese Aktion ist leider schon ausgebucht.',
                ]);
            }

            $this->pricer->price($order);

            $order->fill([
                'number' => $tenant->nextOrderNumber(),
                'status' => $order->payment_method === PaymentMethod::InvoiceInParcel
                    ? OrderStatus::Confirmed
                    : OrderStatus::PendingPayment,
                'submitted_at' => now(),
                'current_step' => null,
            ])->save();

            $order->log('submitted', ['payment_method' => $order->payment_method->value], public: true);
        });

        $checkoutUrl = null;
        try {
            $checkoutUrl = $this->payments->initiate($order);
        } catch (Throwable $e) {
            // The order stands; the customer can retry payment from the tracking page.
            Log::error('Could not start payment', ['order' => $order->id, 'error' => $e->getMessage()]);
        }

        OrderSubmitted::dispatch($order);

        return response()->json([
            'order' => new OrderResource($this->loadForResponse($order)),
            'next' => $checkoutUrl ? ['type' => 'redirect', 'url' => $checkoutUrl] : ['type' => 'confirmation'],
        ]);
    }

    /**
     * (Re)start an online payment for an unpaid order.
     */
    public function pay(Tenant $tenant, string $token): JsonResponse
    {
        $order = $this->findOrder($tenant, $token);
        abort_unless($this->payments->canPayOnline($order), 409, 'Diese Bestellung kann nicht online bezahlt werden.');

        try {
            return response()->json(['url' => $this->payments->startCheckout($order)]);
        } catch (Throwable $e) {
            Log::error('Could not start checkout', ['order' => $order->id, 'error' => $e->getMessage()]);
            abort(502, 'Die Online-Zahlung ist gerade nicht verfügbar. Bitte versuch es später nochmals.');
        }
    }

    /**
     * The QR invoice as PDF (invoice payment methods only).
     */
    public function invoice(Tenant $tenant, string $token, InvoicePdf $invoices): Response
    {
        $order = $this->findOrder($tenant, $token);
        abort_unless($order->number && in_array($order->payment_method, [PaymentMethod::InvoiceAdvance, PaymentMethod::InvoiceInParcel], true), 404);

        return response($invoices->render($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoices->filename($order).'"',
        ]);
    }

    /**
     * Find an order by number and email, e.g. from a printed letter.
     */
    public function lookup(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = $tenant->orders()
            ->where('number', strtoupper(trim($data['number'])))
            ->whereRaw('lower(email) = ?', [strtolower($data['email'])])
            ->first();

        abort_unless($order, 404, 'Wir konnten keine passende Bestellung finden.');

        return response()->json(['token' => $order->token]);
    }

    protected function findOrder(Tenant $tenant, string $token): Order
    {
        return $tenant->orders()->where('token', $token)->firstOrFail();
    }

    protected function acceptingAktion(Tenant $tenant): Aktion
    {
        $aktion = $tenant->openAktion();

        if (! $aktion?->isAcceptingOrders()) {
            abort(409, 'Im Moment läuft keine Widmungsaktion.');
        }

        return $aktion;
    }

    protected function respond(Order $order, int $status = 200): JsonResponse
    {
        return (new OrderResource($this->loadForResponse($order)))->response()->setStatusCode($status);
    }

    protected function loadForResponse(Order $order): Order
    {
        return $order->fresh(['aktion', 'items.book', 'events', 'shipments']);
    }

    /**
     * @return array<int, string>
     */
    protected function paymentMethods(Tenant $tenant): array
    {
        return $this->payments->availableMethods($tenant);
    }

    protected function copiesRules(Tenant $tenant, Aktion $aktion, bool $required): array
    {
        $bookIds = $aktion->books()->where('is_active', true)->pluck('books.id')->all();

        return [
            'copies' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:'.$tenant->setting('max_copies_per_order')],
            'copies.*.book_id' => ['required', 'integer', Rule::in($bookIds)],
            'copies.*.signature_only' => ['boolean'],
            'copies.*.recipient_name' => ['nullable', 'string', 'max:100'],
            'copies.*.wish' => ['nullable', 'string', 'max:'.$tenant->setting('dedication_max_length')],
        ];
    }

    protected function addressRules(string $prefix, bool $strict): array
    {
        $required = $strict ? 'required' : 'nullable';

        return [
            $prefix => ['sometimes', 'nullable', 'array'],
            "$prefix.first_name" => [$required, 'string', 'max:100'],
            "$prefix.last_name" => [$required, 'string', 'max:100'],
            "$prefix.company" => ['nullable', 'string', 'max:100'],
            "$prefix.street" => [$required, 'string', 'max:100'],
            "$prefix.postal_code" => [$required, 'string', 'regex:/^\d{4}$/'],
            "$prefix.town" => [$required, 'string', 'max:100'],
            "$prefix.country" => ['nullable', Rule::in(['CH', 'LI'])],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $copies
     */
    protected function syncCopies(Order $order, array $copies): void
    {
        $order->items()->delete();

        foreach (array_values($copies) as $index => $copy) {
            $signatureOnly = (bool) ($copy['signature_only'] ?? false);
            $wish = $signatureOnly ? null : (trim((string) ($copy['wish'] ?? '')) ?: null);
            $recipient = $signatureOnly ? null : (trim((string) ($copy['recipient_name'] ?? '')) ?: null);

            $order->items()->create([
                'book_id' => $copy['book_id'],
                'unit_price' => 0,
                'position' => $index + 1,
                'signature_only' => $signatureOnly,
                'recipient_name' => $recipient,
                'wish' => $wish,
                // Anything customers wrote gets reviewed; plain signatures don't need it.
                'moderation_status' => $wish === null && $recipient === null ? ModerationStatus::Approved : ModerationStatus::Pending,
            ]);
        }

        $order->unsetRelation('items');
    }

    protected function validateForSubmit(Order $order): void
    {
        // Validate exactly what the customer sees, as plain arrays.
        $state = json_decode((new OrderResource($this->loadForResponse($order)))->toJson(), true);
        $tenant = $order->tenant;

        $rules = [
            'email' => ['required', 'email:rfc'],
            ...$this->copiesRules($tenant, $order->aktion, required: true),
            ...$this->addressRules('billing', strict: true),
            'payment_method' => ['required', Rule::in($this->paymentMethods($tenant))],
        ];

        if ($order->is_gift) {
            $rules += $this->addressRules('shipping', strict: true);
            $rules['shipping'] = ['required', 'array'];
            $rules['gift_message'] = ['required', 'string', 'max:500'];
            $rules['payment_method'][] = Rule::notIn([PaymentMethod::InvoiceInParcel->value]);
        }

        Validator::make($state, $rules, [
            'payment_method.not_in' => 'Bei Geschenken ist «Rechnung im Paket» nicht möglich.',
        ])->validate();
    }
}
