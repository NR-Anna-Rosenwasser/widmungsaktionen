<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(fn (self $domain) => $domain->domain = strtolower(trim($domain->domain)));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
