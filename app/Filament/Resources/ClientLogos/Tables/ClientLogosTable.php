<?php

namespace App\Filament\Resources\ClientLogos\Tables;

use App\Models\ClientLogo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientLogosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')->label('Logo')->collection(ClientLogo::MEDIA_LOGO)->imageHeight(32),
                TextColumn::make('name')->label('Klient')->weight('bold')->searchable(),
                IconColumn::make('published')->label('Na webu')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
