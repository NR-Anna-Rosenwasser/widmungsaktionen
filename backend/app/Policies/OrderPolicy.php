<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantRole;

class OrderPolicy
{
    use ChecksTenantRole;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment], $order);
    }

    public function create(User $user): bool
    {
        // Orders come from the storefront.
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment], $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
