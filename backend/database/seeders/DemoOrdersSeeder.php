<?php

namespace Database\Seeders;

use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Models\Aktion;
use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\OrderPricer;
use App\Services\Payments\PaymentReference;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Realistic demo orders for the open "Herbst-Widmungsaktion 2026", covering
 * every payment method and status before signing day. Records are written
 * directly (no events), so no emails are sent.
 *
 *   ddev artisan db:seed --class=DemoOrdersSeeder
 */
class DemoOrdersSeeder extends Seeder
{
    protected Generator $faker;

    protected Tenant $tenant;

    protected Aktion $aktion;

    /** @var Collection<int, Book> */
    protected Collection $books;

    /**
     * How many orders of each kind. All belong to the Herbst-Aktion, none is signed.
     *
     * @var array<string, int>
     */
    protected array $plan = [
        // Paid and ready for signing day
        'online_paid' => 12,
        'invoice_advance_paid' => 7,
        // Confirmed without payment: the invoice goes into the parcel
        'invoice_in_parcel' => 6,
        // Waiting for payment
        'invoice_advance_unpaid' => 6,
        'invoice_advance_reminded' => 2,
        'online_unpaid' => 2,
        // Paid, then partly refunded (e.g. one book less), still to be signed
        'partially_refunded' => 1,
        // Cancelled before signing
        'cancelled_unpaid' => 2,
        'cancelled_refunded' => 1,
        // Started, never submitted (abandoned form)
        'draft' => 4,
    ];

    protected array $towns = [
        ['8004', 'Zürich'], ['8048', 'Zürich'], ['8400', 'Winterthur'], ['3011', 'Bern'], ['3007', 'Bern'],
        ['4051', 'Basel'], ['4057', 'Basel'], ['6003', 'Luzern'], ['9000', 'St. Gallen'], ['8200', 'Schaffhausen'],
        ['5000', 'Aarau'], ['2502', 'Biel/Bienne'], ['7000', 'Chur'], ['6300', 'Zug'], ['8840', 'Einsiedeln'],
        ['3600', 'Thun'], ['8610', 'Uster'], ['4500', 'Solothurn'], ['1700', 'Fribourg'], ['9490', 'Vaduz'],
    ];

    protected array $streets = [
        'Langstrasse', 'Bahnhofstrasse', 'Seestrasse', 'Dorfstrasse', 'Hauptstrasse', 'Kirchgasse', 'Bergstrasse',
        'Lindenweg', 'Rosenweg', 'Gartenstrasse', 'Schulstrasse', 'Feldstrasse', 'Mühlegasse', 'Rheinweg',
    ];

    protected array $wishes = [
        'Für alle, die noch Hoffnung brauchen.',
        'Für meine Schwester, die mutigste Person, die ich kenne.',
        'Danke für alles, was du für uns tust.',
        'Zum 30. Geburtstag, bleib so laut!',
        'Für mein queeres Herz.',
        'Für Mama, die mich immer verstanden hat.',
        'Alles Liebe zum Coming-out-Jahrestag.',
        'Für die beste WG der Welt.',
        'Weil du mir gezeigt hast, dass Wut auch Hoffnung sein kann.',
        'Für meine Liebste, auf viele weitere Jahre.',
        'Bleib stark und bleib weich.',
        'Für unseren Lesekreis 💗',
    ];

    protected array $wishesEn = [
        'For everyone who still needs hope.',
        'To my partner, with all my love.',
        'Happy birthday, stay loud!',
    ];

    protected array $giftMessages = [
        'Alles Gute zum Geburtstag! Ich dachte, das könnte dir gefallen. Deine Sam',
        'Weil du mir so viel bedeutest. Fühl dich gedrückt!',
        'Ein kleines Dankeschön für alles. Bis bald!',
        'Frohe Weihnachten! ❤️',
        'Für dich, weil du immer an mich glaubst.',
    ];

    public function run(): void
    {
        $this->faker = Faker::create('de_CH');
        $this->faker->seed(2026);

        $this->tenant = Tenant::query()->where('slug', 'anna-rosenwasser')->firstOrFail();
        $this->aktion = $this->tenant->aktionen()->where('name', 'Herbst-Widmungsaktion 2026')->firstOrFail();
        $this->books = $this->aktion->books()->get();

        foreach ($this->plan as $kind => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->createOrder($kind);
            }
        }

