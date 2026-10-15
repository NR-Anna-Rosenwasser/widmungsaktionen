<?php

namespace App\Filament\Resources\Aktions\Pages;

use App\Filament\Resources\Aktions\AktionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAktions extends ListRecords
{
    protected static string $resource = AktionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
