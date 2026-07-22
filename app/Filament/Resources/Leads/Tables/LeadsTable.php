<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Přišlo')
                    ->dateTime('j. n. Y H:i')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Jméno')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Lead $record) => $record->company),

                TextColumn::make('email')
                    ->label('Kontakt')
                    ->searchable()
                    ->copyable()
                    ->description(fn (Lead $record) => $record->phone),

                TextColumn::make('topic')->label('O co jde'),
                TextColumn::make('budget')->label('Rozpočet'),

                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Lead::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stav')->options(Lead::STATUSES),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
