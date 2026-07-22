<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([

                Tab::make('Základ')->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('title')
                            ->label('Název služby')
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
                            ->unique(ignoreRecord: true),

                        TextInput::make('number')
                            ->label('Číslo')
                            ->maxLength(8)
                            ->helperText('Zobrazí se vlevo v seznamu na homepage, např. „01".'),

                        TextInput::make('order_column')
                            ->label('Pořadí')
                            ->numeric()
                            ->default(0),

                        Toggle::make('published')->label('Zveřejněno')->default(true),

                        Toggle::make('has_detail_page')
                            ->label('Má vlastní stránku')
                            ->helperText('Zapněte, pokud má služba mít detail na /sluzby/…')
                            ->live(),

                        Textarea::make('excerpt')
                            ->label('Popis v seznamu na homepage')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                ]),

                Tab::make('Detailní stránka')
                    ->visible(fn ($get) => (bool) $get('has_detail_page'))
                    ->schema([
                        Section::make('Úvod')->columns(2)->schema([
                            TextInput::make('hero_eyebrow')->label('Nadřazený popisek'),
                            TextInput::make('hero_headline')->label('Nadpis — první část'),
                            TextInput::make('hero_headline_accent')
                                ->label('Nadpis — zvýrazněná část')
                                ->helperText('Vysází se cihlově kurzívou.'),
                            Textarea::make('hero_perex')->label('Perex')->rows(3)->columnSpanFull(),
                        ]),

                        Section::make('Pro koho')->schema([
                            TextInput::make('target_group_title')->label('Nadpis sekce'),
                            Repeater::make('target_groups')
                                ->label('Odrážky')
                                ->addActionLabel('Přidat odrážku')
                                ->simple(TextInput::make('text')->required())
                                ->defaultItems(0),
                        ]),

                        Section::make('Co stavíme')->schema([
                            TextInput::make('offerings_title')->label('Nadpis sekce'),
                            Repeater::make('offerings')
                                ->label('Boxy')
                                ->addActionLabel('Přidat box')
                                ->schema([
                                    TextInput::make('title')->label('Nadpis')->required(),
                                    Textarea::make('text')->label('Text')->rows(2)->required(),
                                ])
                                ->defaultItems(0),
                        ]),

                        Section::make('Jak to probíhá')->schema([
                            TextInput::make('process_title')->label('Nadpis sekce'),
                            Repeater::make('process_steps')
                                ->label('Kroky')
                                ->addActionLabel('Přidat krok')
                                ->schema([
                                    TextInput::make('number')->label('Číslo')->required(),
                                    TextInput::make('title')->label('Název')->required(),
                                    Textarea::make('text')->label('Popis')->rows(2),
                                ])
                                ->columns(3)
                                ->defaultItems(0),
                        ]),
                    ]),

                Tab::make('SEO')
                    ->visible(fn ($get) => (bool) $get('has_detail_page'))
                    ->schema([
                        TextInput::make('seo_title')->label('Titulek stránky'),
                        Textarea::make('seo_description')->label('Popisek pro vyhledávače')->rows(3),
                    ]),
            ]),
        ]);
    }
}
