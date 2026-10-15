<?php

namespace Tests\Feature;

use App\Enums\TenantRole;
use App\Exceptions\TeamException;
use App\Filament\Resources\Team\Pages\ListTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TeamService;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected TeamService $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->team = app(TeamService::class);
    }

    public function test_new_people_get_a_password_link(): void
    {
        Notification::fake();

        $user = $this->team->add($this->tenant, 'Kim', 'kim@example.com', TenantRole::Fulfillment);

        $this->assertSame(TenantRole::Fulfillment, $user->roleIn($this->tenant));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_existing_accounts_are_linked_without_mail(): void
    {
        Notification::fake();
        $existing = User::create(['name' => 'Kim', 'email' => 'kim@example.com', 'password' => 'secret-password']);

        $user = $this->team->add($this->tenant, 'Kim', 'KIM@example.com', TenantRole::Admin);

        $this->assertTrue($user->is($existing));
        Notification::assertNothingSent();
    }

    public function test_people_cannot_be_added_twice(): void
    {
        $this->expectException(TeamException::class);

        $this->team->add($this->tenant, 'Anna', 'anna@widmungen.test', TenantRole::Admin);
    }

    public function test_last_admin_cannot_be_demoted_or_removed(): void
    {
        $admin = $this->team->add($this->tenant, 'Kim', 'kim@example.com', TenantRole::Admin);

        try {
            $this->team->changeRole($this->tenant, $admin, TenantRole::Widmen);
            $this->fail('Demoting the last admin should fail.');
        } catch (TeamException) {
        }

        $this->expectException(TeamException::class);
        $this->team->remove($this->tenant, $admin);
    }

    public function test_admins_can_open_the_team_page(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        $this->get('https://'.config('widmungen.admin_domain').'/anna-rosenwasser/team')->assertOk();
    }

    public function test_other_roles_cannot_open_the_team_page(): void
    {
        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));
        $this->get('https://'.config('widmungen.admin_domain').'/anna-rosenwasser/team')->assertForbidden();
    }

    public function test_team_page_lists_only_this_shops_people(): void
    {
        $other = Tenant::create(['name' => 'Anderer Shop', 'slug' => 'anderer-shop', 'order_prefix' => 'AS', 'settings' => []]);
        $stranger = User::create(['name' => 'Fremd', 'email' => 'fremd@example.com', 'password' => 'secret-password']);
        $other->users()->attach($stranger, ['role' => 'admin']);

        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);

        Livewire::test(ListTeam::class)
            ->assertCanSeeTableRecords([User::firstWhere('email', 'anna@widmungen.test')])
            ->assertCanNotSeeTableRecords([$stranger]);
    }
}
