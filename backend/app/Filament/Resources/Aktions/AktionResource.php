<?php

namespace App\Filament\Resources\Aktions;

use App\Filament\Resources\Aktions\Pages\CreateAktion;
use App\Filament\Resources\Aktions\Pages\EditAktion;
use App\Filament\Resources\Aktions\Pages\ListAktions;
use App\Filament\Resources\Aktions\Schemas\AktionForm;
use App\Filament\Resources\Aktions\Tables\AktionsTable;
use App\Models\Aktion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AktionResource extends Resource
{
    protected static ?string $model = Aktion::class;

    protected static ?string $slug = 'aktionen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Aktion';

    protected static ?string $pluralModelLabel = 'Aktionen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AktionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AktionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAktions::route('/'),
            'create' => CreateAktion::route('/create'),
            'edit' => EditAktion::route('/{record}/edit'),
        ];
    }
}
