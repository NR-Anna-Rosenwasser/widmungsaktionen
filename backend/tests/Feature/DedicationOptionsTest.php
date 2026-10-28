<?php

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Mail\OrderConfirmationMail;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * Which dedication options a shop offers, and which copies need checking.
 */
class DedicationOptionsTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected int $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->book = $this->tenant->books()->orderBy('sort')->first()->id;
    }

    protected function settings(array $settings): void
    {
        $this->tenant->update(['settings' => [...($this->tenant->settings ?? []), ...$settings]]);
    }

    protected function base(): string
    {
        return "/api/tenants/{$this->tenant->slug}";
    }

    public function test_defaults_offer_everything_and_check_names(): void
    {
        $this->getJson($this->base())
            ->assertJsonPath('tenant.dedication_options', ['signature_only' => true, 'wish' => true]);

        $order = $this->submitOrder($this->tenant, copies: [
            ['book_id' => $this->book, 'signature_only' => true],
            ['book_id' => $this->book, 'recipient_name' => 'Mia'],
            ['book_id' => $this->book, 'recipient_name' => 'Kim', 'wish' => 'Alles Liebe'],
        ]);

        $this->assertSame(
            [ModerationStatus::Approved, ModerationStatus::Pending, ModerationStatus::Pending],
            $order->items->pluck('moderation_status')->all(),
        );
    }

    public function test_without_signature_only_every_copy_needs_a_name(): void
    {
        $this->settings(['dedication_signature_only' => false]);
        $this->getJson($this->base())->assertJsonPath('tenant.dedication_options.signature_only', false);

        // A draft that still asks for a plain signature (e.g. started before the change).
        $token = $this->postJson("{$this->base()}/orders", ['email' => 'leserin@example.com', 'copies' => [['book_id' => $this->book]]])
            ->assertCreated()
            ->json('token');
        $this->patchJson("{$this->base()}/orders/{$token}", [
            'copies' => [['book_id' => $this->book, 'signature_only' => true]],
            'billing' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'street' => 'Langstrasse 1', 'postal_code' => '8004', 'town' => 'Zürich'],
            'payment_method' => 'invoice_advance',
        ])->assertOk()->assertJsonPath('copies.0.signature_only', false);

        $this->postJson("{$this->base()}/orders/{$token}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('copies.0.recipient_name');

        // With a name and no wish: «lass dich überraschen».
        $order = $this->submitOrder($this->tenant, copies: [['book_id' => $this->book, 'recipient_name' => 'Mia']]);
        $this->assertSame(ModerationStatus::Pending, $order->items->first()->moderation_status);
        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->order->is($order)
            && str_contains($mail->render(), 'Mia · lass dich überraschen'));
    }

    public function test_without_wish_it_is_dropped(): void
    {
        $this->settings(['dedication_wish' => false]);

        $order = $this->submitOrder($this->tenant, copies: [['book_id' => $this->book, 'recipient_name' => 'Mia', 'wish' => 'Für Mia']]);

        $this->assertNull($order->items->first()->wish);
        $this->assertSame('Mia', $order->items->first()->recipient_name);
    }

    public function test_names_without_wish_can_be_approved_automatically(): void
    {
        $this->settings(['dedication_auto_approve_names' => true]);

        $order = $this->submitOrder($this->tenant, copies: [
            ['book_id' => $this->book, 'recipient_name' => 'Mia'],
            ['book_id' => $this->book, 'recipient_name' => 'Kim', 'wish' => 'Alles Liebe'],
        ]);

        // A wish is still checked.
        $this->assertSame(
            [ModerationStatus::Approved, ModerationStatus::Pending],
            $order->fresh()->items->pluck('moderation_status')->all(),
        );
    }
}