        $this->command?->info(array_sum($this->plan).' demo orders created for «'.$this->aktion->name.'».');
    }

    protected function createOrder(string $kind): Order
    {
        $locale = $this->faker->boolean(10) ? 'en' : 'de';
        $isGift = $kind !== 'invoice_in_parcel' && $this->faker->boolean(25);
        $method = match (true) {
            str_starts_with($kind, 'online') => PaymentMethod::Online,
            $kind === 'invoice_in_parcel' => PaymentMethod::InvoiceInParcel,
            $kind === 'partially_refunded', $kind === 'cancelled_refunded' => $this->faker->randomElement([PaymentMethod::Online, PaymentMethod::InvoiceAdvance]),
            $kind === 'draft' => null,
            default => PaymentMethod::InvoiceAdvance,
        };

        $submittedAt = Carbon::instance($this->faker->dateTimeBetween($this->aktion->orders_open_at, 'now'));
        [$zip, $town] = $this->faker->randomElement($this->towns);
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();

        $order = $this->tenant->orders()->create([
            'aktion_id' => $this->aktion->id,
            'locale' => $locale,
            'email' => strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "$firstName.$lastName")).'@example.com',
            'billing_first_name' => $firstName,
            'billing_last_name' => $lastName,
            'billing_company' => $this->faker->boolean(8) ? 'c/o '.$this->faker->lastName() : null,
            'billing_street' => $this->faker->randomElement($this->streets).' '.$this->faker->numberBetween(1, 140),
            'billing_postal_code' => $zip,
            'billing_town' => $town,
            'billing_country' => $zip === '9490' ? 'LI' : 'CH',
            'is_gift' => $isGift,
            'gift_message' => $isGift ? $this->faker->randomElement($this->giftMessages) : null,
            'payment_method' => $method,
            'newsletter_opt_in' => $this->faker->boolean(35),
            'created_at' => $submittedAt->copy()->subMinutes(6),
        ]);

        if ($isGift) {
            [$giftZip, $giftTown] = $this->faker->randomElement($this->towns);
            $order->fill([
                'shipping_first_name' => $this->faker->firstName(),
                'shipping_last_name' => $this->faker->lastName(),
                'shipping_street' => $this->faker->randomElement($this->streets).' '.$this->faker->numberBetween(1, 140),
                'shipping_postal_code' => $giftZip,
                'shipping_town' => $giftTown,
                'shipping_country' => $giftZip === '9490' ? 'LI' : 'CH',
            ])->save();
        }

        $this->createCopies($order, $locale);
        app(OrderPricer::class)->price($order->refresh())->save();

        if ($kind === 'draft') {
            $order->update(['current_step' => $this->faker->randomElement(['dedication', 'address', 'payment'])]);
            $order->log('draft_created');

            return $order;
        }

        $order->forceFill([
            'number' => $this->tenant->nextOrderNumber(),
            'submitted_at' => $submittedAt,
            'status' => OrderStatus::PendingPayment,
        ])->save();
        $this->event($order, 'submitted', $submittedAt, public: true);

        match ($kind) {
            'online_paid' => $this->pay($order, $this->onlinePayment($order), $submittedAt->copy()->addMinutes(2)),
            'invoice_advance_paid' => $this->pay($order, $this->invoicePayment($order), $this->paidLater($submittedAt)),
            'invoice_in_parcel' => $this->confirmInParcel($order),
            'invoice_advance_unpaid' => $this->invoicePayment($order),
            'invoice_advance_reminded' => $this->remind($order, $this->invoicePayment($order)),
            'online_unpaid' => $this->onlinePayment($order, PaymentState::Expired),
            'partially_refunded' => $this->refund($order, partial: true),
            'cancelled_unpaid' => $this->cancel($order, $this->invoicePayment($order), 'Doppelt bestellt'),
            'cancelled_refunded' => $this->refund($order, partial: false),
        };

        return $order;
    }

    protected function createCopies(Order $order, string $locale): void
    {
        // Mostly single copies, sometimes two, rarely three or four.
        $count = $this->faker->randomElement([1, 1, 1, 1, 1, 1, 2, 2, 2, 3, 4]);
        $herz = $this->books->firstWhere('title', 'Herz') ?? $this->books->first();

        for ($position = 1; $position <= $count; $position++) {
            $book = $this->faker->boolean(70) ? $herz : $this->books->random();
            $signatureOnly = $this->faker->boolean(20);
            $withWish = ! $signatureOnly && $this->faker->boolean(60);

            $recipient = $signatureOnly ? null : (
                $order->is_gift && $position === 1 ? $order->shipping_first_name : $this->faker->firstName()
            );
            $wish = $withWish ? $this->faker->randomElement($locale === 'en' ? $this->wishesEn : $this->wishes) : null;

            $order->items()->create([
                'book_id' => $book->id,
                'unit_price' => 0,
                'position' => $position,
                'signature_only' => $signatureOnly,
                'recipient_name' => $recipient,
                'wish' => $wish,
                'moderation_status' => $this->moderationStatus($recipient, $wish),
                'moderation_note' => null,
            ]);
        }

        // One query for the moderation page.
        $flaggable = $order->items()->whereNotNull('wish')->first();
        if ($flaggable && $this->faker->boolean(8)) {
            $flaggable->update(['moderation_status' => ModerationStatus::Flagged, 'moderation_note' => 'Bitte nachfragen, was genau gemeint ist.']);
        }
    }

    protected function moderationStatus(?string $recipient, ?string $wish): ModerationStatus
    {
        if ($recipient === null && $wish === null) {
            return ModerationStatus::Approved;
        }

        // About two thirds already reviewed, the rest waits on the moderation page.
        return $this->faker->boolean(65) ? ModerationStatus::Approved : ModerationStatus::Pending;
    }

    protected function invoicePayment(Order $order): Payment
    {
        return $order->payments()->create([
            'method' => $order->payment_method === PaymentMethod::InvoiceInParcel ? PaymentMethod::InvoiceInParcel : PaymentMethod::InvoiceAdvance,
            'amount' => $order->total,
            'status' => PaymentState::Pending,
            'reference' => PaymentReference::for($order),
        ]);
    }

    protected function onlinePayment(Order $order, PaymentState $state = PaymentState::Pending): Payment
    {
        $id = substr(hash('sha256', $order->number), 0, 24);

        return $order->payments()->create([
            'method' => PaymentMethod::Online,
            'amount' => $order->total,
            'status' => $state,
            'stripe_checkout_session_id' => "cs_demo_$id",
            'stripe_payment_intent_id' => $state === PaymentState::Expired ? null : "pi_demo_$id",
        ]);
    }

    protected function pay(Order $order, Payment $payment, Carbon $at): void
    {
        $payment->update(['status' => PaymentState::Paid, 'paid_at' => $at]);
        $order->update(['status' => OrderStatus::Confirmed, 'payment_status' => PaymentStatus::Paid]);
        $this->event($order, 'payment_received', $at, public: true, data: ['method' => $payment->method->value, 'amount' => $payment->amount]);
    }

    protected function paidLater(Carbon $submittedAt): Carbon
    {
        $at = $submittedAt->copy()->addDays($this->faker->numberBetween(1, 4))->setTime(10, 0);

        return $at->isFuture() ? now()->subHour() : $at;
    }

    protected function confirmInParcel(Order $order): void
    {
        $this->invoicePayment($order);
        $order->update(['status' => OrderStatus::Confirmed]);
    }

    protected function remind(Order $order, Payment $payment): void
    {
        $at = now()->subDays($this->faker->numberBetween(1, 2));
        $order->update(['payment_reminder_sent_at' => $at, 'submitted_at' => $order->submitted_at->min($at->copy()->subDays(5))]);
        $this->event($order, 'reminder_sent', $at);
    }

    protected function refund(Order $order, bool $partial): void
    {
        $payment = $order->payment_method === PaymentMethod::Online ? $this->onlinePayment($order) : $this->invoicePayment($order);
        $paidAt = $order->payment_method === PaymentMethod::Online ? $order->submitted_at->copy()->addMinutes(2) : $this->paidLater($order->submitted_at);
        $this->pay($order, $payment, $paidAt);

        $amount = $partial ? min($payment->amount, (int) $order->items->first()->unit_price) : $payment->amount;
        $payment->update(['refunded_amount' => $amount]);
        $order->update(['payment_status' => $amount >= $payment->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded]);
        $this->event($order, 'refunded', now()->subDay(), public: true, data: ['amount' => $amount, 'reason' => $partial ? 'Ein Buch weniger' : 'Bestellung storniert']);

        if (! $partial) {
            $this->cancel($order, $payment, 'Auf Wunsch der Kundin storniert');
        }
    }

    protected function cancel(Order $order, Payment $payment, string $reason): void
    {
        if ($payment->status === PaymentState::Pending) {
            $payment->update(['status' => PaymentState::Cancelled]);
        }
        $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()->subDay(), 'cancel_reason' => $reason]);
        $this->event($order, 'cancelled', now()->subDay(), public: true, data: ['reason' => $reason]);
    }

    protected function event(Order $order, string $type, Carbon $at, bool $public = false, array $data = []): void
    {
        $order->events()->create(['type' => $type, 'data' => $data, 'is_public' => $public, 'created_at' => $at]);
    }
}
