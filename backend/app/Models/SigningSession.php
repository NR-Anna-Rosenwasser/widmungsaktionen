<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigningSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'log' => 'array',
            'ended_at' => 'datetime',
        ];
    }

    public function aktion(): BelongsTo
    {
        return $this->belongsTo(Aktion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function record(string $type, array $data = []): void
    {
        $this->log = [...($this->log ?? []), ['type' => $type, 'at' => now()->toIso8601String(), ...$data]];
        $this->save();
    }
}
