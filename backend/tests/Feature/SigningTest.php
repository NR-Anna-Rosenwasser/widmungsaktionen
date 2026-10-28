<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Filament\Pages\Widmen;
use App\Jobs\SendSignedMail;
use App\Mail\OrderShippedMail;
use App\Mail\OrderSignedMail;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShippingService;
use App\Services\SigningService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class SigningTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected SigningService $signing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->tenant->update(['settings' => ['checkpoint_interval' => 2]]);
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();
        $this->signing = app(SigningService::class);
        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));
        Filament::setTenant($this->tenant);
        Mail::fake();
    }

    protected function book(string $title): int
    {
        return $this->tenant->books()->firstWhere('title', $title)->id;
    }

    /**
     * A confirmed order with approved dedications.
     */
    protected function confirmedOrder(array $copies): Order
    {
        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel', $copies);
        $order->items()->update(['moderation_status' => ModerationStatus::Approved]);

        return $order->fresh();
    }

    public function test_prepare_needs_finished_moderation(): void
    {
        $this->submitOrder($this->tenant, 'invoice_in_parcel');

        $this->expectException(RuntimeException::class);
        $this->prepareStack($this->aktion);
    }

    public function test_stack_is_sorted_by_book_and_continues_for_late_orders(): void
    {
        $rosa = $this->confirmedOrder([['book_id' => $this->book('Rosa Buch'), 'signature_only' => true]]);
        $herz = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'signature_only' => true]]);
        $flagged = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'X']]);
        $flagged->items()->update(['moderation_status' => ModerationStatus::Flagged]);
        $unpaid = $this->submitOrder($this->tenant, 'invoice_advance', [['book_id' => $this->book('Herz'), 'signature_only' => true]]);

        // Orders with a query are on the stack too, at the end.
        $this->assertSame(3, $this->prepareStack($this->aktion));

        $this->assertSame(1, $herz->fresh()->stack_number);
        $this->assertSame(2, $rosa->fresh()->stack_number);
        $this->assertSame(3, $flagged->fresh()->stack_number);
        $this->assertNull($unpaid->fresh()->stack_number);
        $this->assertSame(AktionStatus::Signing, $this->aktion->fresh()->status);

        // Only once; paid later: added on its own with the next number.
        $unpaid->update(['status' => OrderStatus::Confirmed]);
        $this->assertNotNull($this->signing->cannotPrepare($this->aktion));
        $this->assertSame(4, $this->signing->addToStack($unpaid->fresh()));
    }

    public function test_query_raised_after_preparing_is_put_aside_and_signed_at_the_end(): void
    {
        $sig = fn () => ['book_id' => $this->book('Herz'), 'signature_only' => true];
        $first = $this->confirmedOrder([$sig()]);
        $flagged = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'Kim']]);
        $third = $this->confirmedOrder([$sig()]);
        $this->prepareStack($this->aktion);
        // Flagged after the post-its were written: the book is in the middle of the stack.
        $flaggedItem = $flagged->items->first();
        $flaggedItem->update(['moderation_status' => ModerationStatus::Flagged]);
        $user = User::first();

        $this->signing->sign($first->items->first(), $user);
        $this->assertTrue($this->signing->current($this->aktion)->is($third->items->first()));
        $this->assertTrue($this->signing->heldBefore($this->aktion, $third->items->first())->first()->is($flagged));

        // Resolved before the signing reached it: back in its place.
        $flaggedItem->update(['moderation_status' => ModerationStatus::Approved]);
        $this->assertTrue($this->signing->current($this->aktion)->is($flaggedItem));
        $this->assertFalse($this->signing->isPassed($this->aktion, $flaggedItem));

        // Flagged again and the signing moves on: it comes up at the end.
        $flaggedItem->update(['moderation_status' => ModerationStatus::Flagged]);
        $this->signing->sign($third->items->first(), $user);
        $this->assertNull($this->signing->current($this->aktion));
        $this->assertCount(1, $this->signing->heldOrders($this->aktion));

        $flaggedItem->update(['moderation_status' => ModerationStatus::Approved]);
        $this->assertTrue($this->signing->current($this->aktion)->is($flaggedItem));
        $this->assertTrue($this->signing->isPassed($this->aktion, $flaggedItem));
    }

    public function test_widmen_with_open_queries_at_the_end_of_the_stack(): void
    {
        $sig = fn () => ['book_id' => $this->book('Herz'), 'signature_only' => true];
        $this->confirmedOrder([$sig()]);
        $flagged = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'Kim']]);
        $flaggedItem = $flagged->items->first();
        $flaggedItem->update(['moderation_status' => ModerationStatus::Flagged, 'moderation_note' => 'Name unklar']);
        $this->confirmedOrder([$sig()]);
        $this->prepareStack($this->aktion);
        $this->assertSame(3, $flagged->fresh()->stack_number);

        $page = Livewire::test(Widmen::class)
            ->call('start')
            ->set('checkpointInput', '1')->call('submitCheckpoint')
            ->call('sign')
            ->assertSet('mode', 'sign')
            ->call('sign')
            ->assertSet('mode', 'done')
            ->assertSee('Fertig bis auf 1 Bestellung mit Rückfrage')
            ->assertSee('am Ende des Stapels')
            ->assertSee('Name unklar');

        $flaggedItem->update(['moderation_status' => ModerationStatus::Approved]);

        $page->call('continueSigning')
            ->assertSet('mode', 'checkpoint')
            ->assertDontSee('Nachgeholt')
            ->set('checkpointInput', '3')->call('submitCheckpoint')
            ->call('sign')
            ->assertSee('Alles gewidmet');

        $this->assertSame(OrderStatus::Signed, $flagged->fresh()->status);
    }

    public function test_widmen_asks_to_put_aside_a_book_flagged_later(): void
    {
        $sig = fn () => ['book_id' => $this->book('Herz'), 'signature_only' => true];
        $this->confirmedOrder([$sig()]);
        $flagged = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'Kim']]);
        $this->confirmedOrder([$sig()]);
        $this->prepareStack($this->aktion);
        $flaggedItem = $flagged->items->first();
        $flaggedItem->update(['moderation_status' => ModerationStatus::Flagged]);

        $page = Livewire::test(Widmen::class)
            ->call('start')
            ->set('checkpointInput', '1')->call('submitCheckpoint')
            ->call('sign')
            // #2 lies on top of the stack now: put it aside, then check the post-it.
            ->assertSet('mode', 'checkpoint')
            ->assertSee('Rückfrage: Leg diesen Post-it mit allen Büchern beiseite')
            ->set('checkpointInput', '3')->call('submitCheckpoint')
            ->call('sign')
            ->assertSee('beiseitegelegt');

        $flaggedItem->update(['moderation_status' => ModerationStatus::Approved]);

        $page->call('continueSigning')
            ->assertSee('Nachgeholt')
            ->assertSee('Hol Post-it #2')
            ->set('checkpointInput', '2')->call('submitCheckpoint')
            ->call('sign')
            ->assertSee('Alles gewidmet');
    }

    public function test_an_order_with_a_query_is_held_as_a_whole(): void
    {
        $herz = fn (array $extra = []) => ['book_id' => $this->book('Herz'), ...$extra];
        $order = $this->confirmedOrder([$herz(['signature_only' => true]), $herz(['recipient_name' => 'Kim'])]);
        $order->items[1]->update(['moderation_status' => ModerationStatus::Flagged]);
        $other = $this->confirmedOrder([$herz(['signature_only' => true]), $herz(['signature_only' => true])]);
        $this->prepareStack($this->aktion);

        // The unproblematic first book waits too: the order's books stay together.
        $this->assertSame([$other->id], $this->signing->toSign($this->aktion)->pluck('order_id')->unique()->values()->all());

        // Flagged while signing the first copy of the other order: the whole order leaves the queue.
        Livewire::test(Widmen::class)
            ->call('start')
            ->set('checkpointInput', '1')->call('submitCheckpoint')
            ->call('flag')
            ->assertSet('mode', 'done')
            ->assertSee('Fertig bis auf 2 Bestellungen mit Rückfrage');

        $this->assertSame(0, $this->signing->progress($this->aktion)['signed']);
    }

    public function test_flagged_copy_is_put_aside(): void
    {
        $order = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'Kim']]);
        $this->prepareStack($this->aktion);

        Livewire::test(Widmen::class)
            ->call('start')
            ->set('checkpointInput', '1')->call('submitCheckpoint')
            ->set('flagNote', 'Name unleserlich')
            ->call('flag')
            ->assertSet('mode', 'done');

        $this->assertSame(ModerationStatus::Flagged, $order->items->first()->fresh()->moderation_status);
    }

    public function test_signed_mail_is_off_by_default(): void
    {
        Queue::fake();
        $order = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'signature_only' => true]]);
        $this->prepareStack($this->aktion);

        $this->signing->sign($order->items->first(), User::first());

        $this->assertSame(OrderStatus::Signed, $order->fresh()->status);
        Queue::assertNotPushed(SendSignedMail::class);
    }

    public function test_signed_mail_is_delayed_and_skipped_after_undo(): void
    {
        Queue::fake();
        $this->tenant->update(['settings' => [...$this->tenant->settings, 'notify_signed' => true]]);
        $order = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'signature_only' => true]]);
        $this->prepareStack($this->aktion);
        $item = $order->items->first();

        $this->signing->sign($item, User::first());
        Queue::assertPushed(SendSignedMail::class, fn ($job) => $job->delay !== null);

        $this->signing->unsign($item->fresh(), User::first());
        (new SendSignedMail($order))->handle();
        Mail::assertNotQueued(OrderSignedMail::class);

        $this->signing->sign($item->fresh(), User::first());
        (new SendSignedMail($order))->handle();
        Mail::assertQueued(OrderSignedMail::class);
    }

    public function test_shipping_requires_signed_orders_and_notifies(): void
    {
        $order = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'signature_only' => true]]);
        $shipping = app(ShippingService::class);

        try {
            $shipping->ship($order, now(), User::first());
            $this->fail('Unsigned orders must not ship.');
        } catch (RuntimeException) {
        }

        $this->prepareStack($this->aktion);
        $this->signing->sign($order->items->first(), User::first());
        $shipping->ship($order->fresh(), now(), User::first(), '99.00.123456.12345678');

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('letter', $order->shipments->first()->method);
        Mail::assertQueued(OrderShippedMail::class, fn ($mail) => str_contains($mail->render(), '99.00.123456.12345678'));
        $this->getJson("/api/tenants/{$this->tenant->slug}/orders/{$order->token}")
            ->assertJsonPath('shipments.0.tracking_number', '99.00.123456.12345678');
    }
}
