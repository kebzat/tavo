<?php

namespace App\Filament\Tools\Pages;

use App\Filament\Schemas\ImageUpload;
use App\Settings\ProposalSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Společný obsah všech stránek „Potenciální spolupráce": fotka s nadpisem
 * před závěrečnou výzvou, stejná u každého konceptu.
 */
class ManageProposals extends SettingsPage
{
    protected static string $settings = ProposalSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Koncepty: společná fotka';

    protected static ?string $title = 'Potenciální spolupráce: společná fotka';

    protected static string|\UnitEnum|null $navigationGroup = 'Checklisty';

    protected static ?int $navigationSort = 26;

    protected static ?string $slug = 'spoluprace/nastaveni';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fotka před závěrečnou výzvou')
                ->description('Ukáže se na všech stránkách „Potenciální spolupráce" nad tlačítkem „Probereme to spolu?". Bez fotky se sekce nezobrazí.')
                ->columns(2)
                ->schema([
                    ImageUpload::file('photo')
                        ->label('Fotka')
                        ->directory('spoluprace')
                        ->imageEditor()
                        ->helperText('Na šířku, ideálně 1600 × 1200 px. Na počítači stojí vlevo od textu, na mobilu nad ním.')
                        ->columnSpanFull(),
                    TextInput::make('photo_alt')
                        ->label('Popisek fotky (alt)')
                        ->helperText('Co na fotce je, pro hlasové čtečky.')
                        ->columnSpanFull(),
                    TextInput::make('photo_title')
                        ->label('Nadpis')
                        ->placeholder('Komplexní marketing = cesta k úspěchu')
                        ->columnSpanFull(),
                    Textarea::make('photo_text')
                        ->label('Text pod nadpisem')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
