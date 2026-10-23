<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Filament\Tools\Actions\Ads\ConnectAdAccountAction;
use App\Filament\Tools\Pages\Ads\Concerns\HasAdsPeriod;
use App\Filament\Tools\Resources\AdAlerts\AdAlertResource;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdEvent;
use App\Models\Client;
use App\Settings\AdsSettings;
use App\Support\Ads\AdsScope;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\DataStatus;
use App\Support\Ads\Format;
use App\Support\Ads\MetricCatalog;
use App\Support\Ads\Metrics;
use App\Support\Ads\MorningSummary;
use App\Support\Ads\PeriodReach;
use App\Support\Ads\Stats;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Přehled všech klientů v reklamách na jedné obrazovce. Nahoře ranní souhrn
 * (problémy s daty, výkonové odchylky, termíny, reporty ke kontrole). Karta
 * na klienta: stav dat, hlavní výsledek proti cíli, šest čísel podle šablony
 * cíle (třemi tečkami jde vyměnit), skupiny kampaní, čerpání rozpočtu,
 * nejdůležitější upozornění a nejbližší termín. Klient s upozorněním je nahoře.
 */
class AdsOverview extends Page
{
    use HasAdsPeriod;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Přehled klientů';

    protected static ?string $title = 'Reklamy klientů';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'reklamy';

    protected string $view = 'filament.tools.pages.ads.overview';

    #[Url]
    public string $period = '7d';

    #[Url]
    public string $sort = 'alerts';

    public function getSubheading(): ?string
    {
        return $this->periodHeading();
    }

    /** @return list<array{key: string, count: int, label: string, url: string, color: string}> */
    public function summary(): array
    {
        return MorningSummary::for()->items();
    }

    /** Nejstarší „data kompletní do“ přes všechny klienty, do podnadpisu. */
    public function dataThrough(): ?string
    {
        $dates = Client::query()->withAds()->with('adAccounts')->get()
            ->map(fn (Client $client): ?CarbonImmutable => DataStatus::for($client->adAccounts)->completeThrough)
            ->filter();

        return $dates->isEmpty() ? null : $dates->min()->format('j. n. Y');
    }

