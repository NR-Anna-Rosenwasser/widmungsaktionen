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
        $this->signing->prepare($this->aktion);
    }

    public function test_stack_is_sorted_by_book_and_continues_for_late_orders(): void
    {
        $rosa = $this->confirmedOrder([['book_id' => $this->book('Rosa Buch'), 'signature_only' => true]]);
        $herz = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'signature_only' => true]]);
        $flagged = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'X']]);
        $flagged->items()->update(['moderation_status' => ModerationStatus::Flagged]);
        $unpaid = $this->submitOrder($this->tenant, 'invoice_advance', [['book_id' => $this->book('Herz'), 'signature_only' => true]]);

        $this->assertSame(2, $this->signing->prepare($this->aktion));

        $this->assertSame(1, $herz->fresh()->stack_number);
        $this->assertSame(2, $rosa->fresh()->stack_number);
        $this->assertNull($flagged->fresh()->stack_number);
        $this->assertNull($unpaid->fresh()->stack_number);
        $this->assertSame(AktionStatus::Signing, $this->aktion->fresh()->status);

        // Resolved later: gets the next number.
        $flagged->items()->update(['moderation_status' => ModerationStatus::Approved]);
        $this->signing->prepare($this->aktion);
        $this->assertSame(3, $flagged->fresh()->stack_number);
    }

    public function test_signing_session_with_checkpoints(): void
    {
        $sig = fn () => ['book_id' => $this->book('Herz'), 'signature_only' => true];
        $first = $this->confirmedOrder([$sig(), $sig()]);
        $second = $this->confirmedOrder([$sig()]);
        $third = $this->confirmedOrder([$sig()]);
        $this->signing->prepare($this->aktion);

        $page = Livewire::test(Widmen::class)
            ->assertSet('aktion', $this->aktion->id)
            ->call('start')
            ->assertSet('mode', 'checkpoint')
            // Wrong post-it: stop.
            ->set('checkpointInput', '3')
            ->call('submitCheckpoint')
            ->assertSet('mode', 'mismatch')
            ->assertSee('Post-it #1')
            ->call('retryCheckpoint')
            ->set('checkpointInput', '1')
            ->call('submitCheckpoint')
            ->assertSet('mode', 'sign')
            ->assertSee('Buch 1 von 2');

        // Two books of order #1: signed, and only then the order is complete.
        $page->call('sign')->assertSee('Buch 2 von 2');
        $this->assertSame(OrderStatus::Confirmed, $first->fresh()->status);
        $page->call('sign');
        $this->assertSame(OrderStatus::Signed, $first->fresh()->status);

        // Interval (2) reached at a new post-it: check again.
        $page->assertSet('mode', 'checkpoint')
            ->set('checkpointInput', '#2')
            ->call('submitCheckpoint')
            ->call('sign')
            ->assertSet('mode', 'sign');

        // Undo goes back and asks for the post-it again.
        $page->call('undo')->assertSet('mode', 'checkpoint');
        $this->assertSame(OrderStatus::Confirmed, $second->fresh()->status);

        $page->set('checkpointInput', '2')->call('submitCheckpoint')
            ->call('sign')
            ->call('sign')
            ->assertSet('mode', 'done')
            ->assertSee('Alles gewidmet');

        $this->assertSame(OrderStatus::Signed, $third->fresh()->status);
    }

    public function test_flagged_copy_is_put_aside(): void
    {
        $order = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'recipient_name' => 'Kim']]);
        $this->signing->prepare($this->aktion);

        Livewire::test(Widmen::class)
            ->call('start')
            ->set('checkpointInput', '1')->call('submitCheckpoint')
            ->set('flagNote', 'Name unleserlich')
            ->call('flag')
            ->assertSet('mode', 'done');

        $this->assertSame(ModerationStatus::Flagged, $order->items->first()->fresh()->moderation_status);
    }

    public function test_signed_mail_is_delayed_and_skipped_after_undo(): void
    {
        Queue::fake();
        $order = $this->confirmedOrder([['book_id' => $this->book('Herz'), 'signature_only' => true]]);
        $this->signing->prepare($this->aktion);
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

        $this->signing->prepare($this->aktion);
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
