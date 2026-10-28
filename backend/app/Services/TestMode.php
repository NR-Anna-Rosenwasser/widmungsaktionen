<?php

namespace App\Services;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendSignedMail;
use App\Models\Aktion;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Admin actions for an Aktion in testing mode: send the mails that would
 * otherwise wait (24 h, days before the deadline, 30 min after signing),
 * delete test orders, and turn testing mode off.
 */
class TestMode
{
    public function __construct(
        protected OrderHousekeeping $housekeeping,
        protected UnpaidOrders $unpaid,
    ) {}

    /**
     * @return array{abandoned: int, reminders: int, signed: int}
     */
    public function sendPendingMails(Aktion $aktion): array
    {
        $this->ensureTest($aktion);

        $signed = $aktion->orders()
            ->where('status', OrderStatus::Signed)
            ->get()
            ->filter(fn (Order $order) => $order->signedMailPending())
            // The delayed job queued at signing finds the mail sent and skips.
            ->each(fn (Order $order) => SendSignedMail::dispatchSync($order));

        return [
            'abandoned' => $this->housekeeping->sendAbandonedRemindersNow($aktion),
            'reminders' => $this->unpaid->sendRemindersNow($aktion),
            'signed' => $signed->count(),
        ];
    }

    /**
     * Delete all orders of the test Aktion with everything attached
     * (items, payments, history, shipments cascade). Returns the count.
     */
    public function deleteOrders(Aktion $aktion): int
    {
        $this->ensureTest($aktion);

        return DB::transaction(fn () => $aktion->orders()->get()->each->delete()->count());
    }

    /**
     * Leave testing mode: the Aktion becomes a real one, the preview link stops working.
     */
    public function end(Aktion $aktion, bool $deleteOrders = false): void
    {
        $this->ensureTest($aktion);

        if ($deleteOrders) {
            $this->deleteOrders($aktion);
        }

        if ($aktion->orders()->exists()) {
            throw new RuntimeException('Bitte zuerst die Testbestellungen löschen.');
        }

        if ($aktion->status === AktionStatus::Open && ($other = $this->otherOpenAktion($aktion))) {
            throw new RuntimeException("«{$other->name}» ist bereits offen. Bitte zuerst schliessen.");
        }

        $aktion->update(['is_test' => false, 'preview_token' => null]);
    }

    /**
     * Copy an Aktion (dates, capacity, books with prices, extra texts) into a
     * new test Aktion. Always a test Aktion: a live copy could open by accident.
     * Starts as draft, so dates and status can be checked before testing.
     */
    public function duplicate(Aktion $source, string $name): Aktion
    {
        return DB::transaction(function () use ($source, $name) {
            $copy = $source->replicate(['status', 'is_test', 'preview_token']);
            $copy->fill(['name' => $name, 'status' => AktionStatus::Draft, 'is_test' => true])->save();

            $copy->books()->sync($source->books->mapWithKeys(fn ($book) => [$book->id => [
                'price_override' => $book->pivot->price_override,
                'sort' => $book->pivot->sort,
            ]]));

            return $copy;
        });
    }

    public function regenerateLink(Aktion $aktion): void
    {
        $this->ensureTest($aktion);

        $aktion->update(['preview_token' => Aktion::newPreviewToken()]);
    }

    /**
     * Another real Aktion of the shop that is open (only one may be).
     */
    public function otherOpenAktion(Aktion $aktion): ?Aktion
    {
        return Aktion::query()
            ->where('tenant_id', $aktion->tenant_id)
            ->where('is_test', false)
            ->where('status', AktionStatus::Open)
            ->whereKeyNot($aktion->getKey())
            ->first();
    }

    protected function ensureTest(Aktion $aktion): void
    {
        if (! $aktion->is_test) {
            throw new RuntimeException('Diese Aktion ist nicht im Testmodus.');
        }
    }
}
