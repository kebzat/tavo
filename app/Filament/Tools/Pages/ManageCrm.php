<?php

namespace App\Filament\Tools\Pages;

use App\Settings\CrmSettings;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Nastavení CRM: nabídka odkladů u follow-upů a příjemci ranního souhrnu.
 */
class ManageCrm extends SettingsPage
{
    protected static string $settings = CrmSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Nastavení CRM';

    protected static ?string $title = 'Nastavení CRM';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 90;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Follow-upy')
                ->schema([
                    TagsInput::make('follow_up_days')
                        ->label('Nabídka odkladů ve dnech')
                        ->placeholder('3')
                        ->helperText('Objeví se jako tlačítka u zápisu aktivity. Pořadí se zachová.')
                        ->required(),
                ]),

            Section::make('Ranní souhrn')
                ->schema([
                    TagsInput::make('digest_recipients')
                        ->label('Komu chodí e-mail')
                        ->placeholder('tom@taveo.cz')
                        ->helperText('Prázdné = všem účtům. Souhrn odchází ve všední dny v 7:00.'),
                ]),

            Section::make('Strojové rozhraní')
                ->description('Token pro import poptávek a export pipeline se nastavuje v .env jako CRM_IMPORT_TOKEN.')
                ->schema([
                    TextEntry::make('import_token')
                        ->label('Stav tokenu')
                        ->state(fn (): string => filled(config('crm.import_token'))
                            ? 'Nastavený — strojové endpointy fungují.'
                            : 'Chybí — /nastroje/api/… vrací 404.')
                        ->color(fn (): string => filled(config('crm.import_token')) ? 'success' : 'danger'),
                ]),
        ]);
    }
}
