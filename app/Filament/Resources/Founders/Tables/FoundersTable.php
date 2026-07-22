<?php

namespace App\Filament\Resources\Founders\Tables;

use App\Models\Founder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FoundersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('')
                    ->collection(Founder::MEDIA_PHOTO)
                    ->circular(),
                TextColumn::make('name')->label('Jméno')->weight('bold')->searchable(),
                TextColumn::make('role_label')->label('Role'),
                TextColumn::make('external_url')->label('Web')->limit(30),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
