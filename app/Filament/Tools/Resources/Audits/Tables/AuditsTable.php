<?php

namespace App\Filament\Tools\Resources\Audits\Tables;

use App\Models\Audit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('audited_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Audit')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Audit $record): ?string => $record->client?->name),

                TextColumn::make('audited_at')
                    ->label('Stav k')
                    ->date('j. n. Y')
                    ->sortable(),

                IconColumn::make('is_public')
                    ->label('Sdíleno')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('openPublicUrl')
                    ->label('Otevřít')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Audit $record): ?string => $record->publicUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Audit $record): bool => $record->publicUrl() !== null),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
