<?php

namespace App\Filament\Tools\Resources\Checklists\RelationManagers;

use App\Enums\ChecklistItemStatus;
use App\Models\ChecklistCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Karty na rozcestníku sdílené stránky. Pět jich je tak akorát,
 * při patnácti se z rozcestníku stane další dlouhý seznam.
 */
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Kategorie';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Název')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($set, $state, $operation) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->label('Adresa')
                ->required()
                ->helperText('Poslední část odkazu: /checklist/{token}/tady.'),

            Textarea::make('description')
                ->label('Popisek na kartě')
                ->rows(2),

            TextInput::make('order_column')
                ->label('Pořadí')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                TextColumn::make('title')
                    ->label('Kategorie')
                    ->weight('bold')
                    ->description(fn (ChecklistCategory $record): ?string => $record->description),

                TextColumn::make('sections_count')
                    ->label('Sekcí')
                    ->counts('sections'),

                TextColumn::make('items_count')
                    ->label('Položek')
                    ->counts('items'),

                TextColumn::make('finished_items_count')
                    ->label('Hotovo')
                    ->counts([
                        'items as finished_items_count' => fn ($query) => $query->whereIn('status', [
                            ChecklistItemStatus::Done->value,
                            ChecklistItemStatus::Skipped->value,
                        ]),
                    ]),
            ])
            ->headerActions([
                CreateAction::make()->label('Přidat kategorii'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
