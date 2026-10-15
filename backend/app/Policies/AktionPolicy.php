<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Aktion;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantRole;

class AktionPolicy
{
    use ChecksTenantRole;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    public function view(User $user, Aktion $aktion): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment], $aktion);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, [TenantRole::Admin]);
    }

    public function update(User $user, Aktion $aktion): bool
    {
        return $this->allows($user, [TenantRole::Admin], $aktion);
    }

    public function delete(User $user, Aktion $aktion): bool
    {
        return $this->allows($user, [TenantRole::Admin], $aktion) && ! $aktion->orders()->exists();
    }
}
