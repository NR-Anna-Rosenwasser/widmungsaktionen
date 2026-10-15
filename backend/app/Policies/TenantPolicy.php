<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function create(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasRoleIn($tenant, TenantRole::Admin);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin;
    }
}
