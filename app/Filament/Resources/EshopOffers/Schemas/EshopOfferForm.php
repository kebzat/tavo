<?php

namespace App\Filament\Resources\EshopOffers\Schemas;

use App\Models\EshopOffer;
use App\Rules\FreeTopLevelSlug;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EshopOfferForm
{
    private const PARAGRAPHS_HELP = 'Odstavce oddělte prázdným řádkem.';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([

                Tab::make('Základ')->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('nav_label')
                            ->label('Krátký název')
                            ->helperText('V patičce ve skupině „Pro e-shopy" a v rozcestníku na konci ostatních nabídek.')
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
                            ->alphaDash()
                            ->rule(fn (?EshopOffer $record) => new FreeTopLevelSlug($record))
                            ->helperText('Např. „rozvoj-eshopu" → /rozvoj-eshopu. Po spuštění neměňte, odkazy a pozice ve vyhledávání vedou na starou adresu.'),

                        Toggle::make('published')
                            ->label('Zveřejněno')
                            ->helperText('Vypnutá nabídka zmizí z patičky i sitemapy a její adresa vrátí 404.')
                            ->default(true),
                    ]),

                    Section::make('Úvod')->schema([
                        TextInput::make('headline')
                            ->label('Nadpis (H1)')
                            ->helperText('Zobrazí se i na kartě v rozcestníku pod krátkým názvem.')
                            ->required(),

                        Textarea::make('intro')
                            ->label('Text pod nadpisem')
                            ->helperText(self::PARAGRAPHS_HELP)
                            ->rows(8),
                    ]),
                ]),

                Tab::make('Obsah')->schema([
                    Repeater::make('sections')
                        ->label('Sekce')
                        ->helperText('Světlá část pod úvodem: nadpis vlevo, text vpravo.')
                        ->addActionLabel('Přidat sekci')
                        ->schema([
                            TextInput::make('title')->label('Nadpis (H2)')->required(),
                            Textarea::make('text')
                                ->label('Text')
                                ->helperText(self::PARAGRAPHS_HELP)
                                ->rows(8)
                                ->required(),
                        ])
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->collapsible()
                        ->defaultItems(0),
                ]),

                Tab::make('Časté otázky')->schema([
                    Repeater::make('faq')
                        ->label('Otázky')
                        ->helperText('Černá sekce „Časté otázky". Stejné znění dostanou vyhledávače jako strukturovaná data, odpovědi proto pište celé. Bez otázek se sekce nezobrazí.')
                        ->addActionLabel('Přidat otázku')
                        ->schema([
                            TextInput::make('question')->label('Otázka')->required(),
                            Textarea::make('answer')->label('Odpověď')->rows(3)->required(),
                        ])
                        ->itemLabel(fn (array $state) => $state['question'] ?? null)
                        ->collapsible()
                        ->defaultItems(0),
                ]),

                Tab::make('Výzva k akci')->schema([
                    Section::make()
                        ->description('Cihlový pruh na konci stránky. Tlačítko s e-mailem se bere z Nastavení → Kontakt.')
                        ->schema([
                            TextInput::make('cta_title')->label('Nadpis')->required(),
                            TextInput::make('cta_perex')->label('Text pod nadpisem'),
                        ]),
                ]),

                Tab::make('SEO')->schema([
                    TextInput::make('seo_title')
                        ->label('Titulek stránky')
                        ->helperText('Bez „| Taveo", příponu připojí Nastavení → SEO. Prázdné = použije se nadpis.'),

                    Textarea::make('seo_description')
                        ->label('Popisek pro vyhledávače')
                        ->rows(3),

                    TextInput::make('service_type')
                        ->label('Typ služby pro vyhledávače')
                        ->helperText('Jde do strukturovaných dat (schema.org serviceType), na stránce vidět není. Prázdné = krátký název.'),
                ]),
            ]),
        ]);
    }
}
