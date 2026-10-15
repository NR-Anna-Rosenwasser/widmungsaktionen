<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendNewsletterOptIn;
use App\Mail\AbandonedOrderMail;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\OrderHousekeeping;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class HousekeepingTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        Mail::fake();
    }

    protected function draft(string $email = 'leserin@example.com'): Order
    {
        $token = $this->postJson("/api/tenants/{$this->tenant->slug}/orders", [
            'email' => $email,
            'copies' => [['book_id' => $this->tenant->books()->first()->id]],
        ])->json('token');

        return Order::firstWhere('token', $token);
    }

    protected function age(Order $order, int $hours): void
    {
        Order::query()->whereKey($order->id)->update(['updated_at' => now()->subHours($hours)]);
    }

    public function test_newsletter_opt_in_is_sent_signed_to_the_webhook(): void
    {
        $this->tenant->update(['newsletter_webhook_url' => 'https://n8n.example.com/webhook/newsletter', 'newsletter_webhook_secret' => 's3cret']);
        Http::fake(['n8n.example.com/*' => Http::response(['ok' => true])]);

        $order = $this->submitOrder($this->tenant, overrides: ['newsletter_opt_in' => true]);

        Http::assertSent(function (Request $request) use ($order) {
            $timestamp = $request->header('X-Widmungen-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 's3cret');

            return $request->url() === 'https://n8n.example.com/webhook/newsletter'
                && $request['event'] === 'newsletter.opt_in'
                && $request['email'] === $order->email
                && $request['first_name'] === 'Mia'
                && $request->header('X-Widmungen-Signature')[0] === $expected;
        });
    }

    public function test_no_newsletter_webhook_without_opt_in(): void
    {
        Queue::fake([SendNewsletterOptIn::class]);
        $this->tenant->update(['newsletter_webhook_url' => 'https://n8n.example.com/webhook/newsletter']);

        $this->submitOrder($this->tenant);

        Queue::assertNotPushed(SendNewsletterOptIn::class);
    }

    public function test_abandoned_draft_gets_one_reminder_after_a_day(): void
    {
        $draft = $this->draft();

        $this->age($draft, 2);
        Artisan::call('orders:send-abandoned-reminders');
        Mail::assertNotQueued(AbandonedOrderMail::class);

        $this->age($draft, 25);
        Artisan::call('orders:send-abandoned-reminders');
        $this->age($draft, 30);
        Artisan::call('orders:send-abandoned-reminders');

        Mail::assertQueued(AbandonedOrderMail::class, 1);
        Mail::assertQueued(AbandonedOrderMail::class, fn ($mail) => str_contains($mail->render(), "/bestellen?t={$draft->token}"));
    }

    public function test_no_reminder_if_the_person_ordered_meanwhile_or_aktion_closed(): void
    {
        $draft = $this->draft('Leserin@example.com');
        $this->submitOrder($this->tenant);
        $this->age($draft, 25);

        app(OrderHousekeeping::class)->sendAbandonedReminders();
        Mail::assertNotQueued(AbandonedOrderMail::class);

        $other = $this->draft('jemand@example.com');
        $this->age($other, 25);
        $this->tenant->aktionen()->update(['status' => AktionStatus::Closed]);

        app(OrderHousekeeping::class)->sendAbandonedReminders();
        Mail::assertNotQueued(AbandonedOrderMail::class);
    }

    public function test_opt_out_link_deletes_the_draft(): void
    {
        $draft = $this->draft();

        $this->get("/api/tenants/{$this->tenant->slug}/orders/{$draft->token}/no-reminders")
            ->assertRedirectContains('abgemeldet=1');

        $this->assertModelMissing($draft);
    }

    public function test_old_drafts_are_deleted(): void
    {
        $old = $this->draft();
        $recent = $this->draft('neu@example.com');
        $this->age($old, 31 * 24);

        Artisan::call('orders:clean-up');

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_dedications_are_anonymised_after_retention(): void
    {
        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel');
        $order->update(['status' => OrderStatus::Shipped]);
        $order->shipments()->create(['method' => 'letter', 'shipped_at' => now()->subMonths(13)]);

        Artisan::call('orders:clean-up');

        $item = $order->items->first()->fresh();
        $this->assertNull($item->recipient_name);
        $this->assertNull($item->wish);
        $this->assertNotNull($item->dedication_anonymised_at);

        // Recently shipped orders keep their dedications.
        $recent = $this->submitOrder($this->tenant, 'invoice_in_parcel');
        $recent->update(['status' => OrderStatus::Shipped]);
        $recent->shipments()->create(['method' => 'letter', 'shipped_at' => now()->subMonth()]);
        Artisan::call('orders:clean-up');
        $this->assertSame('Mia', $recent->items->first()->fresh()->recipient_name);
    }
}
