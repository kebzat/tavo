<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Concerns\OnlyForAdmins;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSeo extends SettingsPage
{
    use OnlyForAdmins;

    protected static string $settings = SeoSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'SEO a měření';

    protected static ?string $title = 'SEO a měření';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 30;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Výchozí texty')
                ->description('Použijí se všude, kde stránka nemá vlastní SEO texty.')
                ->schema([
                    TextInput::make('default_title')->label('Výchozí titulek')->required(),
                    TextInput::make('title_suffix')
                        ->label('Přípona titulku')
                        ->helperText('Připojí se za titulek každé stránky, např. „ | Taveo".'),
                    Textarea::make('default_description')->label('Výchozí popisek')->rows(3),
                    FileUpload::make('og_image')
                        ->label('Obrázek pro sdílení')
                        ->image()
                        ->directory('seo')
                        ->helperText('Doporučeno 1200 × 630 px.'),
                ]),

            Section::make('Měření')->schema([
                TextInput::make('gtm_id')
                    ->label('Google Tag Manager ID')
                    ->placeholder('GTM-XXXXXXX')
                    ->helperText('Načte se až po souhlasu s cookies. Prázdné = žádné měření a cookie lišta nabídne jen nezbytné cookies.'),

                Toggle::make('indexable')
                    ->label('Povolit indexaci vyhledávači')
                    ->helperText('Vypněte na testovacím serveru — přidá se noindex.')
                    ->default(true),
            ]),
        ]);
    }
}
