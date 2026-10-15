<?php

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Filament\Pages\Moderation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    public function test_names_and_wishes_need_review_plain_signatures_dont(): void
    {
        $book = $this->tenant->books()->first()->id;
        $order = $this->submitOrder($this->tenant, copies: [
            ['book_id' => $book, 'recipient_name' => 'Kim'],
            ['book_id' => $book, 'signature_only' => true],
        ]);

        $this->assertSame(
            [ModerationStatus::Pending, ModerationStatus::Approved],
            $order->items->pluck('moderation_status')->all(),
        );
    }

    public function test_widmen_role_can_approve_edit_and_flag(): void
    {
        $order = $this->submitOrder($this->tenant);
        $item = $order->items->first();

        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/widmungswuensche")
            ->assertOk()
            ->assertSee('Für Mia');

        Livewire::test(Moderation::class)
            ->assertCanSeeTableRecords([$item])
            ->callTableAction('flag', $item, ['note' => 'Nachfragen'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(ModerationStatus::Flagged, $item->fresh()->moderation_status);

        Livewire::test(Moderation::class)
            ->filterTable('moderation_status', ModerationStatus::Flagged->value)
            ->assertCanSeeTableRecords([$item])
            ->callTableAction('edit', $item, ['recipient_name' => 'Mia', 'wish' => 'Für Mia, alles Liebe'])
            ->assertHasNoTableActionErrors();

        $item->refresh();
        $this->assertSame(ModerationStatus::Approved, $item->moderation_status);
        $this->assertSame('Für Mia, alles Liebe', $item->wish);

        // Approved items leave the default (pending) view.
        Livewire::test(Moderation::class)->assertCanNotSeeTableRecords([$item]);
    }

    public function test_bulk_approve(): void
    {
        $items = $this->submitOrder($this->tenant)->items->merge($this->submitOrder($this->tenant)->items);

        Livewire::test(Moderation::class)->callTableBulkAction('approveAll', $items);

        $this->assertTrue($items->every(fn ($item) => $item->fresh()->moderation_status === ModerationStatus::Approved));
    }
}
