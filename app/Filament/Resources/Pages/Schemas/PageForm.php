<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')
                    ->label('Název stránky')
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
                    ->helperText('Např. „cookies" → /cookies'),

                Toggle::make('published')->label('Zveřejněno')->default(true),

                Textarea::make('perex')->label('Perex')->rows(2)->columnSpanFull(),
            ]),

            Section::make('Obsah')->schema([
                RichEditor::make('content')
                    ->label('')
                    ->columnSpanFull(),
            ]),

            Section::make('SEO')->columns(2)->collapsed()->schema([
                TextInput::make('seo_title')->label('Titulek stránky'),
                Textarea::make('seo_description')->label('Popisek pro vyhledávače')->rows(2),
            ]),
        ]);
    }
}
