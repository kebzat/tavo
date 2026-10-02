<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->columns([
                TextColumn::make('author')->label('Kdo')->weight('bold')->searchable()->description(fn (Testimonial $record) => $record->role),
                TextColumn::make('text')->label('Recenze')->limit(80)->wrap(),
                TextColumn::make('person')->label('Komu')->formatStateUsing(fn (?string $state) => Testimonial::PEOPLE[$state] ?? 'Oběma')->badge(),
                IconColumn::make('published')->label('Na webu')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
