<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Schemas\ContentBlocks;
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
            Section::make()->columns(2)->columnSpanFull()->schema([
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

            Section::make('Hlavička')
                ->description('Nepovinné. Vyplněný nadtitulek udělá z hlavičky poutavý úvod dopadové stránky, prázdný nechá střídmou hlavičku pro právní texty.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    TextInput::make('hero_eyebrow')
                        ->label('Nadtitulek')
                        ->helperText('Např. „Pro majitele e-shopů". Zapne velký nadpis přes celou šířku.'),

                    TextInput::make('hero_accent')
                        ->label('Zvýrazněná část nadpisu')
                        ->helperText('Slovo nebo úsek z názvu stránky. Vysází se cihlově kurzívou.'),

                    Toggle::make('hero_cta')
                        ->label('Tlačítka na e-mail a telefon')
                        ->helperText('Kontakty se berou z Nastavení → Kontakt.'),
                ]),

            Section::make('Obsah')
                ->description('Stránka se skládá z bloků. Pořadí měníte tažením, blok jde sbalit.')
                ->columnSpanFull()
                ->schema([
                    ContentBlocks::builder('pages'),
                ]),

            Section::make('SEO')->columns(2)->columnSpanFull()->collapsed()->schema([
                TextInput::make('seo_title')->label('Titulek stránky'),
                Textarea::make('seo_description')->label('Popisek pro vyhledávače')->rows(2),
            ]),
        ]);
    }
}
