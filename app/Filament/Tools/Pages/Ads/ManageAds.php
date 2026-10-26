<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdSyncRun;
use App\Settings\AdsSettings;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\MetricCatalog;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\Platforms\Ga4;
use App\Support\Ads\Platforms\Platforms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Nastavení reklam: stav napojení na Metu, stav dat po účtech (do kdy
 * kompletní, chyba), příjemci ranního souhrnu, prahy upozornění, šablony
 * čísel podle typu kampaní a poslední synchronizace.
 */
class ManageAds extends SettingsPage
{
    protected static string $settings = AdsSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Nastavení reklam';

    protected static ?string $title = 'Nastavení reklam';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'reklamy/nastaveni';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Napojení')
                ->description('Přístupy jsou v .env na serveru, sem se nezadávají. Postup je v docs/ADS.md. Spojení ověřuje ranní stažení a Načíst účty znovu, výsledek je u každé platformy.')
                ->afterHeader([$this->reloadAccountsAction()])
                ->columns(2)
                ->schema(collect(AdPlatform::cases())->map(fn (AdPlatform $platform): TextEntry => TextEntry::make('platform_'.$platform->value)
                    ->label($platform->getLabel())
                    ->state(fn (): string => $this->platformState($platform))
                    ->color(fn (): string => app(Platforms::class)->for($platform)->isConfigured() ? 'success' : ($platform->isDemo() ? 'gray' : 'danger')))
                    ->all()),

            Section::make('Stav dat po účtech')
                ->description('Do kterého dne máme čísla kompletní. Den po něm není nula, ale „nemáme data“: odhady a srovnání se z něj nepočítají.')
                ->schema([
                    TextEntry::make('data_state')
                        ->hiddenLabel()
                        ->state(fn (): HtmlString => $this->dataState()),
                ]),

            Section::make('Ochrana před přetížením')
                ->description('Na Metu, Google Ads a GA4 sahá jen stažení ráno v 6:00 a ruční žádosti (Načíst účty znovu, Obnovit čísla, nově propojený účet). Všechno ostatní (přehledy, reporty, upozornění, agent) čte z databáze. U Mety je to jeden dotaz na seznam účtů, šest na účet (stav, čísla, dosah, zamítnuté reklamy, stav kampaní, čísla reklam) a jeden na skupinu kampaní (její dosah). Ruční obnova z detailu klienta stáhne jeden účet, nejvýš jednou za 2 hodiny. Načíst účty znovu je jeden dotaz na platformu, nejvýš jednou za 10 minut. Nově propojený účet se stáhne hned, i s historií. Když platforma hlásí vytížení nebo omezí dotazy, stahování se samo pozastaví do zítřejšího rána.')
                ->columns(3)
                ->schema(collect(['meta' => 'Meta', 'google_ads' => 'Google Ads', 'ga4' => 'GA4'])->map(fn (string $label, string $key): TextEntry => TextEntry::make('guard_'.$key)
                    ->label($label)
                    ->state(function () use ($key): string {
                        $guard = ApiGuard::for($key);
                        $until = $guard->pausedUntil();

                        return $until
                            ? 'Pozastaveno do '.$until->format('j. n. H:i')
                            : 'Dnes '.$guard->callsToday().' dotazů z bezpečnostního stropu '.$guard->dailyLimit();
                    })
                    ->color(fn (): string => ApiGuard::for($key)->pausedUntil() ? 'danger' : 'gray'))
                    ->values()
                    ->all()),

            Section::make('Ranní souhrn')
                ->schema([
                    TagsInput::make('digest_recipients')
                        ->label('Komu chodí e-mail')
                        ->placeholder('pavel@taveo.cz')
                        ->helperText('Prázdné = všem účtům. Chodí ve všední dny v 7:15, jen když je co hlásit.'),
                ]),

