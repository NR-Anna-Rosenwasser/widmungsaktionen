<?php

namespace Tests\Feature;

use App\Filament\Widgets\AktionOverview;
use App\Filament\Widgets\OrderFunnel;
use App\Filament\Widgets\TodoOverview;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([DatabaseSeeder::class, DemoOrdersSeeder::class]);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
    }

    public function test_dashboard_shows_the_current_aktion(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));

        $this->get('https://'.config('widmungen.admin_domain').'/anna-rosenwasser')
            ->assertOk()
            ->assertSee('Herbst-Widmungsaktion 2026');

        Filament::setTenant($this->tenant);
        // 43 demo orders: 4 drafts, 3 cancelled → 36 active, 10 unpaid.
        Livewire::test(AktionOverview::class)->assertSee('36')->assertSee('Zahlung offen');
        Livewire::test(OrderFunnel::class)->assertSee('Begonnen')->assertSee('43');
        Livewire::test(TodoOverview::class)->assertSee('Widmungswünsche prüfen');
    }

    public function test_widmen_role_only_sees_the_to_do_list(): void
    {
        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));

        $this->get('https://'.config('widmungen.admin_domain').'/anna-rosenwasser')->assertOk();

        Filament::setTenant($this->tenant);
        $this->assertTrue(TodoOverview::canView());
        $this->assertFalse(AktionOverview::canView());
        $this->assertFalse(OrderFunnel::canView());
    }
}
