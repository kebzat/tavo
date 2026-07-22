<?php

namespace App\Filament\Resources\CaseStudies\Schemas;

use App\Models\CaseStudy;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CaseStudyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([

                Tab::make('Základ')->schema([
                    Section::make('Identifikace')->columns(2)->schema([
                        TextInput::make('title')
                            ->label('Název projektu')
                            ->required()
                            ->maxLength(255)
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
                            ->helperText('Např. „rodinny-eshop" → /reference/rodinny-eshop'),

                        Select::make('case_study_category_id')
                            ->label('Kategorie')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('order_column')
                            ->label('Pořadí')
                            ->numeric()
                            ->default(0)
                            ->helperText('Nižší číslo = výš ve výpisu.'),

                        Toggle::make('published')
                            ->label('Zveřejněno')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Vypíchnout na homepage')
                            ->helperText('Zobrazí se v sekci „Vybrané projekty".'),
                    ]),

                    Section::make('Výpis (homepage a /reference)')->columns(2)->schema([
                        TextInput::make('eyebrow')
                            ->label('Nadřazený popisek')
                            ->helperText('Např. „E-commerce · Redesign + kampaně"'),

                        TextInput::make('headline_metric')
                            ->label('Hlavní číslo')
                            ->helperText('Velké číslo u dlaždice ve výpisu, např. „+41 %".'),

                        Textarea::make('excerpt')
                            ->label('Krátký popis')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('thumb_label')
                            ->label('Popisek zástupného vizuálu')
                            ->helperText('Zobrazí se v šrafovaném poli, dokud nenahrajete fotku.')
                            ->columnSpanFull(),

                        Repeater::make('tags')
                            ->label('Štítky')
                            ->simple(TextInput::make('text')->required())
                            ->columnSpanFull()
                            ->defaultItems(0),
                    ]),

                    Section::make('Obrázky')->columns(2)->schema([
                        SpatieMediaLibraryFileUpload::make('thumb')
                            ->label('Náhled ve výpisu')
                            ->collection(CaseStudy::MEDIA_THUMB)
                            ->image()
                            ->imageEditor()
                            ->helperText('Doporučený poměr 4:3, min. 1200 px na šířku.'),

                        SpatieMediaLibraryFileUpload::make('hero')
                            ->label('Hlavní vizuál na detailu')
                            ->collection(CaseStudy::MEDIA_HERO)
                            ->image()
                            ->imageEditor()
                            ->helperText('Doporučený poměr 16:8, min. 2000 px na šířku.'),
                    ]),
                ]),

                Tab::make('Detail')->schema([
                    Section::make('Úvod detailu')->columns(2)->schema([
                        TextInput::make('hero_headline')
                            ->label('Nadpis — první část'),
                        TextInput::make('hero_headline_accent')
                            ->label('Nadpis — zvýrazněná část')
                            ->helperText('Vysází se cihlově kurzívou.'),
                        Textarea::make('hero_perex')
                            ->label('Perex')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                    Section::make('Údaje o projektu')->columns(4)->schema([
                        TextInput::make('client')->label('Klient'),
                        TextInput::make('industry')->label('Obor'),
                        TextInput::make('scope')->label('Rozsah'),
                        TextInput::make('duration')->label('Doba'),
                    ]),

                    Section::make('Výchozí stav')->schema([
                        TextInput::make('problem_title')->label('Nadpis sekce')->default('Výchozí stav'),
                        Textarea::make('problem_text')->label('Text')->rows(4),
                        Repeater::make('problem_points')
                            ->label('Odrážky')
                            ->simple(TextInput::make('text')->required())
                            ->defaultItems(0),
                    ]),

                    Section::make('Role marketingu a vývoje')->schema([
                        TextInput::make('roles_title')->label('Nadpis sekce')->default('Jak jsme to táhli spolu'),
                        Textarea::make('roles_perex')->label('Perex')->rows(2),

                        TextInput::make('marketing_title')->label('Nadpis levého sloupce')->default('Role marketingu — Pavel'),
                        Repeater::make('marketing_items')
                            ->label('Body marketingu')
                            ->simple(TextInput::make('text')->required())
                            ->defaultItems(0),

                        TextInput::make('dev_title')->label('Nadpis pravého sloupce')->default('Role vývoje — Tom'),
                        Repeater::make('dev_items')
                            ->label('Body vývoje')
                            ->simple(TextInput::make('text')->required())
                            ->defaultItems(0),
                    ]),
                ]),

                Tab::make('Výsledky')->schema([
                    Section::make('Čísla')->schema([
                        Repeater::make('results')
                            ->label('Metriky')
                            ->schema([
                                TextInput::make('value')->label('Hodnota')->required()->helperText('Např. „+41 %"'),
                                TextInput::make('label')->label('Popisek')->required()->helperText('Např. „tržby z online za 4 měsíce"'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->helperText('První dvě metriky se použijí i na homepage.'),

                        Textarea::make('disclaimer')
                            ->label('Poznámka pod čísly')
                            ->rows(2),
                    ]),

                    Section::make('Citace klienta')->columns(2)->schema([
                        Textarea::make('quote')->label('Citace')->rows(3)->columnSpanFull(),
                        TextInput::make('quote_author')->label('Kdo to řekl'),
                    ]),
                ]),

                Tab::make('SEO')->schema([
                    TextInput::make('seo_title')
                        ->label('Titulek stránky')
                        ->helperText('Nechte prázdné a použije se název projektu.'),
                    Textarea::make('seo_description')
                        ->label('Popisek pro vyhledávače')
                        ->rows(3)
                        ->helperText('Nechte prázdné a použije se krátký popis.'),
                ]),
            ]),
        ]);
    }
}
