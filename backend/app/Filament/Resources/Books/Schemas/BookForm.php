<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Filament\Schemas\TenantSettingsForm;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')->label('Titel')->required()->maxLength(255),
                        TextInput::make('subtitle')->label('Untertitel')->maxLength(255),
                        TextInput::make('author_name')
                            ->label('Autor*in')
                            ->required()
                            ->default(fn () => filament()->getTenant()?->name),
                        Textarea::make('description')->label('Kurzbeschreibung')->rows(4),
                        Grid::make(3)->schema([
                            TenantSettingsForm::money('price', 'Preis (CHF)'),
                            TextInput::make('weight_grams')->label('Gewicht (g)')->numeric()->minValue(1),
                            TextInput::make('isbn')->label('ISBN')->maxLength(20),
                        ]),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        FileUpload::make('cover_path')
                            ->label('Cover')
                            ->image()
                            ->disk('public')
                            ->directory('covers')
                            ->imageEditor(),
                        Toggle::make('is_active')->label('Aktiv')->default(true),
                        TextInput::make('sort')->label('Reihenfolge')->numeric()->default(0),
                    ]),
            ]);
    }
}
