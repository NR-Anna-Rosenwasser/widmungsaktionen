<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Filament\Pages\Widmen;
use App\Models\Aktion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Motivation;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class WidmenMotivationTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();

        // Twelve copies, no checkpoints in between.
        $this->tenant->update(['settings' => ['checkpoint_interval' => 50]]);
        $book = $this->tenant->books()->first()->id;
        foreach (range(1, 3) as $_) {
            $order = $this->submitOrder($this->tenant, 'invoice_in_parcel', array_fill(0, 4, ['book_id' => $book, 'signature_only' => true]));
            $order->items()->update(['moderation_status' => ModerationStatus::Approved]);
        }
        $this->prepareStack($this->aktion);

        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    protected function signing(): Testable
    {
        return Livewire::test(Widmen::class)
            ->call('start')
            ->set('checkpointInput', '1')->call('submitCheckpoint')
            ->assertSet('mode', 'sign');
    }

    public function test_there_are_about_a_hundred_distinct_messages(): void
    {
        $this->assertGreaterThanOrEqual(100, count(Motivation::MESSAGES));
        $this->assertSame(count(Motivation::MESSAGES), count(array_unique(Motivation::MESSAGES)));

        foreach (range(1, 50) as $_) {
            $gap = Motivation::gap();
            $this->assertTrue($gap >= 5 && $gap <= 15);
        }

        // No repeats until every message was shown once.
        $seen = [];
        foreach (Motivation::MESSAGES as $_) {
            $seen[] = Motivation::pick($seen);
        }
        $this->assertSame(array_keys(Motivation::MESSAGES), collect($seen)->sort()->values()->all());
    }

    public function test_a_message_appears_after_some_books_and_fades_after_a_few_more(): void
    {
        $page = $this->signing()
            ->assertSee('Motivationssprüche anzeigen')
            ->set('motivationIn', 2)
            ->call('sign')
            ->assertSet('motivation', null)
            ->call('sign');

        $index = $page->get('motivation');
        $this->assertNotNull($index);
        $page->assertSee(Motivation::MESSAGES[$index]);
        $this->assertTrue($page->get('motivationIn') >= 5 && $page->get('motivationIn') <= 15);

        // Stays for a few books, then disappears.
        $page->call('sign')->call('sign')->assertSet('motivation', $index)
            ->call('sign')->assertSet('motivation', null)
            ->assertDontSee(Motivation::MESSAGES[$index]);
    }

    public function test_a_message_can_be_dismissed(): void
    {
        $this->signing()
            ->set('motivationIn', 1)
            ->call('sign')
            ->assertNotSet('motivation', null)
            ->call('dismissMotivation')
            ->assertSet('motivation', null);
    }

    public function test_turned_off_for_the_session(): void
    {
        $this->signing()
            ->call('toggleMotivation')
            ->assertSet('motivationOn', false)
            ->set('motivationIn', 1)
            ->call('sign')
            ->assertSet('motivation', null);

        // Kept for the browser session, e.g. after a reload.
        Livewire::test(Widmen::class)->assertSet('motivationOn', false);
    }

    public function test_turned_off_for_the_shop(): void
    {
        $this->tenant->update(['settings' => [...($this->tenant->settings ?? []), 'widmen_motivation' => false]]);

        $this->signing()
            ->assertDontSee('Motivationssprüche anzeigen')
            ->set('motivationIn', 1)
            ->call('sign')
            ->assertSet('motivation', null);
    }
}
