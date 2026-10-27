<?php

namespace App\Filament\Tools\Resources\Automations;

use App\Enums\AutomationType;
use App\Enums\VaultType;
use App\Filament\Client\Pages\Automations as ClientAutomations;
use App\Filament\Tools\Resources\Automations\Pages\CreateAutomation;
use App\Filament\Tools\Resources\Automations\Pages\EditAutomation;
use App\Filament\Tools\Resources\Automations\Pages\ListAutomations;
use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Models\Client;
use App\Models\VaultEntry;
use App\Support\Vault\ZaslatApi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Automatizace klientů (zatím připomínky vyzvednutí zásilek v Upgates).
 * Tady správce nastaví technickou část: přístup k Zaslatu z trezoru,
 * export objednávek, názvy stavů v Upgates a zkopíruje adresu CSV do
 * importu v Upgates. Klient vidí přehled a přepínače v /muj-ucet.
 * Viz docs/AUTOMATIZACE.md.
 */
class AutomationResource extends Resource
{
    protected static ?string $model = Automation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Automatizace';

    protected static ?string $modelLabel = 'automatizace';

    protected static ?string $pluralModelLabel = 'Automatizace';

    protected static string|\UnitEnum|null $navigationGroup = 'Klienti';

    protected static ?int $navigationSort = 75;

    protected static ?string $slug = 'automatizace';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationBadge(): ?string
    {
        $broken = Automation::query()->where('enabled', true)->whereNotNull('last_error')->count()
            + AutomationEvent::query()->where('state', AutomationEvent::STATE_FAILED)->where('updated_at', '>', now()->subDay())->count();

        return $broken > 0 ? (string) $broken : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Chyby automatizací za poslední den';
    }

