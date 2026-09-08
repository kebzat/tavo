<?php

namespace App\Filament\Resources\WebTexts\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class WebTextsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('group')->titlePrefixedWithLabel(false))
            ->defaultSort('key')
            ->columns([
                TextColumn::make('key')->label('Klíč')->searchable()->weight('bold')->size('sm'),
                TextColumn::make('value')->label('Text')->searchable()->limit(80)->wrap(),
                TextColumn::make('note')->label('Kde je')->toggleable()->limit(40),
            ])
            ->recordActions([
                EditAction::make(),
                // Smazání není ztráta: klíč se při dalším vykreslení stránky
                // založí znovu s původním zněním ze šablony.
                DeleteAction::make()
                    ->label('Vrátit původní')
                    ->modalHeading('Vrátit původní text?')
                    ->modalDescription('Vaše úprava se zahodí a na webu se objeví znění, které je v šabloně.')
                    ->modalSubmitActionLabel('Vrátit'),
            ]);
    }
}
