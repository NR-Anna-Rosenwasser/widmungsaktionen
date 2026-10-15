<?php

namespace App\Models;

use App\Enums\TenantRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_super_admin || $this->tenants()->exists();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->is_super_admin ? Tenant::query()->orderBy('name')->get() : $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_super_admin || $this->tenants()->whereKey($tenant)->exists();
    }

    public function roleIn(Tenant $tenant): ?TenantRole
    {
        if ($this->is_super_admin) {
            return TenantRole::Admin;
        }

        $role = $this->tenants()->whereKey($tenant)->first()?->pivot->role;

        return $role ? TenantRole::from($role) : null;
    }

    /**
     * @param  TenantRole|array<TenantRole>  $roles
     */
    public function hasRoleIn(Tenant $tenant, TenantRole|array $roles): bool
    {
        return in_array($this->roleIn($tenant), is_array($roles) ? $roles : [$roles], true);
    }
}
