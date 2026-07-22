<?php

namespace App\Filament\Resources\CaseStudies\Tables;

use App\Models\CaseStudy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CaseStudiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                SpatieMediaLibraryImageColumn::make('thumb')
                    ->label('')
                    ->collection(CaseStudy::MEDIA_THUMB),

                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (CaseStudy $record) => $record->eyebrow),

                TextColumn::make('category.name')
                    ->label('Kategorie')
                    ->badge()
                    ->sortable(),

                TextColumn::make('headline_metric')
                    ->label('Číslo'),

                IconColumn::make('is_featured')
                    ->label('Na homepage')
                    ->boolean(),

                IconColumn::make('published')
                    ->label('Zveřejněno')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('case_study_category_id')
                    ->label('Kategorie')
                    ->relationship('category', 'name'),
                TernaryFilter::make('published')->label('Zveřejněno'),
                TernaryFilter::make('is_featured')->label('Na homepage'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
