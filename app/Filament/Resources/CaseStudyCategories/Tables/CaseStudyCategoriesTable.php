<?php

namespace App\Filament\Resources\CaseStudyCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CaseStudyCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                TextColumn::make('name')->label('Název')->searchable()->weight('bold'),
                TextColumn::make('slug')->label('URL'),
                TextColumn::make('case_studies_count')->label('Referencí')->counts('caseStudies'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
