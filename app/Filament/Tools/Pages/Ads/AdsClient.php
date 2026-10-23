<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Enums\Ads\AdEventType;
use App\Enums\Ads\AlertStatus;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\Ads\ReportType;
use App\Filament\Tools\Actions\Ads\AdChangeForm;
use App\Filament\Tools\Actions\Ads\AdEventForm;
use App\Filament\Tools\Actions\Ads\CampaignGroupForm;
use App\Filament\Tools\Actions\Ads\ConnectAdAccountAction;
use App\Filament\Tools\Actions\Ads\LogTimeAction;
use App\Filament\Tools\Pages\Ads\Concerns\HasAdsPeriod;
use App\Filament\Tools\Resources\AdEvents\AdEventResource;
use App\Filament\Tools\Resources\AdReports\AdReportResource;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Filament\Tools\Resources\TimeEntries\TimeEntryResource;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdChange;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdEvent;
use App\Models\Ads\AdReport;
use App\Models\Client;
use App\Models\TimeEntry;
use App\Settings\AdsSettings;
use App\Support\Ads\AdsScope;
use App\Support\Ads\Ai\AdsAdvisor;
use App\Support\Ads\Billing;
use App\Support\Ads\BusinessResults;
use App\Support\Ads\CampaignGroups;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\CsvExport;
use App\Support\Ads\DataStatus;
use App\Support\Ads\Format;
use App\Support\Ads\MetricCatalog;
use App\Support\Ads\Metrics;
use App\Support\Ads\PerformanceView;
use App\Support\Ads\Period;
use App\Support\Ads\ReportBuilder;
use App\Support\Ads\Stats;
use App\Support\Ads\TopAds;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Detail klienta v reklamách: stav dat, hlavní čísla se srovnáním a proti
 * cíli, graf po dnech se zásahy a termíny, události po cestě k nákupu,
 * kampaně se skupinami, čerpání rozpočtu, upozornění, termíny, historie
 * zásahů a reporty.
 *
 * Nahoře se přepíná celý účet / skupina kampaní / kampaně mimo skupiny.
 * Výběr platí pro čísla, graf, kampaně, report i export.
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

    /** Skupina kampaní: ID, „mimo“, nebo nic = celý účet. */
    #[Url(as: 'skupina')]
    public ?string $group = null;

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
        return $this->scope()->label().' · '.$this->periodHeading();
    }

    /** Na co se díváme: celý účet, skupina, nebo kampaně mimo skupiny. */
    public function scope(): AdsScope
    {
        return AdsScope::for($this->freshClient(), $this->group);
    }

    public function dataStatus(): DataStatus
    {
        return DataStatus::for($this->freshClient()->adAccounts);
    }

    public function setGroup(?string $group): void
    {
        $this->group = $group === 'all' ? null : $group;
    }

    /**
     * Přepínač nahoře: celý účet, skupiny, mimo skupiny. U každé útrata za
     * zvolené období, ať je vidět, kolik jde mimo výkonnostní kampaně.
     *
     * @return list<array{key: string, label: string, spend: string, goal: ?string, active: bool, id: ?int}>
     */
    public function scopes(): array
    {
        $client = $this->freshClient();
        $groups = $client->adCampaignGroups()->get();

        if ($groups->isEmpty()) {
            return [];
        }

        $period = $this->currentPeriod();
        $byCampaign = Stats::byCampaign($client->activeAdAccountIds(), $period);
        $campaigns = CampaignGroups::campaigns($client);
        $sum = fn ($ids): float => collect($ids)->sum(fn (int $id): float => ($byCampaign[$id] ?? Metrics::empty())->spend());
        $currency = $client->adCurrency();
        $current = $this->scope()->key();

        $rows = [['key' => 'all', 'label' => 'Celý účet', 'spend' => Format::money($byCampaign->sum(fn (Metrics $m): float => $m->spend()), $currency), 'goal' => null, 'active' => $current === 'all', 'id' => null]];

        foreach ($groups as $group) {
            $rows[] = [
                'key' => (string) $group->getKey(),
                'label' => $group->name,
                'spend' => Format::money($sum($campaigns->where('ad_campaign_group_id', $group->getKey())->pluck('id')), $currency),
                'goal' => $group->goal->templateLabel(),
                'active' => $current === (string) $group->getKey(),
                'id' => $group->getKey(),
            ];
        }

        $rows[] = [
            'key' => AdsScope::OUTSIDE,
            'label' => 'Mimo skupiny',
            'spend' => Format::money($sum($campaigns->whereNull('ad_campaign_group_id')->pluck('id')), $currency),
            'goal' => null,
            'active' => $current === AdsScope::OUTSIDE,
            'id' => null,
        ];

        return $rows;
    }

    /** @return Collection<int, AdCampaign> Nové kampaně, které čekají na zařazení do skupiny. */
    public function waitingCampaigns(): Collection
    {
        return CampaignGroups::waiting($this->freshClient());
    }

    /** @return array<int, string> Skupiny pro výběr u kampaně. */
    public function groupOptions(): array
    {
        return $this->freshClient()->adCampaignGroups()->pluck('name', 'id')->all();
    }

    /** Ruční přesun kampaně do skupiny z tabulky kampaní. */
    public function assignCampaign(int $campaignId, string $groupId): void
    {
        CampaignGroups::assign($this->freshClient(), $campaignId, is_numeric($groupId) ? (int) $groupId : null);

        Notification::make()->success()->title(is_numeric($groupId) ? 'Kampaň zařazena' : 'Kampaň je mimo skupiny')->send();
    }

    /**
     * Stav ruční obnovy: čeká, nebo kdy půjde znovu.
     *
     * @return array{pending: bool, next: ?string}
     */
    public function refreshState(): array
    {
        $accounts = $this->freshClient()->adAccounts->where('is_active', true)->reject(fn (AdAccount $account): bool => $account->isAnalytics());
        $last = $accounts->pluck('refreshed_at')->filter()->max();
        $next = $last ? Carbon::parse($last)->addMinutes((int) config('ads.refresh_cooldown_minutes')) : null;

        return [
            'pending' => $accounts->contains(fn (AdAccount $account): bool => $account->refresh_requested_at !== null),
            'next' => $next?->isFuture() ? $next->timezone('Europe/Prague')->format('G:i') : null,
        ];
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
        return new PerformanceView(ClientPerformance::build($this->freshClient(), $this->currentPeriod(), $this->scope(), $this->compare));
    }

    /**
     * Živá upozornění. U skupiny jen její a ta za celý účet (data, měření).
     *
     * @return Collection<int, AdAlert>
     */
    public function alerts(): Collection
    {
        $scope = $this->scope();

        return $this->client->adAlerts()->live()
            ->with('group')
            ->when($scope->group !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('ad_campaign_group_id')->orWhere('ad_campaign_group_id', $scope->group->getKey())))
            ->worstFirst()
            ->get();
    }

    /** @return Collection<int, AdAlert> Co se za poslední týden samo vrátilo do normálu. */
    public function recovered(): Collection
    {
        return $this->client->adAlerts()
            ->where('status', AlertStatus::Resolved)
            ->where('resolved_by', 'system')
            ->whereDate('recovered_on', '>=', today()->subDays(7))
            ->latest('recovered_on')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, AdEvent> Nevyřešené termíny: po termínu a do 60 dní dopředu. */
    public function events(): Collection
    {
        return $this->client->adEvents()->open()
            ->with(['group', 'campaign'])
            ->whereDate('due_on', '<=', today()->addDays(60))
            ->orderBy('due_on')
            ->get();
    }

    /** @return Collection<int, AdChange> Poslední zásahy (bez ohledu na období). */
    public function recentChanges(): Collection
    {
        $scope = $this->scope();

        return $this->client->adChanges()
            ->with(['group', 'campaign', 'user'])
            ->when($scope->group !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('ad_campaign_group_id')->orWhere('ad_campaign_group_id', $scope->group->getKey())))
            ->latest('changed_on')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    /**
     * Skutečné výsledky vedle čísel z reklam: e-shop (objednávky, tržby)
     * nebo leady (relevantní, obchody). Podle cíle klienta a skupin.
     *
     * @return array{shop: list<array<string, mixed>>, leads: list<array<string, mixed>>, show_shop: bool, show_leads: bool}
     */
    public function business(): array
    {
        $client = $this->freshClient();
        $shop = BusinessResults::scopeFor($client, [PrimaryGoal::Purchases]);
        $leads = BusinessResults::scopeFor($client, [PrimaryGoal::Leads, PrimaryGoal::Recruitment]);

        return [
            'shop' => $shop ? BusinessResults::shop($client, $shop) : [],
            'leads' => $leads ? BusinessResults::leads($client, $leads) : [],
            'show_shop' => $shop !== null,
            'show_leads' => $leads !== null,
            'shop_scope' => $shop?->label(),
            'leads_scope' => $leads?->label(),
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, period: ?string, tests_url: string} */
    public function topAds(): array
    {
        $scope = $this->scope();

        return [
            'rows' => TopAds::for($scope),
            'period' => TopAds::periodLabel($scope),
            'tests_url' => ClientResource::getUrl('edit', ['record' => $this->client]),
        ];
    }

    public function logShopResultAction(): Action
    {
        return Action::make('logShopResult')
            ->label('Zapsat tržby e-shopu')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Objednávky a tržby z e-shopu')
            ->modalDescription('Čísla z administrace e-shopu (Shoptet, Upgates) za období. Ukážou se vedle toho, co si připsaly reklamy, a z nich se počítají náklady na obrat.')
            ->modalWidth('xl')
            ->fillForm(fn (): array => ['period_from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'period_to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('period_from')->label('Od')->native(false)->displayFormat('j. n. Y')->required(),
                    DatePicker::make('period_to')->label('Do')->native(false)->displayFormat('j. n. Y')->required()->afterOrEqual('period_from'),
                    TextInput::make('orders')->label('Objednávek')->numeric()->minValue(0),
                    TextInput::make('revenue')->label('Tržby s DPH')->numeric()->minValue(0)->suffix($this->client->adCurrency()),
                ]),
                TextInput::make('source')->label('Odkud čísla jsou')->placeholder('Shoptet → Přehledy → Objednávky'),
                TextInput::make('note')->label('Poznámka')->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->client->adBusinessResults()->create($data + ['user_id' => auth()->id()]);
                Notification::make()->success()->title('Zapsáno')->send();
            });
    }

    public function logLeadQualityAction(): Action
    {
        return Action::make('logLeadQuality')
            ->label('Zapsat kvalitu leadů')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Co se s leady stalo')
            ->modalDescription('Čísla od klienta za období: kolik leadů eviduje, kolik bylo relevantních a kolik skončilo obchodem.')
            ->modalWidth('xl')
            ->fillForm(fn (): array => ['period_from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'period_to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('period_from')->label('Od')->native(false)->displayFormat('j. n. Y')->required(),
                    DatePicker::make('period_to')->label('Do')->native(false)->displayFormat('j. n. Y')->required()->afterOrEqual('period_from'),
                    TextInput::make('leads')->label('Leadů u klienta')->numeric()->minValue(0),
                    TextInput::make('contacted')->label('Ozvali se / dovolali se')->numeric()->minValue(0),
                    TextInput::make('qualified')->label('Relevantní')->numeric()->minValue(0),
                    TextInput::make('deals')->label('Obchody')->numeric()->minValue(0),
                    TextInput::make('deal_value')->label('Hodnota obchodů')->numeric()->minValue(0)->suffix($this->client->adCurrency()),
                    TextInput::make('source')->label('Odkud čísla jsou')->placeholder('CRM klienta, tabulka, telefonát'),
                ]),
                TextInput::make('note')->label('Poznámka')->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->client->leadFeedback()->create($data);
                Notification::make()->success()->title('Zapsáno')->send();
            });
    }

    public function resolveEvent(int $id): void
    {
        $event = $this->client->adEvents()->findOrFail($id);
        $event->update(['resolved_at' => now(), 'resolved_by' => auth()->id()]);

        Notification::make()->success()->title('Termín vyřešen')->send();
    }

    public function calendarUrl(): string
    {
        return AdEventResource::getUrl();
    }

    /** @return Collection<int, AdReport> */
    public function reports(): Collection
    {
        return $this->client->adReports()->latest('period_end')->limit(6)->get();
    }

    /**
     * Připojené účty se stavem synchronizace.
     *
     * @return Collection<int, array{name: string, platform: string, status: string, ok: bool, synced: ?string, through: ?string, error: ?string, active: bool, manager_url: ?string}>
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
                'through' => $account->data_through?->format('j. n. Y'),
                'error' => $account->last_sync_error,
                'active' => $account->is_active,
                'manager_url' => $account->managerUrl(),
            ]);
    }

    /** @return array{label: string, percent: float, expected: float, pace: ?string, projected: ?string, note: ?string}|null */
    public function budget(): ?array
    {
        $scope = $this->scope();
        $pace = $scope->budgetPace($this->dataStatus());

        if ($pace === null) {
            return null;
        }

        $currency = $scope->currency();

        return [
            'label' => Format::money($pace->spent, $currency).' z '.Format::money($pace->budget, $currency),
            'percent' => min(100, round($pace->spentPercent(), 1)),
            'expected' => min(100, round($pace->expected / $pace->budget * 100, 1)),
            'pace' => $pace->pacePercent() !== null ? Format::number($pace->pacePercent()).' % plánu'.($pace->partial ? '' : ' k dnešku') : null,
            // Odhad z neúplných dat by zkresloval, ukazujeme ho jen z kompletních dní.
            'projected' => $pace->projected() !== null ? 'Odhad za měsíc '.Format::money($pace->projected(), $currency) : null,
            'note' => $pace->note(),
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

        // U skupiny cíl, cena a ROAS skupiny. Paušál a reporty jsou vždy klienta.
        $scope = $this->scope();

        return array_values(array_filter([
            ['label' => 'Hlavní cíl', 'value' => $scope->goal()->getLabel()],
            $scope->targetCpa() ? ['label' => 'Cílová cena za konverzi', 'value' => Format::unitPrice($scope->targetCpa(), $currency)] : null,
            $scope->targetRoas() ? ['label' => 'Cílový ROAS', 'value' => Format::roas($scope->targetRoas())] : null,
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
        $this->updateAlert($id, AlertStatus::Acknowledged, 'Řešíte');
    }

    public function resolveAlert(int $id): void
    {
        $this->updateAlert($id, AlertStatus::Resolved, 'Upozornění vyřešeno');
    }

    public function dismissAlert(int $id): void
    {
        $this->updateAlert($id, AlertStatus::Dismissed, 'Upozornění zamítnuto, týden se neozve');
    }

    public function reopenAlert(int $id): void
    {
        $this->updateAlert($id, AlertStatus::Open, 'Upozornění je zase nové');
    }

    /** Čekám na klienta: upozornění dál platí, jen víme, na co se čeká. */
    public function waitAlertAction(): Action
    {
        return Action::make('waitAlert')
            ->label('Čekám na klienta')
            ->size('xs')
            ->color('gray')
            ->modalHeading('Čekám na klienta')
            ->modalWidth('md')
            ->schema([
                TextInput::make('waiting_for')->label('Na co čekáme')->placeholder('nové fotky produktů, schválení rozpočtu…')->maxLength(255),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->client->adAlerts()->findOrFail($arguments['alert'])->moveTo(AlertStatus::Waiting, auth()->user()?->name, $data['waiting_for'] ?? null);
                Notification::make()->success()->title('Čeká se na klienta')->send();
            });
    }

    /** Odložit: do zvoleného dne upozornění nesvítí, pak se ozve, pokud problém trvá. */
    public function snoozeAlertAction(): Action
    {
        return Action::make('snoozeAlert')
            ->label('Odložit')
            ->size('xs')
            ->color('gray')
            ->modalHeading('Odložit upozornění')
            ->modalDescription('Do zvoleného dne nebude svítit v přehledu ani v e-mailu. Když problém potom trvá, ozve se znovu.')
            ->modalWidth('md')
            ->schema([
                Select::make('days')->label('Na jak dlouho')->options([1 => 'Do zítřka', 3 => 'Na 3 dny', 7 => 'Na týden', 14 => 'Na 2 týdny'])->default(3)->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $until = today()->addDays((int) $data['days']);
                $this->client->adAlerts()->findOrFail($arguments['alert'])->snooze($until, auth()->user()?->name);
                Notification::make()->success()->title('Odloženo do '.$until->format('j. n.'))->send();
            });
    }

    public function createGroupAction(): Action
    {
        return Action::make('createGroup')
            ->label('Nová skupina kampaní')
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->color('gray')
            ->modalHeading('Nová skupina kampaní')
            ->modalDescription('Zaškrtněte kampaně, které k sobě patří (třeba Prodej, Brand, Nábor). Skupina má vlastní cíl, rozpočet, čísla, upozornění i report.')
            ->modalWidth('3xl')
            ->schema(fn (): array => CampaignGroupForm::schema($this->freshClient()))
            ->fillForm(fn (): array => ['goal' => $this->freshClient()->adGoal()->value])
            ->action(function (array $data): void {
                $group = CampaignGroupForm::save($this->freshClient(), $data);
                $this->group = (string) $group->getKey();
                Notification::make()->success()->title('Skupina '.$group->name.' uložena')->send();
            });
    }

    public function editGroupAction(): Action
    {
        return Action::make('editGroup')
            ->label('Upravit skupinu')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->scope()->group !== null)
            ->modalHeading(fn (): string => 'Skupina '.$this->scope()->group?->name)
            ->modalWidth('3xl')
            ->schema(fn (): array => CampaignGroupForm::schema($this->freshClient()))
            ->fillForm(fn (): array => CampaignGroupForm::fill($this->scope()->group))
            ->extraModalFooterActions(fn (): array => [
                Action::make('deleteGroup')
                    ->label('Smazat skupinu')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Kampaně zůstanou, jen budou mimo skupiny. Upozornění skupiny zmizí.')
                    ->cancelParentActions()
                    ->action(function (): void {
                        $this->scope()->group?->delete();
                        $this->group = null;
                        Notification::make()->success()->title('Skupina smazána')->send();
                    }),
            ])
            ->action(function (array $data): void {
                $group = CampaignGroupForm::save($this->freshClient(), $data, $this->scope()->group);
                Notification::make()->success()->title('Skupina '.$group->name.' uložena')->send();
            });
    }

    public function logChangeAction(): Action
    {
        return Action::make('logChange')
            ->label('Zapsat zásah')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Zapsat zásah')
            ->modalDescription('Co jsme v účtu nebo na webu změnili. Ukáže se v grafu a v reportu a agent z toho vychází, když hledá příčinu změny.')
            ->modalWidth('2xl')
            ->schema(fn (): array => AdChangeForm::schema($this->freshClient(), $this->scope()->group?->getKey()))
            ->action(function (array $data): void {
                AdChangeForm::create($this->freshClient(), $data);
                Notification::make()->success()->title('Zásah zapsán')->send();
            });
    }

    public function addEventAction(): Action
    {
        return Action::make('addEvent')
            ->label('Přidat termín')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Nový termín')
            ->modalDescription('Konec licence, začátek akce, příprava Black Friday, čekání na podklady. Upozorníme předem a v ranním přehledu termín visí, dokud ho neoznačíte jako vyřešený.')
            ->modalWidth('2xl')
            ->schema(fn (): array => AdEventForm::schema($this->freshClient()))
            ->fillForm(fn (): array => ['type' => AdEventType::LicenseEnd->value, 'remind_days' => 7, 'ad_campaign_group_id' => $this->scope()->group?->getKey()])
            ->action(function (array $data): void {
                AdEventForm::save($data, $this->freshClient());
                Notification::make()->success()->title('Termín uložen')->send();
            });
    }

    public function editEventAction(): Action
    {
        return Action::make('editEvent')
            ->label('Upravit')
            ->size('xs')
            ->color('gray')
            ->modalHeading('Upravit termín')
            ->modalWidth('2xl')
            ->schema(fn (): array => AdEventForm::schema($this->freshClient()))
            ->fillForm(fn (array $arguments): array => AdEventForm::fill($this->client->adEvents()->findOrFail($arguments['event'])))
            ->action(function (array $data, array $arguments): void {
                AdEventForm::save($data, $this->freshClient(), $this->client->adEvents()->findOrFail($arguments['event']));
                Notification::make()->success()->title('Termín uložen')->send();
            });
    }

    public function reportUrl(int $id): string
    {
        return AdReportResource::getUrl('edit', ['record' => $id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createReportAction(),
            $this->logTimeAction(),
            $this->settingsAction(),
            ActionGroup::make([
                $this->refreshAction(),
                $this->exportAction(),
                $this->createGroupAction(),
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
                Select::make('group')
                    ->label('Za co')
                    ->options(fn (): array => $this->groupOptions())
                    ->placeholder('Celý účet')
                    ->default(fn (): ?int => $this->scope()->group?->getKey())
                    ->visible(fn (): bool => $this->groupOptions() !== []),
            ])
            ->action(function (array $data, ReportBuilder $builder): void {
                $type = $data['type'] instanceof ReportType ? $data['type'] : ReportType::from($data['type']);
                $scope = AdsScope::for($this->freshClient(), $data['group'] ?? null);
                $report = $builder->create($this->freshClient(), $type, Period::between($data['from'], $data['to']), $scope);

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
                    'included_hours', 'hourly_rate', 'report_recipients', 'weekly_report', 'monthly_report', 'alert_overrides',
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
                Section::make('Upozornění pro tohoto klienta')
                    ->description('Prázdné = platí Nastavení reklam. Výpadek stahování a stav účtu se ztlumit nedají.')
                    ->collapsed()
                    ->schema([
                        Select::make('alert_overrides.urgency')
                            ->label('Naléhavost')
                            ->options([
                                'high' => 'Vysoká: Pozor se hlásí jako Hoří (velký rozpočet, akce s termínem)',
                                'normal' => 'Běžná',
                                'low' => 'Nízká: Pozor se hlásí jako Tip (malý účet, awareness)',
                            ])
                            ->placeholder('Běžná'),
                        CheckboxList::make('alert_overrides.muted')
                            ->label('Ztlumená pravidla')
                            ->options(fn (): array => self::mutableRules())
                            ->columns(2),
                        Grid::make(4)->schema([
                            TextInput::make('alert_overrides.budget_under_pct')->label('Čerpání pod (%)')->numeric()->minValue(1)->maxValue(100)->placeholder((string) $this->settings()->budget_under_pct),
                            TextInput::make('alert_overrides.budget_over_pct')->label('Čerpání nad (%)')->numeric()->minValue(100)->maxValue(300)->placeholder((string) $this->settings()->budget_over_pct),
                            TextInput::make('alert_overrides.cpa_over_pct')->label('Cena za konverzi nad cíl o (%)')->numeric()->minValue(1)->placeholder((string) $this->settings()->cpa_over_pct),
                            TextInput::make('alert_overrides.roas_under_pct')->label('ROAS pod cíl o (%)')->numeric()->minValue(1)->maxValue(100)->placeholder((string) $this->settings()->roas_under_pct),
                            TextInput::make('alert_overrides.min_conversions')->label('Hodnotit od konverzí za týden')->numeric()->minValue(1)->placeholder((string) $this->settings()->min_conversions),
                            TextInput::make('alert_overrides.no_conversion_days')->label('Dní útraty bez konverze')->numeric()->minValue(1)->maxValue(14)->placeholder((string) $this->settings()->no_conversion_days),
                            TextInput::make('alert_overrides.spend_spike_pct')->label('Skok útraty o (%)')->numeric()->minValue(10)->placeholder((string) $this->settings()->spend_spike_pct),
                            TextInput::make('alert_overrides.frequency_max')->label('Frekvence nad')->numeric()->minValue(1)->step(0.1)->placeholder((string) $this->settings()->frequency_max),
                        ]),
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

                // Prázdné prahy se neukládají, ať platí globální nastavení i po jeho změně.
                $overrides = collect($data['alert_overrides'] ?? [])
                    ->reject(fn ($value, string $key): bool => blank($value) || ($key === 'urgency' && $value === 'normal'))
                    ->map(fn ($value, string $key) => is_numeric($value) ? $value + 0 : $value)
                    ->all();
                $data['alert_overrides'] = $overrides ?: null;

                $this->client->adSettings()->updateOrCreate(['client_id' => $this->client->getKey()], $data);

                Notification::make()->success()->title('Uloženo')->send();
            });
    }

    /**
     * Pravidla, která jde u klienta ztlumit, s lidským popisem.
     *
     * @return array<string, string>
     */
    private static function mutableRules(): array
    {
        return [
            'no_conversions' => 'Útrata bez konverzí',
            'no_spend' => 'Včera nulová útrata',
            'budget_pacing' => 'Čerpání rozpočtu',
            'cost_above_target' => 'Cena za konverzi nad cílem',
            'roas_below_target' => 'ROAS pod cílem',
            'creative_fatigue' => 'Únava kreativ',
            'spend_spike' => 'Skok útraty',
            'measurement_value' => 'Měření: nákupy bez hodnoty',
            'measurement_duplicates' => 'Měření: víc nákupů než pokladen',
            'analytics_mismatch' => 'Měření: reklamy vs. web (GA4)',
            'ad_issues' => 'Zamítnuté reklamy',
            'landing_page' => 'Cílová stránka nefunguje',
            'landing_pixel' => 'Na cílové stránce chybí pixel',
            'lead_feedback' => 'Chybí zpětná vazba k leadům',
        ];
    }

    private function settings(): AdsSettings
    {
        return app(AdsSettings::class);
    }

    /**
     * Ruční obnova čísel. Odsud se na Metu nesahá: zapíše žádost, kterou
     * do pár minut vyřídí `ads:sync --requested`. Jeden účet nejvýš jednou
     * za ads.refresh_cooldown_minutes, ať se nepřibližujeme limitům.
     */
    private function refreshAction(): Action
    {
        return Action::make('refresh')
            ->label('Obnovit čísla')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalHeading('Obnovit čísla z reklamních systémů?')
            ->modalDescription(fn (): string => 'Stáhne se posledních '.config('ads.sync_days').' dní (a chybějící dny), stav kampaní a dosah, pak se přepočítají upozornění. Trvá to do 5 minut. Jeden účet jde obnovit jednou za '.((int) config('ads.refresh_cooldown_minutes') / 60).' h.')
            ->modalSubmitActionLabel('Obnovit')
            ->action(function (): void {
                $cooldown = now()->subMinutes((int) config('ads.refresh_cooldown_minutes'));
                $accounts = $this->client->adAccounts()->active()->get();
                $queued = $accounts
                    ->filter(fn (AdAccount $account): bool => $account->refresh_requested_at === null && ($account->refreshed_at === null || $account->refreshed_at->lt($cooldown)))
                    ->each(fn (AdAccount $account) => $account->forceFill(['refresh_requested_at' => now()])->save())
                    ->count();

                $queued > 0
                    ? Notification::make()->success()->title('Čísla se stáhnou do 5 minut')->body('Stránku pak obnovte.')->send()
                    : Notification::make()->warning()->title('Teď to nejde')->body('Obnova už čeká, nebo proběhla před chvílí. Další bude možná v '.($this->refreshState()['next'] ?? 'nejbližších minutách').'.')->send();
            });
    }

    private function exportAction(): Action
    {
        return Action::make('export')
            ->label('Stáhnout CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading('Export do CSV')
            ->modalDescription(fn (): string => $this->scope()->label().', '.$this->currentPeriod()->label().'. Středník a desetinná čárka, otevře se rovnou v Excelu.')
            ->modalWidth('md')
            ->schema([
                Select::make('layout')->label('Jak')->options(CsvExport::LAYOUTS)->default('days_campaigns')->required(),
            ])
            ->modalSubmitActionLabel('Stáhnout')
            ->action(fn (array $data) => (new CsvExport($this->scope(), $this->currentPeriod(), $data['layout']))->download());
    }

    /**
     * Doplnění starší historie u účtů, které mají jen část (propojené dřív,
     * kdy se stahovalo 90 dní). Jen zapíše přání, chybějící úsek před
     * nejstarším dnem stáhne ranní `ads:sync`.
     */
    private function historyAction(): Action
    {
        return Action::make('history')
            ->visible(fn (): bool => (bool) config('ads.history_backfill'))
            ->label('Doplnit starší historii')
            ->icon(Heroicon::OutlinedClock)
            ->modalHeading('Doplnit starší historii')
            ->modalDescription(fn (): string => ($this->dataSince() ? 'Čísla máme od '.Carbon::parse($this->dataSince())->format('j. n. Y').'. ' : '')
                .'Stáhne se zítra ráno v 6:00, jen úsek, který chybí, po čtvrtletích: zhruba 1 dotaz na každé 3 měsíce a účet, jednou provždy. Meta vydá nejvýš '.Period::MAX_HISTORY_MONTHS.' měsíců zpátky.')
            ->schema([
                Select::make('months')
                    ->label('Jak daleko zpátky')
                    ->options([6 => '6 měsíců', 12 => '1 rok', 24 => '2 roky', Period::MAX_HISTORY_MONTHS => 'Všechno, co Meta vydá (37 měsíců)'])
                    ->default(Period::MAX_HISTORY_MONTHS)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Doplnit')
            ->action(function (array $data): void {
                $months = min(Period::MAX_HISTORY_MONTHS, (int) $data['months']);
                $from = now()->subMonthsNoOverflow($months)->addDay()->toDateString();

                // Stáhne ji ranní ads:sync, odsud se na platformu nesahá.
                $queued = $this->client->adAccounts()->active()->get()
                    ->filter(fn (AdAccount $account): bool => $account->backfill_from === null || $account->backfill_from->toDateString() > $from)
                    ->each(fn (AdAccount $account) => $account->update(['backfill_from' => $from]))
                    ->count();

                $queued > 0
                    ? Notification::make()->success()->title('Historie se stáhne zítra ráno v 6:00')->body('Stáhne se jen úsek, který chybí.')->send()
                    : Notification::make()->info()->title('O tuhle historii už je požádáno')->send();
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
                    ClientPerformance::build($client, $this->currentPeriod(), $this->scope(), $this->compare),
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
        $this->client->adAlerts()->findOrFail($id)->moveTo($status, auth()->user()?->name);

        Notification::make()->success()->title($message)->send();
    }

    /** Klient s účty a nastavením, načtený znovu po každé akci. */
    private function freshClient(): Client
    {
        return $this->client->load(['adAccounts', 'adSettings']);
    }
}
