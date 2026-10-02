<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Concerns\OnlyForAdmins;
use App\Models\CaseStudy;
use App\Settings\HomeSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Obsah homepage sekci po sekci. Seznamy (reference, služby, kroky procesu,
 * lidé) se needitují tady — mají vlastní položky v menu pod „Obsah".
 */
class ManageHome extends SettingsPage
{
    use OnlyForAdmins;

    protected static string $settings = HomeSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Homepage';

    protected static ?string $title = 'Obsah homepage';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 5;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([

                Tab::make('Úvod')->schema([
                    Section::make('Hlavní nadpis')
                        ->description('Nadpis se odkrývá po řádcích — proto jsou tři pole.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('hero_eyebrow')->label('Popisek nad nadpisem')->columnSpanFull(),
                            TextInput::make('hero_line_1')->label('1. řádek'),
                            TextInput::make('hero_line_2')->label('2. řádek'),
                            TextInput::make('hero_line_3')->label('3. řádek'),
                            TextInput::make('hero_line_3_accent')
                                ->label('3. řádek — zvýrazněná část')
                                ->helperText('Vysází se cihlově kurzívou.'),
                            Textarea::make('hero_perex')->label('Perex')->rows(3)->columnSpanFull(),
                        ]),

                    Section::make('Pruh s čísly')
                        ->description('Hned pod úvodem. Jen čísla, která umíme doložit: když nevíte, pole smažte, pruh se zkrátí.')
                        ->schema([
                            Repeater::make('trust_items')
                                ->hiddenLabel()
                                ->addActionLabel('Přidat číslo')
                                ->schema([
                                    TextInput::make('value')->label('Číslo')->required()->helperText('Např. „8+ let"'),
                                    TextInput::make('label')->label('Popisek')->required()->helperText('Např. „praxe každého z nás"'),
                                    TextInput::make('url')
                                        ->label('Odkaz')
                                        ->helperText('Volitelné. Např. „/#recenze" u hodnocení na Googlu. Prázdné = číslo bez odkazu.')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => trim(($state['value'] ?? '').' '.($state['label'] ?? '')) ?: null)
                                ->maxItems(4),
                        ]),

                    Section::make('Tlačítka')->columns(2)->schema([
                        TextInput::make('hero_cta_primary_label')->label('Hlavní tlačítko — text'),
                        TextInput::make('hero_cta_primary_url')->label('Hlavní tlačítko — odkaz'),
                        TextInput::make('hero_cta_secondary_label')->label('Vedlejší tlačítko — text'),
                        TextInput::make('hero_cta_secondary_url')->label('Vedlejší tlačítko — odkaz'),
                    ]),
                ]),

                Tab::make('Problém')->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('problem_eyebrow')->label('Popisek nad nadpisem'),
                        Textarea::make('problem_title')
                            ->label('Nadpis')
                            ->rows(2)
                            ->helperText('Zalomení řádku napište Enterem.'),
                        Textarea::make('problem_perex')->label('Perex')->rows(4)->columnSpanFull(),
                        Repeater::make('problem_points')
                            ->label('Očíslované body')
                            ->addActionLabel('Přidat bod')
                            ->simple(TextInput::make('text')->required())
                            ->columnSpanFull(),
                    ]),
                ]),

                Tab::make('Dvě situace')->schema([
                    TextInput::make('situations_title')->label('Nadpis sekce'),
                    Repeater::make('situations')
                        ->label('Karty')
                        ->addActionLabel('Přidat kartu')
                        ->schema([
                            TextInput::make('eyebrow')->label('Popisek nahoře')->required(),
                            Select::make('variant')
                                ->label('Barva karty')
                                ->options(['dark' => 'Černá', 'brick' => 'Cihlová'])
                                ->default('dark')
                                ->required(),
                            TextInput::make('title')->label('Nadpis')->required()->columnSpanFull(),
                            Textarea::make('text')->label('Text')->rows(3)->columnSpanFull(),
                            TextInput::make('cta_label')->label('Text odkazu'),
                            TextInput::make('cta_url')->label('Cíl odkazu'),
                        ])
                        ->columns(2)
                        ->itemLabel(fn (array $state): ?string => $state['eyebrow'] ?? null)
                        ->maxItems(2),
                ]),

                Tab::make('Služby a reference')->schema([
                    Section::make('Nejnovější projekt')
                        ->description('Ukázka projektu za sekcí „Dvě situace“: text vlevo, obrázek vpravo. Rozbije dlouhý úsek textu.')
                        ->schema([
                            Select::make('latest_case_id')
                                ->label('Reference')
                                ->options(fn (): array => CaseStudy::published()->ordered()->pluck('title', 'id')->all())
                                ->searchable()
                                ->placeholder('Nezobrazovat')
                                ->helperText('Když má reference blok „Před a po", ukáže se posuvník se starým a novým webem. Jinak první obrázek z galerie. Prázdné = sekce se nezobrazí.'),
                        ]),

                    Section::make('Sekce „Co umíme"')
                        ->description('Samotné služby se editují v menu Obsah → Služby.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('services_title')->label('Nadpis'),
                            Textarea::make('services_perex')->label('Text vpravo')->rows(2),
                        ]),

                    Section::make('Sekce „Vybrané projekty"')
                        ->description('Které reference se zobrazí, se řídí přepínačem „Vypíchnout na homepage" u konkrétní reference.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('cases_title')->label('Nadpis'),
                            TextInput::make('cases_link_label')->label('Text odkazu na výpis'),
                        ]),
                ]),

                Tab::make('Proč my')->schema([
                    Section::make()->schema([
                        TextInput::make('loop_title')->label('Nadpis'),
                        Textarea::make('loop_perex')->label('Perex')->rows(2),
                        Repeater::make('loop_items')
                            ->label('Čtyři sloupce')
                            ->addActionLabel('Přidat sloupec')
                            ->schema([
                                TextInput::make('label')->label('Popisek')->required()->helperText('Např. „Přivede"'),
                                TextInput::make('title')->label('Nadpis')->required(),
                                Textarea::make('text')->label('Text')->rows(3)->required(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                    ]),
                ]),

                Tab::make('Lidé a proces')->schema([
                    Section::make('Sekce „Lidé"')
                        ->description('Jednotlivé osoby se editují v menu Obsah → Lidé.')
                        ->schema([
                            TextInput::make('founders_title')->label('Nadpis'),
                            Textarea::make('founders_perex')->label('Text vpravo od nadpisu')->rows(2),
                            Textarea::make('founders_intro')->label('Úvodní odstavec')->rows(3),
                        ]),

                    Section::make('Blok „Když je potřeba někdo další"')
                        ->description('Specialisté na volné noze kolem nás. Nechte nadpis i text prázdný a blok se nezobrazí.')
                        ->schema([
                            TextInput::make('founders_network_title')->label('Nadpis'),
                            Textarea::make('founders_network_text')->label('Text')->rows(6),
                            Repeater::make('founders_network_items')
                                ->label('Obory (štítky)')
                                ->addActionLabel('Přidat obor')
                                ->simple(TextInput::make('text')->required()),
                        ]),

                    Section::make('Sekce „Jak spolu pracujeme"')
                        ->description('Kroky se editují v menu Obsah → Postup spolupráce.')
                        ->schema([
                            TextInput::make('process_title')->label('Nadpis'),
                        ]),
                ]),

                Tab::make('Ceník')->schema([
                    Section::make('Sekce „A kolik to celé stojí?"')
                        ->description('Stojí mezi postupem spolupráce a formulářem. Bez jediné karty se sekce nezobrazí.')
                        ->schema([
                            TextInput::make('pricing_title')->label('Nadpis'),
                            Textarea::make('pricing_perex')->label('Text vpravo od nadpisu')->rows(2),
                            Repeater::make('pricing_plans')
                                ->label('Formy spolupráce')
                                ->addActionLabel('Přidat formu spolupráce')
                                ->schema([
                                    TextInput::make('name')->label('Název')->required(),
                                    TextInput::make('when')->label('Pro koho')->helperText('Cihlový řádek pod názvem, např. „Když potřebujete…"'),
                                    Textarea::make('text')->label('Popis')->rows(4)->columnSpanFull(),
                                    TextInput::make('price')->label('Cena')->required()->helperText('Např. „2 000 Kč" nebo „od 8 900 Kč"'),
                                    TextInput::make('price_unit')->label('Za co')->helperText('Např. „/ hod." nebo „/ měsíc"'),
                                    Textarea::make('price_note')->label('Poznámka pod cenou')->rows(2)->columnSpanFull(),
                                    Toggle::make('highlight')->label('Zvýraznit')->helperText('Karta dostane cihlový rámeček.'),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->maxItems(3),
                        ]),

                    Section::make('Úvodní konzultace zdarma')
                        ->description('Pruh pod kartami ceníku, ať se lidé nebojí ozvat. Bez nadpisu se nezobrazí. Tlačítko vede na formulář na konci stránky.')
                        ->schema([
                            TextInput::make('pricing_free_title')->label('Nadpis'),
                            Textarea::make('pricing_free_text')->label('Text')->rows(2),
                            TextInput::make('pricing_free_cta_label')->label('Popisek tlačítka')->helperText('Prázdné pole tlačítko skryje.'),
                        ]),

                    Section::make('„Kolik hodin vlastně potřebuji?"')
                        ->description('Příklady z praxe pod kartami ceníku: co za daný počet hodin měsíčně stihneme a co se změní za tři měsíce. Bez jediného příkladu se blok nezobrazí.')
                        ->schema([
                            TextInput::make('pricing_examples_title')->label('Nadpis'),
                            Textarea::make('pricing_examples_perex')->label('Perex')->rows(3),
                            Repeater::make('pricing_examples')
                                ->label('Příklady')
                                ->addActionLabel('Přidat příklad')
                                ->schema([
                                    TextInput::make('hours')->label('Rozsah')->required()->helperText('Např. „8 hodin měsíčně"'),
                                    TextInput::make('price')->label('Cena')->helperText('Např. „8 000 Kč / měsíc". Prázdné = bez ceny.'),
                                    Textarea::make('for')->label('Pro koho')->rows(2)->columnSpanFull(),
                                    Repeater::make('items')
                                        ->label('Co za měsíc stihneme')
                                        ->addActionLabel('Přidat bod')
                                        ->simple(TextInput::make('text')->required())
                                        ->columnSpanFull(),
                                    Textarea::make('after')->label('Co se změní za tři měsíce')->rows(3)->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => $state['hours'] ?? null)
                                ->maxItems(3),
                            Textarea::make('pricing_examples_note')
                                ->label('Poznámka pod příklady')
                                ->rows(2)
                                ->helperText('Drobným písmem, např. co se platí zvlášť.'),
                        ]),
                ]),

                Tab::make('Recenze')->schema([
                    Section::make('Sekce „Co o nás říkají klienti"')
                        ->description('Stojí za logy klientů, kotva #recenze. Samotné recenze se editují v menu Obsah → Recenze. Bez jediné zveřejněné recenze se sekce nezobrazí.')
                        ->schema([
                            TextInput::make('reviews_title')->label('Nadpis'),
                            Textarea::make('reviews_perex')->label('Perex')->rows(2),
                            TextInput::make('reviews_google_url')
                                ->label('Odkaz na hodnocení na Googlu')
                                ->url()
                                ->helperText('Odkaz na firemní profil na Googlu. Prázdné = tlačítko „Hodnocení na Googlu" se nezobrazí.'),
                        ]),
                ]),

                Tab::make('Závěrečné CTA')->schema([
                    Section::make()->schema([
                        TextInput::make('cta_eyebrow')->label('Popisek nad nadpisem'),
                        TextInput::make('cta_title')->label('Nadpis'),
                        Textarea::make('cta_perex')->label('Perex')->rows(3),
                    ]),
                ]),
            ]),
        ]);
    }
}
