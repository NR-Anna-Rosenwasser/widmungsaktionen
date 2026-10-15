<?php

namespace App\Services;

use App\Enums\TenantRole;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Membership of users in a tenant's team.
 */
class TeamService
{
    /**
     * Add a person by email. Existing accounts are linked; new accounts get
     * a link to set their password.
     */
    public function add(Tenant $tenant, string $name, string $email, TenantRole $role): User
    {
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($email)])->first();

        if ($user && $tenant->users()->whereKey($user)->exists()) {
            throw new TeamException('Diese Person ist bereits im Team.');
        }

        $isNew = ! $user;
        $user ??= User::create([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
        ]);

        $tenant->users()->attach($user, ['role' => $role->value]);

        if ($isNew) {
            $this->sendPasswordSetupLink($user);
        }

        return $user;
    }

    public function changeRole(Tenant $tenant, User $user, TenantRole $role): void
    {
        if ($role !== TenantRole::Admin) {
            $this->ensureNotLastAdmin($tenant, $user);
        }

        $tenant->users()->updateExistingPivot($user->id, ['role' => $role->value]);
    }

    public function remove(Tenant $tenant, User $user): void
    {
        $this->ensureNotLastAdmin($tenant, $user);

        $tenant->users()->detach($user);
    }

    public function sendPasswordSetupLink(User $user): void
    {
        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $user->email],
            function (User $user, string $token) {
                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);
                $user->notify($notification);
            },
        );
    }

    protected function ensureNotLastAdmin(Tenant $tenant, User $user): void
    {
        $admins = $tenant->users()->wherePivot('role', TenantRole::Admin->value)->pluck('users.id');

        if ($admins->count() === 1 && $admins->first() === $user->id) {
            throw new TeamException('Jeder Shop braucht mindestens eine*n Admin.');
        }
    }
}
