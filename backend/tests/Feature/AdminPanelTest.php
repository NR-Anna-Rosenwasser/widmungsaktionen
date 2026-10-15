<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Filament\Resources\Aktions\Pages\CreateAktion;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
    }

    protected function adminUrl(string $path = ''): string
    {
        return 'https://'.config('widmungen.admin_domain').'/'.ltrim($path, '/');
    }

    public function test_super_admin_can_open_all_pages(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        $slug = $this->tenant->slug;
        $aktion = $this->tenant->aktionen()->first();
        $book = $this->tenant->books()->first();

        foreach ([
            $slug,
            "$slug/orders",
            "$slug/books",
            "$slug/books/create",
            "$slug/books/{$book->id}/edit",
            "$slug/aktionen",
            "$slug/aktionen/create",
            "$slug/aktionen/{$aktion->id}/edit",
            "$slug/profile",
            'new',
        ] as $path) {
            $this->assertSame(200, $this->get($this->adminUrl($path))->status(), $path);
        }
    }

    public function test_widmen_role_cannot_manage_books_or_settings(): void
    {
        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));
        $slug = $this->tenant->slug;

        $this->get($this->adminUrl($slug))->assertOk();
        $this->get($this->adminUrl("$slug/books"))->assertForbidden();
        // Filament hides pages the user may not view.
        $this->get($this->adminUrl("$slug/profile"))->assertNotFound();
        $this->get($this->adminUrl('new'))->assertNotFound();
    }

    public function test_only_one_aktion_can_be_open_per_tenant(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);

        Livewire::test(CreateAktion::class)
            ->fillForm([
                'name' => 'Zweite offene Aktion',
                'status' => AktionStatus::Open,
                'orders_open_at' => now(),
                'order_deadline' => now()->addWeek(),
                'signing_date' => now()->addWeeks(2)->toDateString(),
                'estimated_shipping_date' => now()->addWeeks(3)->toDateString(),
                'books' => $this->tenant->books()->pluck('id')->all(),
            ])
            ->call('create')
            ->assertHasFormErrors(['status']);
    }

    public function test_super_admin_can_create_a_tenant_with_its_first_admin(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));

        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => 'Rotpunktverlag',
                'slug' => 'rotpunkt',
                'order_prefix' => 'RP',
                'contact_email' => 'shop@example.com',
                'letterhead' => "Rotpunktverlag\nHohlstrasse 86a\n8004 Zürich",
                'creditor_name' => 'Rotpunktverlag',
                'creditor_street' => 'Hohlstrasse',
                'creditor_building_number' => '86a',
                'creditor_postal_code' => '8004',
                'creditor_town' => 'Zürich',
                'admin_name' => 'Verlag',
                'admin_email' => 'verlag@example.com',
                'admin_password' => 'a-long-password-123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $tenant = Tenant::firstWhere('slug', 'rotpunkt');
        $this->assertNotNull($tenant);
        $this->assertSame('admin', $tenant->users()->first()->pivot->role);
    }
}
