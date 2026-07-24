<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Concerns\OnlyForAdmins;
use App\Settings\HomeSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

                    Section::make('Sekce „Jak spolu pracujeme"')
                        ->description('Kroky se editují v menu Obsah → Postup spolupráce.')
                        ->schema([
                            TextInput::make('process_title')->label('Nadpis'),
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
