<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\TenantRole;
use App\Filament\Pages\Versand;
use App\Models\Aktion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SigningService;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * «Widmungsaktion vorbereiten» on «Vorbereiten & Versand».
 */
class PrepareTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected User $fulfillment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();
        $this->fulfillment = User::create(['name' => 'Kim', 'email' => 'kim@example.com', 'password' => 'password'])->fresh();
        $this->tenant->users()->attach($this->fulfillment, ['role' => TenantRole::Fulfillment->value]);
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    protected function prepareAction(): TestAction
    {
        return TestAction::make('prepare')->schemaComponent('prepareActions', schema: 'content');
    }

    public function test_fulfillment_prepares_once_after_the_deadline(): void
    {
        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel');
        $this->submitOrder($this->tenant, 'invoice_advance', overrides: ['email' => 'unpaid@example.com']);
        $this->actingAs($this->fulfillment);

        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/vorbereiten-versand")
            ->assertOk()
            ->assertSee('Widmungsaktion vorbereiten');

        // Before the deadline, with an unchecked wish: not yet. No documents before the stack exists.
        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->assertSee('Bestellschluss ist erst am')
            ->assertSee('Widmungswünsche sind noch nicht geprüft')
            ->assertSee('1 Bestellungen sind unbezahlt')
            ->assertDontSee('Stapelliste (PDF)')
            ->assertActionDisabled($this->prepareAction());

        $this->aktion->update(['order_deadline' => now()->subMinute()]);
        $order->items()->update(['moderation_status' => ModerationStatus::Approved]);

        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->assertSee('Alle Widmungswünsche geprüft')
            ->assertActionEnabled($this->prepareAction())
            ->callAction($this->prepareAction())
            ->assertRedirect(Versand::getUrl(['aktion' => $this->aktion->id]));

        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->assertSee('1 auf dem Stapel')
            ->assertSee('Stapelliste (PDF)')
            // Only admins may undo.
            ->assertDontSee('Vorbereitung zurücksetzen');

        $this->aktion->refresh();
        $this->assertNotNull($this->aktion->prepared_at);
        $this->assertSame(AktionStatus::Signing, $this->aktion->status);
        $this->assertSame(1, $order->fresh()->stack_number);
        $this->assertSame('Diese Aktion ist bereits vorbereitet.', app(SigningService::class)->cannotPrepare($this->aktion));
    }

    public function test_admin_can_undo_until_the_first_book_is_signed(): void
    {
        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel');
        $order->items()->update(['moderation_status' => ModerationStatus::Approved]);
        $this->prepareStack($this->aktion);
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        $unprepare = TestAction::make('unprepare')->schemaComponent('prepareActions', schema: 'content');

        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->callAction($unprepare)
            ->assertRedirect();

        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->assertSee('Widmungsaktion vorbereiten');

        $this->aktion->refresh();
        $this->assertNull($this->aktion->prepared_at);
        $this->assertSame(AktionStatus::Closed, $this->aktion->status);
        $this->assertNull($order->fresh()->stack_number);

        // Prepared again and one book signed: no way back.
        app(SigningService::class)->prepare($this->aktion);
        app(SigningService::class)->sign($order->items->first(), auth()->user());

        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->assertSee('Stapelliste (PDF)')
            ->assertDontSee('Vorbereitung zurücksetzen');
    }

    public function test_test_aktion_can_be_prepared_before_the_deadline(): void
    {
        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel');
        $order->items()->update(['moderation_status' => ModerationStatus::Approved]);
        $this->aktion->forceFill(['is_test' => true])->save();

        $this->assertNull(app(SigningService::class)->cannotPrepare($this->aktion));
        $this->assertSame(1, app(SigningService::class)->prepare($this->aktion));

        // Undone before the deadline: open again.
        app(SigningService::class)->unprepare($this->aktion);
        $this->assertSame(AktionStatus::Open, $this->aktion->fresh()->status);
    }
}
