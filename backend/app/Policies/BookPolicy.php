<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Book;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantRole;

class BookPolicy
{
    use ChecksTenantRole;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment]);
    }

    public function view(User $user, Book $book): bool
    {
        return $this->allows($user, [TenantRole::Admin, TenantRole::Fulfillment], $book);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, [TenantRole::Admin]);
    }

    public function update(User $user, Book $book): bool
    {
        return $this->allows($user, [TenantRole::Admin], $book);
    }

    public function delete(User $user, Book $book): bool
    {
        return $this->allows($user, [TenantRole::Admin], $book) && ! $book->aktionen()->exists();
    }
}
