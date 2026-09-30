<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Concerns\OnlyForAdmins;
use App\Filament\Schemas\ImageUpload;
use App\Settings\SeoSettings;
use BackedEnum;
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
                    ImageUpload::file('og_image')
                        ->label('Obrázek pro sdílení')
                        ->directory('seo')
                        ->helperText('Doporučeno 1200 × 630 px.'),
                ]),

            Section::make('Měření')
                ->description('Každý kód se načte až poté, co návštěvník v cookie liště souhlasí s jeho kategorií. Když není vyplněné nic, cookie lišta se nezobrazí.')
                ->schema([
                    TextInput::make('ga4_id')
                        ->label('Google Analytics 4')
                        ->placeholder('G-XXXXXXXXXX')
                        ->regex('/^G-[A-Z0-9]+$/')
                        ->helperText('Analytické cookies. Odeslaná poptávka se měří jako událost generate_lead, v GA4 ji označte jako klíčovou událost.'),

                    TextInput::make('clarity_id')
                        ->label('Microsoft Clarity')
                        ->placeholder('abcd1234ef')
                        ->regex('/^[a-z0-9]+$/')
                        ->helperText('Analytické cookies. ID projektu z adresy clarity.microsoft.com/projects/view/…'),

                    TextInput::make('meta_pixel_id')
                        ->label('Meta Pixel')
                        ->placeholder('1234567890123456')
                        ->regex('/^\d+$/')
                        ->helperText('Marketingové cookies. Po odeslání poptávky pošle událost Lead.'),

                    TextInput::make('gtm_id')
                        ->label('Google Tag Manager')
                        ->placeholder('GTM-XXXXXXX')
                        ->regex('/^GTM-[A-Z0-9]+$/')
                        ->helperText('Nepovinné. Když v GTM nastavíte i GA4, pole Google Analytics 4 nechte prázdné, jinak se návštěvy počítají dvakrát. Poptávka přijde do GTM jako vlastní událost generate_lead.'),

                    Toggle::make('indexable')
                        ->label('Povolit indexaci vyhledávači')
                        ->helperText('Vypněte na testovacím serveru — přidá se noindex.')
                        ->default(true),
                ]),
        ]);
    }
}
