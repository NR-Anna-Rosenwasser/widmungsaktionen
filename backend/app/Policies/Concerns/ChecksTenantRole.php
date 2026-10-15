<?php

namespace App\Policies\Concerns;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

trait ChecksTenantRole
{
    /**
     * @param  array<TenantRole>  $roles
     */
    protected function allows(User $user, array $roles, ?Model $record = null): bool
    {
        $tenant = $record?->tenant ?? Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $user->hasRoleIn($tenant, $roles);
    }
}