    /** Upozornění, když období začíná dřív, než máme čísla. */
    public function periodNote(): ?string
    {
        $since = AdDailyStat::query()->min('date');

        return $this->historyNote($since ? substr((string) $since, 0, 10) : null, config('ads.history_backfill') ? 'Starší čísla doplníte na detailu klienta: Další → Doplnit starší historii.' : 'Starší čísla zatím nestahujeme.');
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, ['alerts', 'spend', 'name'], true) ? $sort : 'alerts';
    }

    /**
     * Karty klientů. Součty za všechny klienty se počítají dvěma dotazy
     * (období a srovnání) a jedním na sparklines, ne dotazem na klienta.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(): Collection
    {
        $clients = Client::query()->withAds()->with(['adAccounts', 'adSettings'])->get();

        if ($clients->isEmpty()) {
            return collect();
        }

        $period = $this->currentPeriod();
        $comparison = $this->comparisonPeriod();
        $ids = $clients->flatMap(fn (Client $client): array => $client->activeAdAccountIds())->all();
        $current = Stats::byAccount($ids, $period);
        $previous = Stats::byAccount($ids, $comparison);
        $byCampaign = Stats::byCampaign($ids, $period);
        $daily = Stats::dailyByAccount($ids, $period);
        $alerts = AdAlert::query()->visible()->get()->groupBy('client_id');
        $events = AdEvent::query()->open()->orderBy('due_on')->get()->groupBy('client_id');
        $reach = PeriodReach::byAccount($ids, $period);
        $previousReach = PeriodReach::byAccount($ids, $comparison);
        $groups = AdCampaignGroup::query()->whereIn('client_id', $clients->modelKeys())->with('campaigns:id,ad_campaign_group_id')->ordered()->get()->groupBy('client_id');

        $cards = $clients->map(function (Client $client) use ($period, $comparison, $current, $previous, $byCampaign, $daily, $alerts, $events, $reach, $previousReach, $groups): array {
            $accountIds = $client->activeAdAccountIds();
            $reachIds = PeriodReach::accountIds($client->adAccounts);
            $goal = $client->adGoal();
            $currency = $client->adCurrency();
            $status = DataStatus::for($client->adAccounts);
            // Neúplná data: součty ukážeme, ale změny ne. Propad by nebyl skutečný.
            $comparable = $status->covers($period);
            $m = $this->sum($current, $accountIds)->withPeriodReach(PeriodReach::total($reachIds, $period, $reach));
            $p = $this->sum($previous, $accountIds)->withPeriodReach(PeriodReach::total($reachIds, $comparison, $previousReach));
            $clientAlerts = $alerts->get($client->getKey(), collect());
            $worst = $clientAlerts->sortByDesc(fn (AdAlert $alert): int => $alert->severity->weight())->first()?->severity;
            $topAlert = $clientAlerts->sortByDesc(fn (AdAlert $alert): int => $alert->severity->weight())->first();
            $nextEvent = $events->get($client->getKey(), collect())->first();
            $pace = BudgetPace::for($accountIds, $client->adSettings?->monthly_budget, null, $status->completeThrough);
            $slots = MetricCatalog::cardSlots($goal);
            $scope = new AdsScope($client);

            $days = collect($period->dates())->map(function (string $date) use ($daily, $accountIds): Metrics {
                return collect($accountIds)->reduce(
                    fn (Metrics $carry, int $id): Metrics => $carry->plus($daily[$id][$date] ?? Metrics::empty()),
                    Metrics::empty(),
                );
            });

            return [
                'id' => $client->getKey(),
                'name' => $client->name,
                'url' => AdsClient::getUrl(['client' => $client->getKey(), ...$this->periodQuery()]),
                'goal' => $goal->conversionLabel(),
                'spend_raw' => $m->spend(),
                'has_data' => $m->hasData(),
                'status' => $status,
                'covered' => $comparable,
                'template' => $goal->templateLabel(),
                'goal_key' => $goal->value,
                'metrics' => array_map(fn (string $key): array => $this->metric($key, $goal, $currency, $m, $comparable ? $p : null), $slots),
                'targets' => $this->targets($scope, $m),
                'top_alert' => $topAlert?->title,
                'next_event' => $nextEvent ? [
                    'title' => $nextEvent->title,
                    'type' => $nextEvent->type->getLabel(),
                    'when' => $nextEvent->due_on->format('j. n.').' ('.$nextEvent->whenLabel().')',
                    'overdue' => $nextEvent->isOverdue(),
                ] : null,
                'groups' => $groups->get($client->getKey(), collect())->map(function (AdCampaignGroup $group) use ($client, $byCampaign, $currency): array {
                    $metrics = $group->campaigns->reduce(fn (Metrics $carry, $campaign): Metrics => $carry->plus($byCampaign[$campaign->getKey()] ?? Metrics::empty()), Metrics::empty());

                    return [
                        'name' => $group->name,
                        'template' => $group->goal->templateLabel(),
                        'spend' => Format::money($metrics->spend(), $currency),
                        'targets' => $this->targets(new AdsScope($client, $group), $metrics),
                        'url' => AdsClient::getUrl(['client' => $client->getKey(), 'skupina' => $group->getKey(), ...$this->periodQuery()]),
                    ];
                })->values()->all(),
                'spark_spend' => $days->map(fn (Metrics $day): float => $day->spend())->all(),
                'spark_conversions' => $days->map(fn (Metrics $day): float => $day->conversions($goal))->all(),
                'budget' => $pace ? [
                    'label' => Format::money($pace->spent, $currency).' z '.Format::money($pace->budget, $currency),
                    'percent' => min(100, round($pace->spentPercent(), 1)),
                    // Kde by čerpání mělo být k dnešku. Značka na pruhu.
                    'expected' => min(100, round($pace->expected / $pace->budget * 100, 1)),
                    'pace' => $pace->pacePercent() !== null ? Format::number($pace->pacePercent()).' % plánu' : null,
                    'note' => $pace->note(),
                ] : null,
                'alerts' => $clientAlerts->count(),
                'worst' => $worst,
                'worst_weight' => $worst?->weight() ?? 0,
                'worst_color' => $worst?->getColor() ?? 'gray',
                'sync_error' => $status->state === DataStatus::ERROR,
                'accounts' => $client->adAccounts->where('is_active', true)->pluck('name')->implode(', '),
            ];
        });

        return match ($this->sort) {
            'spend' => $cards->sortByDesc('spend_raw')->values(),
            'name' => $cards->sortBy(fn (array $card): string => mb_strtolower($card['name']))->values(),
            default => $cards->sortBy([['worst_weight', 'desc'], ['alerts', 'desc'], ['spend_raw', 'desc']])->values(),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            ConnectAdAccountAction::make(),
            Action::make('alerts')
                ->label('Upozornění')
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('gray')
                ->url(fn (): string => AdAlertResource::getUrl()),
        ];
    }

    /**
     * @param  Collection<int, Metrics>  $byAccount
     * @param  list<int>  $accountIds
     */
    private function sum(Collection $byAccount, array $accountIds): Metrics
    {
        return collect($accountIds)->reduce(
            fn (Metrics $carry, int $id): Metrics => $carry->plus($byAccount[$id] ?? Metrics::empty()),
            Metrics::empty(),
        );
    }

    /**
     * Které číslo je na kartách v pozici $slot (0 až 5). Mění šablonu cíle
     * (e-shop, leady, boosting, nábor): platí pro všechny klienty s tímhle
     * cílem i pro všechny uživatele nástrojů.
     */
    public function setOverviewTile(int $slot, string $metric, string $goal = 'purchases'): void
    {
        $goal = PrimaryGoal::tryFrom($goal);

        if ($goal === null || $slot < 0 || $slot >= MetricCatalog::OVERVIEW_SLOTS || ! MetricCatalog::exists($metric)) {
            Notification::make()->danger()->title('Takové číslo neznáme')->send();

            return;
        }

        $settings = app(AdsSettings::class);
        $templates = $settings->card_templates;
        $slots = MetricCatalog::cardSlots($goal);
        $slots[$slot] = $metric;
        $templates[$goal->value] = $slots;
        $settings->card_templates = $templates;
        $settings->save();
    }

    /**
     * Hlavní výsledek proti cíli pro kartu: cena za konverzi a ROAS.
     *
     * @return list<array{label: string, value: string, target: string, ok: ?bool}>
     */
    private function targets(AdsScope $scope, Metrics $m): array
    {
        $goal = $scope->goal();
        $currency = $scope->currency();
        $rows = [];

        if ($goal->isPerformance() && $scope->targetCpa()) {
            $cost = $m->costPerConversion($goal);
            $rows[] = ['label' => $goal->costLabel(), 'value' => Format::unitPrice($cost, $currency), 'target' => Format::unitPrice($scope->targetCpa(), $currency), 'ok' => $cost === null ? null : $cost <= $scope->targetCpa()];
        }

        if ($goal->hasValue() && $scope->targetRoas()) {
            $roas = $m->roas();
            $rows[] = ['label' => 'ROAS', 'value' => Format::roas($roas), 'target' => Format::roas($scope->targetRoas()), 'ok' => $roas === null ? null : $roas >= $scope->targetRoas()];
        }

        return $rows;
    }

    /**
     * Všechna čísla pro výběr u dlaždice.
     *
     * @return array<string, string>
     */
    public function tileOptions(): array
    {
        return MetricCatalog::allOptions();
    }

    /**
     * Jedno číslo na kartě. Když pro cíl klienta nedává smysl (ROAS
     * u poptávek), ukáže se pomlčka.
     *
     * @return array{key: string, label: string, value: string, change: ?string, tone: string, hint: ?string}
     */
    private function metric(string $key, PrimaryGoal $goal, string $currency, Metrics $m, ?Metrics $p): array
    {
        $current = MetricCatalog::value($key, $m, $goal);
        $change = $p === null ? null : Metrics::change($current, MetricCatalog::value($key, $p, $goal));
        $direction = MetricCatalog::direction($key);

        return [
            'key' => $key,
            'label' => MetricCatalog::label($key, $goal),
            'description' => MetricCatalog::description($key, $goal),
            'value' => MetricCatalog::format($key, $current, $currency),
            'change' => Format::change($change),
            'tone' => match (true) {
                $change === null, $direction === 0, abs($change) < 3 => 'neutral',
                $change * $direction > 0 => 'good',
                default => 'bad',
            },
            'hint' => match (true) {
                $p === null => 'Data za období nejsou kompletní, srovnání neukazujeme',
                ! MetricCatalog::appliesTo($key, $goal) => 'U cíle „'.$goal->getLabel().'“ se nepočítá',
                MetricCatalog::needsPeriodReach($key) && ! $m->hasPeriodReach() => 'Dosah a frekvence '.PeriodReach::UNAVAILABLE_HINT,
                default => null,
            },
        ];
    }
}
