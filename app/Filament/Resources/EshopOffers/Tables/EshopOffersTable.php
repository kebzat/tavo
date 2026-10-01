<?php

namespace App\Filament\Resources\EshopOffers\Tables;

use App\Models\EshopOffer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EshopOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                TextColumn::make('nav_label')->label('Název')->searchable()->weight('bold'),
                TextColumn::make('slug')->label('URL')->prefix('/'),
                IconColumn::make('published')->label('Zveřejněno')->boolean(),
                TextColumn::make('updated_at')->label('Upraveno')->dateTime('j. n. Y H:i'),
            ])
            ->recordActions([
                Action::make('showOnWeb')
                    ->label('Zobrazit')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (EshopOffer $record) => $record->url())
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
