<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Enums\Ads\AlertStatus;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\Ads\ReportType;
use App\Filament\Tools\Actions\Ads\ConnectAdAccountAction;
use App\Filament\Tools\Actions\Ads\LogTimeAction;
use App\Filament\Tools\Pages\Ads\Concerns\HasAdsPeriod;
use App\Filament\Tools\Resources\AdReports\AdReportResource;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Filament\Tools\Resources\TimeEntries\TimeEntryResource;
use App\Jobs\BackfillAdAccount;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdReport;
use App\Models\Client;
use App\Models\TimeEntry;
use App\Support\Ads\AccountSync;
use App\Support\Ads\Ai\AdsAdvisor;
use App\Support\Ads\Billing;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\Format;
use App\Support\Ads\MetricCatalog;
use App\Support\Ads\PerformanceView;
use App\Support\Ads\Period;
use App\Support\Ads\ReportBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Detail klienta v reklamách: hlavní čísla se srovnáním, graf po dnech,
 * cesta k nákupu, kampaně, čerpání rozpočtu, upozornění a reporty.
 */
class AdsClient extends Page
{
    use HasAdsPeriod;

    protected static ?string $slug = 'reklamy/klient';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.tools.pages.ads.client';

    public Client $client;

    #[Url]
    public string $period = '30d';

    public static function getRoutePath(Panel $panel): string
    {
        return '/reklamy/klient/{client}';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'reklamy.klient';
    }

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function getTitle(): string
    {
        return $this->client->name;
    }

    public function getSubheading(): ?string
    {
        $period = $this->currentPeriod();

        return $period->label().', srovnání s '.$period->previous()->label();
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [
            AdsOverview::getUrl() => 'Reklamy klientů',
            $this->client->name,
        ];
    }

    /** Upozornění, když období začíná dřív, než u klienta máme čísla. */
    public function periodNote(): ?string
    {
        return $this->historyNote($this->dataSince(), config('ads.history_backfill') ? 'Starší čísla doplníte tlačítkem Další → Doplnit starší historii.' : 'Starší čísla zatím nestahujeme.');
    }

    /** Nejstarší den, ze kterého máme u klienta čísla. */
    public function dataSince(): ?string
    {
        $since = AdDailyStat::query()->whereIn('ad_account_id', $this->freshClient()->activeAdAccountIds())->min('date');

        return $since ? substr((string) $since, 0, 10) : null;
    }

    public function performance(): PerformanceView
    {
        return new PerformanceView(ClientPerformance::build($this->freshClient(), $this->currentPeriod()));
    }

    /** @return Collection<int, AdAlert> */
    public function alerts(): Collection
    {
        return $this->client->adAlerts()->live()->worstFirst()->get();
    }

    /** @return Collection<int, AdReport> */
    public function reports(): Collection
    {
        return $this->client->adReports()->latest('period_end')->limit(6)->get();
    }