            Section::make('Kdy upozornit')
                ->description('Platí pro všechny klienty. Cílovou cenu za konverzi, ROAS a rozpočet má každý klient vlastní.')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('budget_under_pct')->label('Pomalé čerpání pod')->numeric()->required()->suffix('% plánu'),
                        TextInput::make('budget_over_pct')->label('Přečerpání nad')->numeric()->required()->suffix('% plánu'),
                        TextInput::make('spend_spike_pct')->label('Skok útraty den na den')->numeric()->required()->suffix('%'),
                        TextInput::make('cpa_over_pct')->label('Cena za konverzi nad cílem o')->numeric()->required()->suffix('%'),
                        TextInput::make('roas_under_pct')->label('ROAS pod cílem o')->numeric()->required()->suffix('%'),
                        TextInput::make('min_conversions')->label('Cenu a ROAS hodnotit od')->numeric()->required()->suffix('konverzí')
                            ->helperText('Za týden. U menšího počtu dělá jedna objednávka desítky procent.'),
                        TextInput::make('ctr_drop_pct')->label('Pokles CTR proti minulému týdnu')->numeric()->required()->suffix('%'),
                        TextInput::make('frequency_max')->label('Frekvence za týden od')->numeric()->step(0.1)->required(),
                        TextInput::make('no_conversion_days')->label('Dny útraty bez konverze')->numeric()->minValue(1)->required()->suffix('dní'),
                        TextInput::make('min_spend')->label('Výkon hodnotit od útraty')->numeric()->minValue(0)->required()->suffix('Kč za 7 dní')
                            ->helperText('Pod tím cena, ROAS, CTR ani skok útraty nesvítí, jsou to náhodné výkyvy.'),
                    ]),
                ]),

            Section::make('Šablony čísel')
                ->description('Která čísla se ukážou podle typu kampaní. Karta v přehledu má šest čísel, detail a report víc. Klient i skupina si můžou vybrat vlastní.')
                ->collapsed()
                ->schema(collect(PrimaryGoal::cases())->map(fn (PrimaryGoal $goal): Section => Section::make($goal->templateLabel())
                    ->description($goal->getLabel())
                    ->collapsed()
                    ->schema([
                        Grid::make(6)->schema(collect(range(0, MetricCatalog::OVERVIEW_SLOTS - 1))->map(fn (int $slot): Select => Select::make("card_templates.{$goal->value}.{$slot}")
                            ->label('Karta '.($slot + 1))
                            ->options(MetricCatalog::options($goal))
                            ->required())->all()),
                        CheckboxList::make("tile_templates.{$goal->value}")
                            ->label('Detail a report')
                            ->options(MetricCatalog::options($goal))
                            ->columns(3),
                    ]))->all()),

            Section::make('Poslední synchronizace')
                ->collapsible()
                ->schema([
                    TextEntry::make('runs')
                        ->hiddenLabel()
                        ->state(fn (): HtmlString => $this->syncLog()),
                ]),
        ]);
    }

    /**
     * Nově nasdílený účet hned, ne až druhý den ráno. Odsud se na platformy
     * nesahá: zapíše žádost, kterou do 5 minut vyřídí `ads:sync --requested`
     * (jeden dotaz na platformu), nejvýš jednou za 10 minut.
     */
    public function reloadAccountsAction(): Action
    {
        return Action::make('reloadAccounts')
            ->label('Načíst účty znovu')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => collect(app(Platforms::class)->configured())->contains(fn (AdPlatform $platform): bool => ! $platform->isDemo()))
            ->requiresConfirmation()
            ->modalHeading('Načíst účty znovu?')
            ->modalDescription('Stáhne aktuální seznam účtů, které klienti nasdíleli Taveo (Meta, Google Ads pod MCC, GA4). Trvá to do 5 minut, pak je nový účet v nabídce Přidat klienta a Propojit účet. Čísla se tím nestahují.')
            ->modalSubmitActionLabel('Načíst')
            ->action(function (): void {
                $directory = app(AccountDirectory::class);

                if ($directory->requestRefresh()) {
                    Notification::make()->success()->title('Účty se načtou do 5 minut')->body('Pak stránku obnovte, výsledek bude u každé platformy.')->send();

                    return;
                }

                Notification::make()->warning()->title('Teď to nejde')->body($directory->refreshPending()
                    ? 'Načtení už čeká, proběhne do 5 minut.'
                    : 'Účty se načítaly před chvílí. Znovu to půjde v '.$directory->nextRequestAt()?->timezone('Europe/Prague')->format('G:i').'.')->send();
            });
    }

    /** Účty s posledním úspěšným stažením, daty do a chybou. */
    private function dataState(): HtmlString
    {
        $accounts = AdAccount::query()->active()->with('client')->orderBy('client_id')->get()
            ->reject(fn (AdAccount $account): bool => $account->isAnalytics());

        if ($accounts->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">Zatím žádný propojený účet.</p>');
        }

        $yesterday = today()->subDay();
        $rows = $accounts->map(function (AdAccount $account) use ($yesterday): string {
            $state = match (true) {
                filled($account->last_sync_error) => ['text-danger-600', 'Chyba: '.$account->last_sync_error],
                $account->data_through === null => ['text-warning-600', 'Čeká na první stažení'],
                $account->data_through->lt($yesterday) => ['text-warning-600', 'Data jen do '.$account->data_through->format('j. n. Y')],
                default => ['text-success-600', 'Data do '.$account->data_through->format('j. n. Y')],
            };

            return '<li class="flex flex-wrap justify-between gap-2 py-1.5">'
                .'<span>'.e($account->client?->name.' · '.$account->name).($account->platform->isDemo() ? ' <span class="text-gray-400">(ukázka)</span>' : '').'</span>'
                .'<span class="'.$state[0].'">'.e($state[1]).'<span class="text-gray-400">'.e($account->last_synced_at ? ' · staženo '.$account->last_synced_at->timezone('Europe/Prague')->format('j. n. G:i') : '').'</span></span>'
                .'</li>';
        })->implode('');

        return new HtmlString('<ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">'.$rows.'</ul>');
    }

    private function platformState(AdPlatform $platform): string
    {
        $configured = app(Platforms::class)->for($platform)->isConfigured();

        return match ($platform) {
            AdPlatform::Meta, AdPlatform::GoogleAds, AdPlatform::Ga4 => $this->accessState($platform, $configured),
            AdPlatform::Demo, AdPlatform::DemoGa4 => $configured
                ? 'Zapnuté ('.AdAccount::query()->where('platform', $platform)->distinct()->count('client_id').' ukázkových klientů, karty mají štítek „Ukázková data“). Ostrá data na nich nezávisí. Vypnout: ADS_DEMO=false a php artisan ads:demo --remove'
                : 'Vypnuté.',
        };
    }

    /** Přístup a výsledek ranního stažení seznamu účtů. */
    private function accessState(AdPlatform $platform, bool $configured): string
    {
        $access = match ($platform) {
            AdPlatform::Meta => $configured ? 'Token je nastavený.' : 'Chybí META_SYSTEM_USER_TOKEN.',
            AdPlatform::GoogleAds => $configured ? 'Přístup je nastavený (MCC '.config('ads.google_ads.login_customer_id').').' : 'Chybí GOOGLE_ADS_CLIENT_ID, _SECRET, _REFRESH_TOKEN nebo _LOGIN_CUSTOMER_ID.',
            default => $configured ? 'Servisní účet '.app(Ga4::class)->serviceAccountEmail().'. Ten klient přidá do GA4 jako čtenáře.' : 'Chybí GA4_CREDENTIALS.',
        };

        if (! $configured) {
            return $access;
        }

        $directory = app(AccountDirectory::class);
        $checked = $directory->checkedAt($platform)?->timezone('Europe/Prague');

        return $access.' '.match (true) {
            $directory->refreshPending() => 'Seznam účtů se načítá, do 5 minut.',
            $checked === null => 'Spojení ověří ranní stažení, nebo hned Načíst účty znovu.',
            $directory->lastError($platform) !== null => 'Načtení seznamu účtů '.$checked->format('j. n. G:i').' selhalo: '.$directory->lastError($platform),
            default => 'Seznam účtů z '.$checked->format('j. n. G:i').', nasdílených účtů: '.$directory->count($platform).'.',
        };
    }

    /** Posledních 15 běhů jako jednoduchý seznam. */
    private function syncLog(): HtmlString
    {
        $runs = AdSyncRun::query()->with('account.client')->latest('started_at')->limit(15)->get();

        if ($runs->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">Zatím nic. Synchronizace běží každé ráno v 6:00.</p>');
        }

        $rows = $runs->map(fn (AdSyncRun $run): string => '<li class="flex flex-wrap justify-between gap-2 py-1.5">'
            .'<span>'.e($run->account?->client?->name.' · '.$run->account?->name).'</span>'
            .'<span class="'.match ($run->status) {
                'ok' => 'text-success-600', 'skipped' => 'text-gray-500', default => 'text-danger-600'
            }.'">'
            .e($run->started_at->format('j. n. H:i').' · '.($run->status === 'ok' ? $run->rows.' řádků' : ($run->error ?? $run->status)))
            .'</span></li>')->implode('');

        return new HtmlString('<ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">'.$rows.'</ul>');
    }
}
