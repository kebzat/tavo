<?php

namespace App\Filament\Tools\Resources\Clients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Klient')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->contact_name),

                TextColumn::make('website_url')
                    ->label('Web')
                    ->url(fn ($record) => $record->website_url, shouldOpenInNewTab: true)
                    ->limit(32),

                TextColumn::make('checklists_count')
                    ->label('Checklistů')
                    ->counts('checklists'),

                IconColumn::make('is_archived')
                    ->label('Archiv')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_archived')
                    ->label('Archivovaní')
                    ->placeholder('Jen aktivní')
                    ->trueLabel('Jen archivovaní')
                    ->falseLabel('Jen aktivní')
                    ->default(false),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
