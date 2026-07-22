<?php

namespace App\Filament\Pages\Settings;

use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSite extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Web';

    protected static ?string $title = 'Nastavení webu';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 10;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Značka')->columns(2)->schema([
                TextInput::make('brand_name')->label('Název')->required(),
                TextInput::make('copyright')->label('Copyright v patičce')->required(),
                Textarea::make('brand_claim')
                    ->label('Popis v patičce')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('footer_note')->label('Poznámka vpravo dole')->columnSpanFull(),
            ]),

            Section::make('Navigace')->columns(2)->schema([
                Repeater::make('nav_links')
                    ->label('Odkazy v menu')
                    ->addActionLabel('Přidat odkaz')
                    ->schema([
                        TextInput::make('label')->label('Text')->required(),
                        TextInput::make('url')->label('Odkaz')->required()->helperText('Např. /reference nebo /#sluzby'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                TextInput::make('nav_cta_label')->label('Text tlačítka')->required(),
                TextInput::make('nav_cta_url')->label('Odkaz tlačítka')->required(),
            ]),

            Section::make('Patička')->schema([
                Repeater::make('footer_columns')
                    ->label('Sloupce')
                    ->addActionLabel('Přidat sloupec')
                    ->schema([
                        TextInput::make('title')->label('Nadpis sloupce')->required(),
                        Repeater::make('links')
                            ->label('Odkazy')
                            ->addActionLabel('Přidat odkaz')
                            ->schema([
                                TextInput::make('label')->label('Text')->required(),
                                TextInput::make('url')->label('Odkaz')->required(),
                            ])
                            ->columns(2),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->collapsed(),
            ]),
        ]);
    }
}