    /**
     * Připojené účty se stavem synchronizace.
     *
     * @return Collection<int, array{name: string, platform: string, status: string, ok: bool, synced: ?string, error: ?string, active: bool, manager_url: ?string}>
     */
    public function accounts(): Collection
    {
        return $this->client->adAccounts()->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (AdAccount $account): array => [
                'name' => $account->name,
                'platform' => $account->platform->shortLabel(),
                'status' => $account->statusLabel(),
                'ok' => in_array($account->status, [null, ...AdAccount::OK_STATUSES], true) && blank($account->last_sync_error),
                'synced' => $account->last_synced_at?->diffForHumans(),
                'error' => $account->last_sync_error,
                'active' => $account->is_active,
                'manager_url' => $account->managerUrl(),
            ]);
    }

    /** @return array{label: string, percent: float, expected: float, pace: ?string, projected: ?string}|null */
    public function budget(): ?array
    {
        $client = $this->freshClient();
        $pace = BudgetPace::for($client->activeAdAccountIds(), $client->adSettings?->monthly_budget);

        if ($pace === null) {
            return null;
        }

        $currency = $client->adCurrency();

        return [
            'label' => Format::money($pace->spent, $currency).' z '.Format::money($pace->budget, $currency),
            'percent' => min(100, round($pace->spentPercent(), 1)),
            'expected' => min(100, round($pace->expected / $pace->budget * 100, 1)),
            'pace' => $pace->pacePercent() !== null ? Format::number($pace->pacePercent()).' % plánu k dnešku' : null,
            'projected' => $pace->projected() !== null ? 'Odhad za měsíc '.Format::money($pace->projected(), $currency) : null,
        ];
    }

    /**
     * Co jsme s klientem domluvili, do boční karty.
     *
     * @return list<array{label: string, value: string}>
     */
    public function agreement(): array
    {
        $settings = $this->freshClient()->adSettings;
        $currency = $this->client->adCurrency();

        if ($settings === null) {
            return [];
        }

        return array_values(array_filter([
            ['label' => 'Hlavní cíl', 'value' => $settings->primary_goal->getLabel()],
            $settings->target_cpa ? ['label' => 'Cílová cena za konverzi', 'value' => Format::unitPrice($settings->target_cpa, $currency)] : null,
            $settings->target_roas ? ['label' => 'Cílový ROAS', 'value' => Format::roas($settings->target_roas)] : null,
            $settings->fee_czk ? ['label' => 'Náš paušál', 'value' => Format::money($settings->fee_czk).' / měsíc'] : null,
            $settings->included_hours ? ['label' => 'Hodin v paušálu', 'value' => Format::count($settings->included_hours)] : null,
            $settings->hourly_rate ? ['label' => 'Práce navíc', 'value' => Format::money($settings->hourly_rate).' / h'] : null,
            ['label' => 'Reporty', 'value' => collect([$settings->weekly_report ? 'týdenní' : null, $settings->monthly_report ? 'měsíční' : null])->filter()->implode(' a ') ?: 'žádné'],
        ]));
    }

    /** Hodiny a fakturace za tento měsíc. */
    public function billing(): Billing
    {
        return Billing::for($this->freshClient(), now());
    }

    /** @return Collection<int, TimeEntry> */
    public function recentTime(): Collection
    {
        return $this->client->timeEntries()->with('user')->latest('worked_on')->latest('id')->limit(5)->get();
    }

    public function timeEntriesUrl(): string
    {
        return TimeEntryResource::getUrl();
    }

    /** Poslední návrh úprav od Clauda převedený do HTML. */
    public function advice(): ?HtmlString
    {
        $settings = $this->freshClient()->adSettings;

        return filled($settings?->advice)
            ? new HtmlString(Str::markdown($settings->advice, ['html_input' => 'strip', 'allow_unsafe_links' => false]))
            : null;
    }

    public function adviceDate(): ?string
    {
        return $this->client->adSettings?->advice_at?->format('j. n. Y H:i');
    }

    public function acknowledgeAlert(int $id): void
    {
        $this->updateAlert($id, AlertStatus::Acknowledged, 'Upozornění se řeší');
    }

    public function resolveAlert(int $id): void
    {
        $this->updateAlert($id, AlertStatus::Resolved, 'Upozornění vyřešeno');
    }

    public function dismissAlert(int $id): void
    {
        $this->updateAlert($id, AlertStatus::Dismissed, 'Upozornění zamítnuto, týden se neozve');
    }

    public function reportUrl(int $id): string
    {
        return AdReportResource::getUrl('edit', ['record' => $id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createReportAction(),
            $this->syncAction(),
            $this->logTimeAction(),
            $this->settingsAction(),
            ActionGroup::make([
                $this->historyAction(),
                ConnectAdAccountAction::make($this->client),
                $this->adviceAction(),
                Action::make('accounts')
                    ->label('Spravovat účty')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => ClientResource::getUrl('edit', ['record' => $this->client])),
            ])->label('Další')->button()->color('gray'),
        ];
    }

    private function createReportAction(): Action
    {
        return Action::make('createReport')
            ->label('Připravit report')
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->modalHeading('Nový report pro klienta')
            ->modalDescription('Čísla se zmrazí k dnešku. Report pak doplníte komentářem a odešlete, sám nikam nejde.')
            ->modalSubmitActionLabel('Připravit koncept')
            ->fillForm(fn (): array => [
                'type' => ReportType::Weekly->value,
                'from' => Period::week(now()->subWeek())->from->toDateString(),
                'to' => Period::week(now()->subWeek())->to->toDateString(),
            ])
            ->schema([
                Select::make('type')
                    ->label('Typ')
                    ->options(ReportType::class)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        // Select s enumem vrací podle okolností instanci i hodnotu.
                        $period = match ($state instanceof ReportType ? $state : ReportType::tryFrom((string) $state)) {
                            ReportType::Monthly => Period::month(now()->subMonthNoOverflow()),
                            ReportType::Weekly => Period::week(now()->subWeek()),
                            default => null,
                        };

                        if ($period) {
                            $set('from', $period->from->toDateString());
                            $set('to', $period->to->toDateString());
                        }
                    }),
                Grid::make(2)->schema([
                    DatePicker::make('from')->label('Od')->native(false)->displayFormat('j. n. Y')->required(),
                    DatePicker::make('to')->label('Do')->native(false)->displayFormat('j. n. Y')->required()->afterOrEqual('from'),
                ]),
            ])
            ->action(function (array $data, ReportBuilder $builder): void {
                $type = $data['type'] instanceof ReportType ? $data['type'] : ReportType::from($data['type']);
                $report = $builder->create($this->freshClient(), $type, Period::between($data['from'], $data['to']));

                $this->redirect(AdReportResource::getUrl('edit', ['record' => $report]));
            });
    }

    private function logTimeAction(): Action
    {
        return Action::make('logTime')
            ->label('Zapsat čas')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading('Zapsat čas u klienta '.$this->client->name)
            ->schema(LogTimeAction::schema($this->client))
            ->action(function (array $data): void {
                $entry = LogTimeAction::create($data, $this->client);

                Notification::make()->success()->title('Zapsáno '.$entry->duration())->send();
            });
    }

    private function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Cíle a paušál')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->modalHeading('Co s klientem hlídáme')
            ->fillForm(function (): array {
                $settings = $this->freshClient()->adSettings;
                $goal = $this->client->adGoal();
                $data = $settings?->only([
                    'primary_goal', 'monthly_budget', 'target_cpa', 'target_roas', 'fee_czk',
                    'included_hours', 'hourly_rate', 'report_recipients', 'weekly_report', 'monthly_report',
                ]) ?? ['primary_goal' => PrimaryGoal::Purchases->value, 'weekly_report' => true, 'monthly_report' => true];

                // Bez uloženého výběru výchozí dlaždice podle cíle a všechny sekce.
                $data['dashboard'] = [
                    'tiles' => MetricCatalog::tiles($settings?->dashboard['tiles'] ?? null, $goal),
                    'sections' => $settings?->dashboard['sections'] ?? array_keys(PerformanceView::SECTIONS),
                ];

                return $data;
            })
            ->schema([
                Section::make('Cíle')->schema([
                    Select::make('primary_goal')->label('Hlavní cíl')->options(PrimaryGoal::class)->required(),
                    Grid::make(3)->schema([
                        TextInput::make('monthly_budget')->label('Rozpočet na měsíc')->numeric()->minValue(0)->suffix($this->client->adCurrency()),
                        TextInput::make('target_cpa')->label('Cílová cena za konverzi')->numeric()->minValue(0)->suffix($this->client->adCurrency()),
                        TextInput::make('target_roas')->label('Cílový ROAS')->numeric()->minValue(0)->step(0.1),
                    ]),
                ]),
                Section::make('Paušál')->description('Jen pro nás, klient to v reportu nevidí.')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('fee_czk')->label('Paušál')->numeric()->minValue(0)->suffix('Kč / měsíc'),
                        TextInput::make('included_hours')->label('Hodin v paušálu')->numeric()->minValue(0)->step(0.5),
                        TextInput::make('hourly_rate')->label('Sazba za práci navíc')->numeric()->minValue(0)->suffix('Kč / h'),
                    ]),
                ]),
                Section::make('Co ukazovat')
                    ->description('Platí pro detail klienta i pro reporty, které klient dostane.')
                    ->collapsed()
                    ->schema([
                        CheckboxList::make('dashboard.tiles')
                            ->label('Dlaždice s čísly')
                            ->options(fn (): array => MetricCatalog::options($this->client->adGoal()))
                            ->helperText('Dosah, frekvence a unikátní CTR jsou jen u přednastavených období, ne u vlastního od–do.')
                            ->columns(3)
                            ->required(),
                        CheckboxList::make('dashboard.sections')
                            ->label('Sekce')
                            ->options(PerformanceView::SECTIONS)
                            ->columns(3),
                    ]),
                Section::make('Reporty')->schema([
                    TagsInput::make('report_recipients')
                        ->label('Komu posílat')
                        ->placeholder('jmeno@firma.cz')
                        ->nestedRecursiveRules(['email'])
                        ->helperText('Prázdné = kontaktní e-mail klienta'.($this->client->contact_email ? " ({$this->client->contact_email})" : '').'.'),
                    Grid::make(2)->schema([
                        Toggle::make('weekly_report')->label('Týdenní koncept každé pondělí'),
                        Toggle::make('monthly_report')->label('Měsíční koncept 1. v měsíci'),
                    ]),
                ]),
            ])
            ->action(function (array $data): void {
                // Výchozí dlaždice a všechny sekce se neukládají (null). Klient pak
                // dostává výchozí sadu, i když se časem změní.
                $goal = $data['primary_goal'] instanceof PrimaryGoal ? $data['primary_goal'] : PrimaryGoal::from($data['primary_goal']);
                $tiles = MetricCatalog::selectionToStore(array_values($data['dashboard']['tiles'] ?? []), $goal);
                $allSections = array_diff(array_keys(PerformanceView::SECTIONS), $data['dashboard']['sections'] ?? []) === [];
                $data['dashboard'] = $tiles === null && $allSections ? null : [
                    'tiles' => $tiles,
                    'sections' => $allSections ? null : array_values($data['dashboard']['sections'] ?? []),
                ];

                $this->client->adSettings()->updateOrCreate(['client_id' => $this->client->getKey()], $data);

                Notification::make()->success()->title('Uloženo')->send();
            });
    }

    /** Pauza mezi ručními načteními u jednoho klienta, v minutách. */
    public const MANUAL_SYNC_COOLDOWN = 30;

    /**
     * Ruční načtení čísel. Čísla se stahují samy každé ráno. Tlačítko je pro
     * chvíle, kdy je potřeba dnešní stav hned, a má pauzu, aby se na něj
     * nedalo klikat dokola.
     */
    private function syncAction(): Action
    {
        return Action::make('sync')
            ->label(fn (): string => ($wait = $this->manualSyncWait()) ? "Znovu za {$wait} min" : 'Načíst čísla znovu')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->disabled(fn (): bool => $this->manualSyncWait() !== null)
            ->tooltip(fn (): ?string => ($synced = $this->client->adAccounts()->max('last_synced_at'))
                ? 'Naposledy staženo '.Carbon::parse($synced)->format('j. n. H:i')
                : null)
            ->requiresConfirmation()
            ->modalHeading('Načíst čísla znovu?')
            ->modalDescription('Čísla se stahují samy každé ráno v 6:00. Ručně je načtěte, jen když potřebujete aktuální stav hned. '
                .'Stáhne se posledních '.config('ads.sync_days').' dní a dosah za přednastavená období, nejvýš tři dotazy na účet. Další ruční načtení půjde za '.self::MANUAL_SYNC_COOLDOWN.' minut.')
            ->modalSubmitActionLabel('Načíst')
            ->action(function (AccountSync $sync): void {
                // Cache::add projde jen jednou za pauzu, i při dvojkliku nebo ve dvou oknech.
                if (! Cache::add($this->manualSyncKey(), now()->timestamp, now()->addMinutes(self::MANUAL_SYNC_COOLDOWN))) {
                    Notification::make()->warning()->title('Čísla se načítala před chvílí')->body('Znovu to půjde za '.$this->manualSyncWait().' min.')->send();

                    return;
                }

                $days = (int) config('ads.sync_days');
                $period = Period::between(now()->subDays($days), now()->subDay());
                $errors = [];

                foreach ($this->client->adAccounts()->active()->get() as $account) {
                    $run = $sync->sync($account, $period, withReach: true);

                    if ($run->status === 'failed') {
                        $errors[] = "{$account->name}: {$run->error}";
                    }
                }

                $errors
                    ? Notification::make()->danger()->title('Něco se nenačetlo')->body(implode("\n", $errors))->persistent()->send()
                    : Notification::make()->success()->title('Čísla za posledních '.$days.' dní jsou načtená')->send();
            });
    }

    /** Za kolik minut půjde další ruční načtení. Null = hned. */
    public function manualSyncWait(): ?int
    {
        $at = Cache::get($this->manualSyncKey());

        if (! $at) {
            return null;
        }

        $left = (int) ceil(($at + self::MANUAL_SYNC_COOLDOWN * 60 - now()->timestamp) / 60);

        return $left > 0 ? $left : null;
    }

    private function manualSyncKey(): string
    {
        return 'ads.manual-sync.client.'.$this->client->getKey();
    }

    /**
     * Doplnění starší historie u účtů, které mají jen část (propojené dřív,
     * kdy se stahovalo 90 dní). Stáhne jen chybějící úsek před nejstarším
     * dnem, který máme. Jednou za hodinu.
     */
    private function historyAction(): Action
    {
        return Action::make('history')
            ->visible(fn (): bool => (bool) config('ads.history_backfill'))
            ->label('Doplnit starší historii')
            ->icon(Heroicon::OutlinedClock)
            ->modalHeading('Doplnit starší historii')
            ->modalDescription(fn (): string => ($this->dataSince() ? 'Čísla máme od '.Carbon::parse($this->dataSince())->format('j. n. Y').'. ' : '')
                .'Stáhne se jen úsek, který chybí, po čtvrtletích: zhruba 1 dotaz na každé 3 měsíce a účet, jednou provždy. Meta vydá nejvýš '.Period::MAX_HISTORY_MONTHS.' měsíců zpátky.')
            ->schema([
                Select::make('months')
                    ->label('Jak daleko zpátky')
                    ->options([6 => '6 měsíců', 12 => '1 rok', 24 => '2 roky', Period::MAX_HISTORY_MONTHS => 'Všechno, co Meta vydá (37 měsíců)'])
                    ->default(Period::MAX_HISTORY_MONTHS)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Doplnit')
            ->action(function (array $data): void {
                if (! Cache::add('ads.history.client.'.$this->client->getKey(), true, now()->addHour())) {
                    Notification::make()->warning()->title('Historie se doplňovala před chvílí')->body('Znovu to půjde za hodinu.')->send();

                    return;
                }

                $months = min(Period::MAX_HISTORY_MONTHS, (int) $data['months']);
                $from = now()->subMonthsNoOverflow($months)->addDay()->startOfDay();
                $queued = 0;

                foreach ($this->client->adAccounts()->active()->get() as $account) {
                    $earliest = $account->isAnalytics() ? $account->traffic()->min('date') : $account->stats()->min('date');
                    $to = $earliest ? Carbon::parse($earliest)->subDay() : now()->subDay();

                    if ($from->gt($to)) {
                        continue;
                    }

                    BackfillAdAccount::dispatchAfterResponse($account->getKey(), $from->toDateString(), $to->toDateString());
                    $queued++;
                }

                $queued > 0
                    ? Notification::make()->success()->title('Historie se doplňuje na pozadí')->body('Za minutu obnovte stránku.')->send()
                    : Notification::make()->info()->title('Historii za tohle období už máme')->send();
            });
    }

    private function adviceAction(): Action
    {
        return Action::make('advice')
            ->label('Návrh úprav od Clauda')
            ->icon(Heroicon::OutlinedSparkles)
            ->visible(fn (AdsAdvisor $advisor): bool => $advisor->enabled())
            ->requiresConfirmation()
            ->modalHeading('Návrh úprav od Clauda')
            ->modalDescription('Claude dostane čísla klienta za zvolené období po kampaních a živá upozornění. Volání je placené, obvykle v jednotkách korun.')
            ->modalSubmitActionLabel('Zeptat se')
            ->action(function (AdsAdvisor $advisor): void {
                $client = $this->freshClient();
                $advice = $advisor->advise(
                    $client,
                    ClientPerformance::build($client, $this->currentPeriod()),
                    $this->alerts()->pluck('title')->all(),
                );

                if ($advice === null) {
                    Notification::make()->danger()->title('Claude neodpověděl')->body($advisor->lastError())->send();

                    return;
                }

                $client->adSettings()->updateOrCreate(['client_id' => $client->getKey()], ['advice' => $advice, 'advice_at' => now()]);
                Notification::make()->success()->title('Návrh je pod grafem')->send();
            });
    }

    private function updateAlert(int $id, AlertStatus $status, string $message): void
    {
        $alert = $this->client->adAlerts()->findOrFail($id);
        $alert->update([
            'status' => $status,
            'resolved_at' => $status === AlertStatus::Resolved ? Carbon::now() : null,
        ]);

        Notification::make()->success()->title($message)->send();
    }

    /** Klient s účty a nastavením, načtený znovu po každé akci. */
    private function freshClient(): Client
    {
        return $this->client->load(['adAccounts', 'adSettings']);
    }
}