    public static function form(Schema $schema): Schema
    {
        $defaults = Automation::DEFAULT_SETTINGS;

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('type')
                        ->label('Druh')
                        ->options(AutomationType::class)
                        ->default(AutomationType::UpgatesParcelPickup)
                        ->selectablePlaceholder(false)
                        ->required(),
                    Toggle::make('enabled')
                        ->label('Zapnuto')
                        ->helperText('Vypnutá nic nezakládá a CSV pro Upgates je prázdné (jen hlavička). Zapnout může i klient ve svém účtu.')
                        ->columnSpanFull(),
                ]),

            Section::make('Odkud bere data')
                ->columnSpanFull()
                ->schema([
                    Select::make('settings.vault_entry_id')
                        ->label('Klíč k Zaslatu z trezoru')
                        ->options(fn (Get $get): array => VaultEntry::query()
                            ->where('client_id', $get('client_id') ?: 0)
                            ->where('type', VaultType::ApiKey)
                            ->get()
                            ->filter(fn (VaultEntry $entry): bool => ZaslatApi::supports($entry))
                            ->mapWithKeys(fn (VaultEntry $entry): array => [$entry->getKey() => $entry->name.' (#'.$entry->getKey().')'])
                            ->all())
                        ->required()
                        ->helperText('Jen API klíče se Zaslatem u tohoto klienta (Trezor, druh API klíč, adresa zaslat.cz). Klíč nikdo neuvidí, web jím jen čte aktivní zásilky.'),
                    Select::make('settings.upgates_vault_entry_id')
                        ->label('Přihlášení do Upgates z trezoru')
                        ->options(fn (Get $get): array => VaultEntry::query()
                            ->where('client_id', $get('client_id') ?: 0)
                            ->where('type', VaultType::Upgates)
                            ->get()
                            ->mapWithKeys(fn (VaultEntry $entry): array => [$entry->getKey() => $entry->name.' (#'.$entry->getKey().')'])
                            ->all())
                        ->required()
                        ->helperText('Trezor, druh Upgates, adresa administrace e-shopu a adresa přihlášení (…/login/). Web se tím přihlásí a nahraje změny stavů do importu objednávek. Ideálně vlastní účet jen pro automatizace.'),
                    TextInput::make('settings.upgates_import_id')
                        ->label('Číslo importu objednávek v Upgates')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->prefix('#')
                        ->helperText('Objednávky → Import: existující import Upgates CSV (oddělovač středník, jen aktualizace, Zaplaceno/Sledovací číslo/Vyřešeno nezpracovávat). Web do něj nahraje soubor jako „Zopakovat s novým souborem“.'),
                    Textarea::make('order_export_url')
                        ->label('Adresy XML exportu objednávek z Upgates')
                        ->rows(3)
                        ->required()
                        ->rule(function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                foreach (preg_split('/[\s,]+/', (string) $value) ?: [] as $url) {
                                    if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                                        $fail('„'.$url.'“ není adresa.');
                                    }
                                }
                            };
                        })
                        ->helperText('Každou na nový řádek. První = kompletní export (stahuje se nejvýš 1× za 6 h), další = změny za 24 h (při každém běhu). Stačí i jedna adresa. Uloží se zašifrovaně, ze zásilek se čte jen číslo objednávky a stav.'),
                ]),

            Section::make('Co posílat')
                ->columnSpanFull()
                ->columns(2)
                ->description('Tohle může měnit i klient ve svém účtu.')
                ->schema([
                    Toggle::make('settings.send_reminder')->label('Připomínka vyzvednutí e-mailem')->default($defaults['send_reminder']),
                    TextInput::make('settings.remind_days_before')
                        ->label('Kolik dní před koncem úložní doby')
                        ->numeric()->integer()->minValue(1)->maxValue(6)
                        ->default($defaults['remind_days_before'])
                        ->required(),
                    Toggle::make('settings.send_last_day')->label('SMS v poslední den')->default($defaults['send_last_day']),
                    Toggle::make('settings.send_returned')->label('E-mail k vrácené zásilce')->default($defaults['send_returned']),
                ]),

            Section::make('Stavy objednávek v Upgates')
                ->columnSpanFull()
                ->columns(2)
                ->description('Názvy přesně jako v Upgates (Nastavení → Objednávky → Stavy). K cílovým stavům je v Upgates přiřazený e-mail nebo SMS.')
                ->collapsed(fn (?Automation $record): bool => $record !== null)
                ->schema([
                    TextInput::make('settings.status_sent')->label('Odeslaná objednávka')->default($defaults['status_sent'])->required()
                        ->helperText('Připomínka jde jen z tohoto stavu.'),
                    TextInput::make('settings.status_reminder')->label('Připomínka (cílový stav)')->default($defaults['status_reminder'])->required(),
                    TextInput::make('settings.status_last_day')->label('Poslední den (cílový stav)')->default($defaults['status_last_day'])->required(),
                    TextInput::make('settings.status_returned')->label('Vrácená zásilka (cílový stav)')->default($defaults['status_returned'])->required(),
                    TagsInput::make('settings.source_statuses')
                        ->label('Ze kterých stavů smí automatizace objednávku přepnout')
                        ->default($defaults['source_statuses'])
                        ->required()
                        ->helperText('Objednávky v jiném stavu (Vyřešeno, Stornováno…) nechá být.')
                        ->columnSpanFull(),
                    TextInput::make('settings.carrier_storage_days.ppl')->label('Úložní doba PPL (dny)')->numeric()->integer()->minValue(1)->default(7),
                    TextInput::make('settings.carrier_storage_days.dpd')->label('Úložní doba DPD (dny)')->numeric()->integer()->minValue(1)->default(7),
                    TextInput::make('settings.carrier_storage_days.zasilkovna')->label('Úložní doba Zásilkovny (dny)')->numeric()->integer()->minValue(1)->default(7)
                        ->helperText('Jen když sledování Zásilkovny datum neřekne.'),
                    TextInput::make('settings.carrier_storage_days.default')->label('Úložní doba ostatních (dny)')->numeric()->integer()->minValue(1)->default(7),
                ]),

            Section::make('Čekající změny stavů (CSV)')
                ->columnSpanFull()
                ->visible(fn (?Automation $record): bool => $record !== null)
                ->description('Tenhle soubor web každou hodinu sám nahraje do importu v Upgates (pravidelně z adresy Upgates objednávky importovat neumí). Adresa je pro kontrolu, nebo pro ruční import, kdyby nahrávání selhávalo. Kdo ji zná, vidí jen čísla objednávek a stavy.')
                ->schema([
                    TextEntry::make('csv_url')
                        ->hiddenLabel()
                        ->state(fn (Automation $record): string => $record->csvUrl())
                        ->fontFamily('mono')
                        ->copyable()
                        ->copyMessage('Zkopírováno')
                        ->extraAttributes(['class' => 'break-all']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->modifyQueryUsing(fn ($query) => $query->with('client')->withCount(['events as pending_count' => fn ($query) => $query->where('state', AutomationEvent::STATE_PENDING)]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->weight('bold')
                    ->description(fn (Automation $record): string => $record->type->getLabel())
                    ->searchable(),
                IconColumn::make('enabled')->label('Zapnuto')->boolean(),
                TextColumn::make('last_run_at')
                    ->label('Naposledy')
                    ->since()
                    ->placeholder('zatím ne')
                    ->description(fn (Automation $record): ?string => $record->last_error ? 'Chyba: '.str($record->last_error)->limit(80) : null)
                    ->color(fn (Automation $record): string => $record->last_error ? 'danger' : 'gray'),
                TextColumn::make('pending_count')->label('Čeká na Upgates')->numeric(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Jak to vidí klient')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (Automation $record): string => ClientAutomations::getUrl(['klient' => $record->client_id], panel: 'client'))
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton()->tooltip('Upravit'),
            ])
            ->emptyStateHeading('Zatím žádná automatizace')
            ->emptyStateDescription('Přidej první: klient, klíč k Zaslatu z trezoru a export objednávek z Upgates.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomations::route('/'),
            'create' => CreateAutomation::route('/create'),
            'edit' => EditAutomation::route('/{record}/edit'),
        ];
    }
}
