<?php

namespace App\Filament\Tools\Resources\Checklists\Tables;

use App\Enums\ChecklistItemStatus;
use App\Filament\Tools\Resources\Checklists\Actions\CreateFromTemplateAction;
use App\Models\Checklist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            // Progres čteme z agregace v dotazu, ne z modelu — jinak by každý
            // řádek tabulky spustil vlastní dotaz na položky.
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'items',
                'items as finished_items_count' => fn ($sub) => $sub->whereIn('status', [
                    ChecklistItemStatus::Done->value,
                    ChecklistItemStatus::Skipped->value,
                ]),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Checklist')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Checklist $record): ?string => $record->client?->name),

                IconColumn::make('is_template')
                    ->label('Šablona')
                    ->boolean(),

                TextColumn::make('finished_items_count')
                    ->label('Hotovo')
                    ->badge()
                    ->state(fn (Checklist $record): string => self::percent($record).' %')
                    ->color(fn (Checklist $record): string => match (true) {
                        self::percent($record) >= 100 => 'success',
                        self::percent($record) >= 50 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('items_count')
                    ->label('Položek'),

                IconColumn::make('is_public')
                    ->label('Sdíleno')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                CreateFromTemplateAction::make()
                    ->visible(fn (Checklist $record): bool => $record->is_template),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    private static function percent(Checklist $record): int
    {
        $total = (int) $record->items_count;

        return $total > 0
            ? (int) round((int) $record->finished_items_count / $total * 100)
            : 0;
    }
}
