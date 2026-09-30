<?php

namespace App\Filament\Tools\Resources\Proposals\Tables;

use App\Models\Proposal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Proposal $record): string => '/potencialni-spoluprace/'.$record->slug),

                TextColumn::make('prepared_at')
                    ->label('Připraveno')
                    ->date('j. n. Y')
                    ->sortable(),

                IconColumn::make('is_public')
                    ->label('Sdíleno')
                    ->boolean(),

                TextColumn::make('view_count')
                    ->label('Otevřeno')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? $state.'×' : 'ne')
                    ->description(fn (Proposal $record): ?string => $record->last_viewed_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (Proposal $record): string => $record->is_public ? 'Otevřít' : 'Náhled')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Proposal $record): ?string => $record->previewUrl(), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
