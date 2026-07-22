<?php

namespace App\Filament\Resources\CaseStudyCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CaseStudyCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Název')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($set, $state, $operation) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->label('URL adresa')
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Používá se ve filtru: /reference?kategorie=…'),

            TextInput::make('order_column')
                ->label('Pořadí')
                ->numeric()
                ->default(0),
        ]);
    }
}
