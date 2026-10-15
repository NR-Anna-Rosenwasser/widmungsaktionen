<?php

namespace App\Filament\Resources\Books\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('cover_path')->label('')->disk('public')->imageHeight(56),
                TextColumn::make('title')->label('Titel')->description(fn ($record) => $record->subtitle)->searchable(),
                TextColumn::make('author_name')->label('Autor*in')->toggleable(),
                TextColumn::make('price')->label('Preis')->money('CHF', divideBy: 100),
                ToggleColumn::make('is_active')->label('Aktiv'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
