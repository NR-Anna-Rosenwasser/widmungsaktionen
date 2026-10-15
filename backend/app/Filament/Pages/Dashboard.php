<?php

namespace App\Filament\Pages;

use App\Enums\AktionStatus;
use App\Models\Aktion;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('aktion')
                ->label('Aktion')
                ->placeholder('Aktuelle Aktion')
                ->options(fn () => Aktion::query()
                    ->whereBelongsTo(filament()->getTenant())
                    ->whereNot('status', AktionStatus::Draft)
                    ->orderByDesc('signing_date')
                    ->pluck('name', 'id')),
        ]);
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
