<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Filament\Resources\Aktions\Pages\EditAktion;
use App\Filament\Resources\Aktions\Pages\ListAktions;
use App\Jobs\SendSignedMail;
use App\Mail\AbandonedOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderSignedMail;
use App\Mail\PaymentReminderMail;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\InvoicePdf;
use App\Services\TestMode;
use App\Services\UnpaidOrders;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class TestModeTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $real;

    protected Aktion $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->real = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();
        $this->test = $this->openTestAktion('Testaktion');
    }

    protected function openTestAktion(string $name): Aktion
    {
        $aktion = $this->tenant->aktionen()->create([
            'name' => $name,
            'status' => AktionStatus::Open,
            'is_test' => true,
            'orders_open_at' => now()->subHour(),
            'order_deadline' => now()->addWeek(),
            'signing_date' => now()->addDays(10),
            'estimated_shipping_date' => now()->addDays(14),
        ]);
        $aktion->books()->sync($this->tenant->books()->pluck('id'));

        return $aktion;
    }

    protected function usePreview(): void
    {
        $this->withHeader('X-Widmungen-Preview', $this->test->preview_token);
    }

    protected function storefront(): array
    {
        return $this->getJson("/api/tenants/{$this->tenant->slug}")->assertOk()->json();
    }

    public function test_storefront_shows_the_test_aktion_only_with_its_link(): void
    {
        $this->assertNotNull($this->test->preview_token, 'token is created with testing mode');

        $public = $this->storefront();
        $this->assertSame($this->real->name, $public['aktion']['name']);
        $this->assertNull($public['preview']);
        $this->assertNotContains('online', $public['tenant']['payment_methods'], 'no Stripe configured');

        $this->withUnencryptedCookie('widmungen_preview', 'wrong')->assertSame($this->real->name, $this->storefront()['aktion']['name']);

        $this->usePreview();
        $preview = $this->storefront();
        $this->assertSame('Testaktion', $preview['aktion']['name']);
        $this->assertSame(['name' => 'Testaktion'], $preview['preview']);
        $this->assertContains('online', $preview['tenant']['payment_methods'], 'test checkout needs no Stripe');
    }

    public function test_test_orders_have_their_own_numbers_and_stay_in_the_test_aktion(): void
    {
        $real = $this->submitOrder($this->tenant);
        $this->usePreview();
        $test = $this->submitOrder($this->tenant);

        $this->assertSame($this->real->id, $real->aktion_id);
        $this->assertSame($this->test->id, $test->aktion_id);
        $this->assertSame('TEST-AR-'.now()->year.'-0001', $test->number);
        $this->assertSame(1, $this->tenant->fresh()->order_sequence, 'real numbering has no gap');
        $this->assertTrue($this->getJson("/api/tenants/{$this->tenant->slug}/orders/{$test->token}")->json('is_test'));
    }

    public function test_test_checkout_replaces_stripe(): void
    {
        $this->usePreview();
        $order = $this->submitOrder($this->tenant, 'online');

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $payment = $order->payments()->first();
        $this->assertNull($payment->stripe_checkout_session_id);

        $base = "/api/tenants/{$this->tenant->slug}/orders/{$order->token}";
        $this->postJson("$base/test-payment", ['outcome' => 'cancelled'])->assertOk()->assertJsonPath('url', $order->storefrontUrl().'?neu=1');
        $this->assertSame(PaymentState::Pending, $payment->fresh()->status);

        $this->postJson("$base/test-payment", ['outcome' => 'paid'])->assertOk()->assertJsonPath('url', $order->storefrontUrl().'?neu=1&bezahlt=1');
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->envelope()->subject === "[TEST] Deine Bestellung {$order->number}");

        $this->postJson("$base/test-payment", ['outcome' => 'paid'])->assertStatus(409);
    }

    public function test_failed_test_payment_and_no_test_checkout_for_real_orders(): void
    {
        $this->usePreview();
        $order = $this->submitOrder($this->tenant, 'online');
        $this->postJson("/api/tenants/{$this->tenant->slug}/orders/{$order->token}/test-payment", ['outcome' => 'failed'])->assertOk();
        $this->assertSame(PaymentState::Failed, $order->payments()->first()->status);

        $this->withHeader('X-Widmungen-Preview', '');
        $real = $this->submitOrder($this->tenant);
        $this->postJson("/api/tenants/{$this->tenant->slug}/orders/{$real->token}/test-payment", ['outcome' => 'paid'])->assertStatus(409);
    }

    public function test_test_drafts_never_move_into_a_real_aktion(): void
    {
        $this->usePreview();
        $draft = Order::firstWhere('token', $this->postJson("/api/tenants/{$this->tenant->slug}/orders", [
            'email' => 'test@example.com',
            'copies' => [['book_id' => $this->tenant->books()->first()->id]],
        ])->json('token'));
        $this->test->update(['status' => AktionStatus::Closed]);

        $this->postJson("/api/tenants/{$this->tenant->slug}/orders/{$draft->token}/submit")->assertStatus(409);
        $this->assertSame($this->test->id, $draft->fresh()->aktion_id);
    }

    public function test_mails_resume_links_and_newsletter(): void
    {
        $this->tenant->update(['newsletter_webhook_url' => 'https://n8n.example.com/hook', 'newsletter_webhook_secret' => 's']);
        Http::fake();
        $this->usePreview();

        $order = $this->submitOrder($this->tenant, overrides: ['newsletter_opt_in' => true]);

        Http::assertNothingSent();
        $this->assertTrue($order->events()->where('type', 'newsletter_skipped_test')->exists());
        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => str_starts_with($mail->envelope()->subject, '[TEST] '));
        $this->assertSame($this->test->previewUrl('/bestellen?t='.$order->token), $order->resumeUrl());
        $this->assertStringContainsString('/vorschau/'.$this->test->preview_token.'?weiter=%2Fbestellen%3Ft%3D', $order->resumeUrl());
    }

    public function test_pending_mails_can_be_sent_right_away(): void
    {
        $this->usePreview();
        $unpaid = $this->submitOrder($this->tenant, 'invoice_advance');
        $draft = Order::firstWhere('token', $this->postJson("/api/tenants/{$this->tenant->slug}/orders", [
            'email' => 'other@example.com',
            'copies' => [['book_id' => $this->tenant->books()->first()->id]],
        ])->json('token'));
        $signed = $this->submitOrder($this->tenant, 'invoice_in_parcel', overrides: ['email' => 'signed@example.com']);
        $signed->update(['status' => OrderStatus::Signed]);
        $signed->log('signed');

        $sent = app(TestMode::class)->sendPendingMails($this->test);

        $this->assertSame(['abandoned' => 1, 'reminders' => 1, 'signed' => 1], $sent);
        Mail::assertQueued(AbandonedOrderMail::class, fn ($mail) => $mail->order->is($draft));
        Mail::assertQueued(PaymentReminderMail::class, fn ($mail) => $mail->order->is($unpaid));
        Mail::assertQueued(OrderSignedMail::class, 1);

        // The delayed job from signing finds the mail sent and skips; sending again finds nothing.
        (new SendSignedMail($signed))->handle();
        Mail::assertQueued(OrderSignedMail::class, 1);
        $this->assertSame(['abandoned' => 0, 'reminders' => 0, 'signed' => 0], app(TestMode::class)->sendPendingMails($this->test));
    }

    public function test_automations_keep_test_and_real_aktionen_apart(): void
    {
        $this->tenant->update(['settings' => [...$this->tenant->settings, 'unpaid_after_deadline' => 'move']]);
        $this->usePreview();
        $unpaid = $this->submitOrder($this->tenant, 'invoice_advance');
        $second = $this->tenant->aktionen()->create([
            'name' => 'Zweite Testaktion', 'status' => AktionStatus::Scheduled, 'is_test' => true,
            'orders_open_at' => now()->addDay(), 'order_deadline' => now()->addWeeks(2),
            'signing_date' => now()->addWeeks(3), 'estimated_shipping_date' => now()->addWeeks(4),
        ]);

        app(UnpaidOrders::class)->handleClosedAktion($this->test);
        $this->assertSame($second->id, $unpaid->fresh()->aktion_id, 'moves to the next test Aktion, not the real one');

        $second->update(['status' => AktionStatus::Done]);
        $other = $this->submitOrder($this->tenant, 'invoice_advance');
        app(UnpaidOrders::class)->handleClosedAktion($this->test);
        $this->assertSame(OrderStatus::Cancelled, $other->fresh()->status, 'no test Aktion to move to: cancelled');

        // A scheduled test Aktion opens although a real one is open.
        $scheduled = $this->tenant->aktionen()->create([
            'name' => 'Geplante Testaktion', 'status' => AktionStatus::Scheduled, 'is_test' => true,
            'orders_open_at' => now()->subMinute(), 'order_deadline' => now()->addWeek(),
            'signing_date' => now()->addWeeks(2), 'estimated_shipping_date' => now()->addWeeks(3),
        ]);
        Artisan::call('aktionen:sync-status');
        $this->assertSame(AktionStatus::Open, $scheduled->fresh()->status);
        $this->assertSame(AktionStatus::Open, $this->real->fresh()->status);
        $this->assertSame($this->real->id, $this->tenant->currentAktion()?->id, 'the dashboard defaults to the real Aktion');
    }

    public function test_invoice_is_stamped(): void
    {
        $this->usePreview();
        $html = app(InvoicePdf::class)->html($this->submitOrder($this->tenant));

        $this->assertStringContainsString('TESTRECHNUNG – NICHT BEZAHLEN', $html);
        $this->assertStringNotContainsString('TESTRECHNUNG', app(InvoicePdf::class)->html($this->withHeader('X-Widmungen-Preview', '')->submitOrder($this->tenant)));
    }

    public function test_only_test_orders_can_be_deleted(): void
    {
        $admin = User::firstWhere('email', 'admin@widmungen.test');
        $real = $this->submitOrder($this->tenant);
        $this->usePreview();
        $test = $this->submitOrder($this->tenant);

        $this->assertTrue($admin->can('delete', $test));
        $this->assertFalse($admin->can('delete', $real));

        $this->assertSame(1, app(TestMode::class)->deleteOrders($this->test));
        $this->assertModelMissing($test);
        $this->assertModelExists($real);

        $this->expectException(RuntimeException::class);
        app(TestMode::class)->deleteOrders($this->real);
    }

    public function test_ending_testing_mode(): void
    {
        $this->usePreview();
        $this->submitOrder($this->tenant);

        try {
            app(TestMode::class)->end($this->test);
            $this->fail('test orders must be deleted first');
        } catch (RuntimeException $e) {
            $this->assertSame('Bitte zuerst die Testbestellungen löschen.', $e->getMessage());
        }

        try {
            app(TestMode::class)->end($this->test, deleteOrders: true);
            $this->fail('the real Aktion is open');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ist bereits offen', $e->getMessage());
        }
        $this->assertSame(0, $this->test->orders()->count());

        $this->real->update(['status' => AktionStatus::Closed]);
        app(TestMode::class)->end($this->test->fresh());

        $this->test->refresh();
        $this->assertFalse($this->test->is_test);
        $this->assertNull($this->test->preview_token);
        $this->assertSame($this->test->id, $this->tenant->openAktion()?->id, 'now the public Aktion');
    }

    public function test_an_aktion_can_be_duplicated_into_a_test_aktion(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
        $this->real->update(['capacity' => 50, 'texts' => ['de' => ['letter' => 'Frohe Festtage!']]]);
        $this->real->books()->updateExistingPivot($this->real->books->first()->id, ['price_override' => 2500]);
        $this->submitOrder($this->tenant);

        Livewire::test(ListAktions::class)
            ->callAction(TestAction::make('duplicateAsTest')->table($this->real), ['name' => 'Herbst zum Testen'])
            ->assertHasNoActionErrors();

        $copy = Aktion::firstWhere('name', 'Herbst zum Testen');
        $this->assertTrue($copy->is_test);
        $this->assertNotNull($copy->preview_token);
        $this->assertSame(AktionStatus::Draft, $copy->status);
        $this->assertSame(50, $copy->capacity);
        $this->assertSame('Frohe Festtage!', $copy->texts['de']['letter']);
        $this->assertEquals($this->real->order_deadline, $copy->order_deadline);
        $this->assertSame($this->real->books->pluck('id')->all(), $copy->books->pluck('id')->all());
        $this->assertSame(2500, $copy->books->first()->pivot->price_override);
        $this->assertSame(0, $copy->orders()->count(), 'orders are not copied');
        $this->assertSame(AktionStatus::Open, $this->real->fresh()->status, 'the original is untouched');
    }

    public function test_deleting_a_test_aktion_deletes_its_orders(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
        $this->usePreview();
        $order = $this->submitOrder($this->tenant);

        Livewire::test(EditAktion::class, ['record' => $this->test->getRouteKey()])->callAction(TestAction::make('delete'));

        $this->assertModelMissing($this->test);
        $this->assertModelMissing($order);
    }

    public function test_admin_actions_on_the_aktion(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
        $this->usePreview();
        $this->submitOrder($this->tenant);
        $this->real->update(['status' => AktionStatus::Closed]);
        $token = $this->test->preview_token;

        $page = Livewire::test(EditAktion::class, ['record' => $this->test->getRouteKey()])
            ->assertSee($this->test->previewUrl())
            ->callAction(TestAction::make('regenerateLink'));
        $this->assertNotSame($token, $this->test->fresh()->preview_token);

        $page->callAction(TestAction::make('endTestMode'), ['delete_orders' => true])->assertHasNoActionErrors();

        $this->assertFalse($this->test->fresh()->is_test);
        $this->assertSame(0, $this->test->orders()->count());

        Livewire::test(EditAktion::class, ['record' => $this->real->getRouteKey()])
            ->assertActionHidden(TestAction::make('endTestMode'));
    }
}
