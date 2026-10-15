<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Aktion;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * The Aktion chosen in the dashboard filter, or the current one.
 */
trait ResolvesAktion
{
    use InteractsWithPageFilters;

    protected function aktion(): ?Aktion
    {
        $tenant = filament()->getTenant();
        $id = $this->pageFilters['aktion'] ?? null;

        return $id
            ? Aktion::query()->whereBelongsTo($tenant)->find($id)
            : $tenant->currentAktion();
    }
}
