<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Filament\Tools\Actions\Ads\ConnectAdAccountAction;
use App\Filament\Tools\Resources\AdAlerts\AdAlertResource;
use App\Models\Ads\AdAlert;
use App\Models\Client;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\Format;
use App\Support\Ads\Metrics;
use App\Support\Ads\Period;
use App\Support\Ads\Stats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Přehled všech klientů v reklamách na jedné obrazovce. Karta na klienta:
 * útrata, konverze, cena za konverzi, ROAS, CTR, čerpání rozpočtu a co
 * svítí. Klient s upozorněním je nahoře.
 */
class AdsOverview extends Page
{
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
        return $this->currentPeriod()->label().', srovnání s '.$this->currentPeriod()->previous()->label();
    }

    public function currentPeriod(): Period
    {
        return Period::preset($this->period);
    }

    /** @return array<string, string> */
    public function periods(): array
    {
        return Period::PRESETS;
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, Period::PRESETS) ? $period : '7d';
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
        $ids = $clients->flatMap(fn (Client $client): array => $client->activeAdAccountIds())->all();
        $current = Stats::byAccount($ids, $period);
        $previous = Stats::byAccount($ids, $period->previous());
        $daily = Stats::dailyByAccount($ids, $period);
        $alerts = AdAlert::query()->live()->get()->groupBy('client_id');

        $cards = $clients->map(function (Client $client) use ($period, $current, $previous, $daily, $alerts): array {
            $accountIds = $client->activeAdAccountIds();
            $goal = $client->adGoal();
            $currency = $client->adCurrency();
            $m = $this->sum($current, $accountIds);
            $p = $this->sum($previous, $accountIds);
            $clientAlerts = $alerts->get($client->getKey(), collect());
            $worst = $clientAlerts->sortByDesc(fn (AdAlert $alert): int => $alert->severity->weight())->first()?->severity;
            $pace = BudgetPace::for($accountIds, $client->adSettings?->monthly_budget);

            $days = collect($period->dates())->map(function (string $date) use ($daily, $accountIds): Metrics {
                return collect($accountIds)->reduce(
                    fn (Metrics $carry, int $id): Metrics => $carry->plus($daily[$id][$date] ?? Metrics::empty()),
                    Metrics::empty(),
                );
            });

            return [
                'id' => $client->getKey(),
                'name' => $client->name,
                'url' => AdsClient::getUrl(['client' => $client->getKey(), 'period' => $this->period]),
                'goal' => $goal->conversionLabel(),
                'spend_raw' => $m->spend(),
                'has_data' => $m->hasData(),
                'metrics' => array_values(array_filter([
                    ['label' => 'Útrata', 'value' => Format::money($m->spend(), $currency), 'change' => Format::change(Metrics::change($m->spend(), $p->spend())), 'tone' => 'neutral'],
                    $this->metric($goal->conversionLabel(), $m->conversions($goal), $p->conversions($goal), fn ($v) => Format::count($v), 1),
                    $this->metric($goal->costLabel(), $m->costPerConversion($goal), $p->costPerConversion($goal), fn ($v) => Format::unitPrice($v, $currency), -1),
                    $goal->hasValue() ? $this->metric('ROAS', $m->roas(), $p->roas(), fn ($v) => Format::roas($v), 1) : null,
                    $this->metric('CTR', $m->ctr(), $p->ctr(), fn ($v) => Format::percent($v), 1),
                ])),
                'spark_spend' => $days->map(fn (Metrics $day): float => $day->spend())->all(),
                'spark_conversions' => $days->map(fn (Metrics $day): float => $day->conversions($goal))->all(),
                'budget' => $pace ? [
                    'label' => Format::money($pace->spent, $currency).' z '.Format::money($pace->budget, $currency),
                    'percent' => min(100, round($pace->spentPercent(), 1)),
                    // Kde by čerpání mělo být k dnešku. Značka na pruhu.
                    'expected' => min(100, round($pace->expected / $pace->budget * 100, 1)),
                    'pace' => $pace->pacePercent() !== null ? Format::number($pace->pacePercent()).' % plánu' : null,
                ] : null,
                'alerts' => $clientAlerts->count(),
                'worst' => $worst,
                'worst_weight' => $worst?->weight() ?? 0,
                'worst_color' => $worst?->getColor() ?? 'gray',
                'sync_error' => $client->adAccounts->where('is_active', true)->contains(fn ($account): bool => filled($account->last_sync_error)),
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
     * @param  callable(?float): string  $format
     * @return array{label: string, value: string, change: ?string, tone: string}
     */
    private function metric(string $label, ?float $current, ?float $previous, callable $format, int $direction): array
    {
        $change = Metrics::change($current, $previous);

        return [
            'label' => $label,
            'value' => $format($current),
            'change' => Format::change($change),
            'tone' => match (true) {
                $change === null || abs($change) < 3 => 'neutral',
                $change * $direction > 0 => 'good',
                default => 'bad',
            },
        ];
    }
}
