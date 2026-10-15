<?php

namespace App\Filament\Resources\Aktions\Pages;

use App\Filament\Resources\Aktions\AktionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAktion extends EditRecord
{
    protected static string $resource = AktionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
