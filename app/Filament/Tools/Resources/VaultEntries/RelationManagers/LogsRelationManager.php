<?php

namespace App\Filament\Tools\Resources\VaultEntries\RelationManagers;

use App\Models\VaultLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Kdo kdy přístup použil nebo zobrazil. Jen ke čtení. */
class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Historie';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Kdy')->dateTime('j. n. Y H:i:s', 'Europe/Prague')->color('gray'),
                TextColumn::make('actor')->label('Kdo')->badge()->color(fn (string $state): string => $state === 'agent' ? 'info' : 'gray'),
                TextColumn::make('action')->label('Co')->formatStateUsing(fn (VaultLog $record): string => $record->actionLabel())->weight('medium'),
                TextColumn::make('detail')->label('Podrobnosti')->wrap()->limit(300)->color('gray'),
            ])
            ->filters([
                SelectFilter::make('actor')->label('Kdo')->options(['agent' => 'Agent'])->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                    'agent' => $query->where('actor', 'agent'),
                    default => $query,
                }),
            ]);
    }
}
