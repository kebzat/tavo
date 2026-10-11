<?php

namespace App\Filament\Tools\Pages;

use App\Models\StaffIp;
use App\Settings\CrmSettings;
use App\Support\OurIps;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Nastavení CRM: nabídka odkladů u follow-upů, příjemci ranního souhrnu
 * a naše IP adresy, které se nepočítají do otevření dokumentů.
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

            Section::make('Otevření sdílených dokumentů')
                ->description('Audity, nabídky spolupráce a reporty počítají jen otevření klientem. Naše otevření se poznají podle IP adresy.')
                ->schema([
                    TagsInput::make('ignored_ips')
                        ->label('Naše IP adresy')
                        ->placeholder('89.24.10.5')
                        ->nestedRecursiveRules(['ip'])
                        ->helperText('Platí napořád. Hodí se pro kancelář nebo pro kolegu bez účtu. U IPv6 stačí jedna adresa, počítá se celá síť /64.'),

                    TextEntry::make('staff_ips')
                        ->label('Adresy, ze kterých jsme byli přihlášení')
                        ->helperText('Doplňují se samy. Otevření z nich se nepočítá, pokud bylo do měsíce od doby, kdy jsme na adrese byli přihlášení.')
                        ->listWithLineBreaks()
                        ->placeholder('Zatím žádné.')
                        ->state(fn (): array => StaffIp::query()
                            ->with('user')
                            ->latest('last_seen_at')
                            ->limit(30)
                            ->get()
                            ->map(fn (StaffIp $staff): string => $staff->ip
                                .($staff->user ? ' · '.$staff->user->name : '')
                                .' · naposledy '.$staff->last_seen_at->format('j. n. Y H:i'))
                            ->all()),
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

    /** Změna seznamu adres se hned propíše do počtů otevření. */
    protected function afterSave(): void
    {
        app(OurIps::class)->recountAll();
    }
}
