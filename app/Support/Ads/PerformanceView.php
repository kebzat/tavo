<?php

namespace App\Support\Ads;

use App\Enums\Ads\AdChangeType;
use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdCampaign;
use Carbon\CarbonImmutable;

/**
 * Z pole ClientPerformance udělá dlaždice, graf, funnel a tabulku kampaní
 * s už naformátovanými čísly. Šablona je jen vypíše.
 */
final class PerformanceView
{
    public readonly string $currency;

    public readonly PrimaryGoal $goal;

    public readonly Period $period;

    public readonly Period $previousPeriod;

    public readonly Metrics $totals;

    public readonly Metrics $previous;

    /** Změna pod touhle hranicí se nebarví, je to běžné kolísání. */
    private const NOISE_PCT = 3;

    /**
     * Dá se období se srovnáním porovnat? Ne, když chybí data na konci období
     * (výpadek stahování) nebo když srovnávací období začíná dřív, než máme
     * čísla. Procenta by pak ukazovala propad, který neexistuje.
     */
    public readonly bool $comparable;

    /** @param  array<string, mixed>  $snapshot */
    public function __construct(private readonly array $snapshot)
    {
        $this->currency = $snapshot['currency'] ?? 'CZK';
        $this->goal = PrimaryGoal::tryFrom($snapshot['goal'] ?? '') ?? PrimaryGoal::Purchases;
        $this->period = Period::between($snapshot['period']['from'], $snapshot['period']['to']);
        $this->previousPeriod = Period::between($snapshot['previous']['from'], $snapshot['previous']['to']);
        // Přesný dosah za období mají jen novější snímky a jen přednastavená období.
        $this->totals = Metrics::fromArray($snapshot['totals'] ?? [])->withPeriodReach($snapshot['period_reach']['current'] ?? null);
        $this->previous = Metrics::fromArray($snapshot['previous_totals'] ?? [])->withPeriodReach($snapshot['period_reach']['previous'] ?? null);
        $since = $snapshot['data']['since'] ?? null;
        $this->comparable = ($snapshot['data']['covers_period'] ?? true)
            && ($since === null || $since <= $this->previousPeriod->from->toDateString());
    }

    /** Skupina kampaní, za kterou čísla jsou. Null = celý účet. */
    public function scopeLabel(): ?string
    {
        return ($this->snapshot['scope']['is_group'] ?? false) ? $this->snapshot['scope']['label'] : null;
    }

    /**
     * Proč čísla za období nejsou celá nebo srovnatelná. Null, když je
     * všechno v pořádku. Ukazuje se nad dlaždicemi i v reportu.
     */
    public function dataNote(): ?string
    {
        $data = $this->snapshot['data'] ?? null;

        if ($data === null) {
            return null;
        }

        $through = filled($data['complete_through'] ?? null) ? CarbonImmutable::parse($data['complete_through']) : null;
        $since = $data['since'] ?? null;

        return match (true) {
            $through === null && ($data['state'] ?? null) === DataStatus::WAITING => 'Účet se ještě nestahoval, čísla zatím nemáme.',
            ! ($data['covers_period'] ?? true) => 'Data máme jen do '.($through?->format('j. n. Y') ?? '–').', za '.$data['missing_days'].' '.self::days((int) $data['missing_days']).' na konci období chybí. Součty jsou neúplné a srovnání neukazujeme, propad by nebyl skutečný.',
            $since !== null && $since > $this->previousPeriod->from->toDateString() => 'Srovnávací období začíná '.$this->previousPeriod->from->format('j. n. Y').', čísla máme až od '.CarbonImmutable::parse($since)->format('j. n. Y').'. Srovnání neukazujeme.',
            default => null,
        };
    }

    /**
     * Hlavní výsledek proti cíli: cena za konverzi a ROAS proti cílovým
     * hodnotám. Prázdné, když cíle nejsou vyplněné.
     *
     * @return list<array{label: string, value: string, target: string, ok: ?bool}>
     */
    public function targets(): array
    {
        $targets = $this->snapshot['targets'] ?? [];
        $rows = [];

        if ($this->goal->isPerformance() && filled($targets['target_cpa'] ?? null)) {
            $cost = $this->totals->costPerConversion($this->goal);
            $rows[] = [
                'label' => $this->goal->costLabel(),
                'value' => Format::unitPrice($cost, $this->currency),
                'target' => Format::unitPrice((float) $targets['target_cpa'], $this->currency),
                'ok' => $cost === null ? null : $cost <= (float) $targets['target_cpa'],
            ];
        }

        if ($this->goal->hasValue() && filled($targets['target_roas'] ?? null)) {
            $roas = $this->totals->roas();
            $rows[] = [
                'label' => 'ROAS',
                'value' => Format::roas($roas),
                'target' => Format::roas((float) $targets['target_roas']),
                'ok' => $roas === null ? null : $roas >= (float) $targets['target_roas'],
            ];
        }

        return $rows;
    }

    /**
     * Zásahy v období, formátované do seznamu a do grafu.
     *
     * @return list<array{date: string, label: string, description: string, expected: ?string, scope: ?string}>
     */
    public function changes(): array
    {
        return array_map(fn (array $change): array => [
            'date' => CarbonImmutable::parse($change['date'])->format('j. n.'),
            'label' => $change['label'],
            'description' => $change['description'],
            'expected' => $change['expected'] ?? null,
            'scope' => $change['scope'] ?? null,
        ], $this->snapshot['changes'] ?? []);
    }

    /** @return list<array{date: string, label: string, title: string}> */
    public function events(): array
    {
        return array_map(fn (array $event): array => [
            'date' => CarbonImmutable::parse($event['date'])->format('j. n.'),
            'label' => $event['label'],
            'title' => $event['title'],
        ], $this->snapshot['events'] ?? []);
    }

    private static function days(int $count): string
    {
        return match (true) {
            $count === 1 => 'den',
            $count >= 2 && $count <= 4 => 'dny',
            default => 'dní',
        };
    }

    public function hasData(): bool
    {
        return $this->totals->hasData();
    }

    /**
     * Dlaždice s hlavními čísly. Které to jsou, říká výběr v „Co ukazovat“,
     * bez výběru výchozí sada podle cíle (MetricCatalog). Tón říká, jestli je
     * změna dobrá: u ceny za konverzi je růst špatně, u konverzí dobře,
     * u útraty ani jedno.
     *
     * @return list<array{key: string, label: string, value: string, previous: string, change: ?string, tone: string, hint: ?string}>
     */
    public function tiles(): array
    {
        $keys = MetricCatalog::tiles($this->snapshot['dashboard']['tiles'] ?? null, $this->goal);

        return array_map(fn (string $key): array => $this->tile(
            $key,
            MetricCatalog::label($key, $this->goal),
            MetricCatalog::value($key, $this->totals, $this->goal),
            MetricCatalog::value($key, $this->previous, $this->goal),
            fn (?float $value): string => MetricCatalog::format($key, $value, $this->currency),
            MetricCatalog::direction($key),
            $this->hint($key),
        ), $keys);
    }

    /** Poznámka pod dlaždicí: cíl, nebo proč číslo chybí. */
    private function hint(string $key): ?string
    {
        $targets = $this->snapshot['targets'] ?? [];

        return match (true) {
            $key === 'cost_per_conversion' && filled($targets['target_cpa'] ?? null) && $this->goal->isPerformance() => 'cíl '.Format::unitPrice((float) $targets['target_cpa'], $this->currency),
            $key === 'roas' && filled($targets['target_roas'] ?? null) => 'cíl '.Format::roas((float) $targets['target_roas']),
            MetricCatalog::needsPeriodReach($key) && ! $this->totals->hasPeriodReach() => ($this->snapshot['scope']['is_group'] ?? false)
                ? 'u skupiny se stahuje ráno, jen přednastavená období'
                : PeriodReach::UNAVAILABLE_HINT,
            default => null,
        };
    }

    /**
     * Má se sekce ukázat? Bez výběru v nastavení klienta se ukáže všechno.
     * Sekce: chart, funnel, campaigns, accounts, analytics.
     */
    public function shows(string $section): bool
    {
        $chosen = $this->snapshot['dashboard']['sections'] ?? null;

        return $chosen === null || in_array($section, $chosen, true);
    }

    public const SECTIONS = [
        'chart' => 'Graf po dnech',
        'funnel' => 'Cesta ke konverzi',
        'campaigns' => 'Kampaně',
        'accounts' => 'Rozpad podle účtů',
        'analytics' => 'Co naměřil web (GA4)',
    ];

    /**
     * Rozpad podle reklamních účtů (Meta, Google Ads). Prázdné u klienta s jedním účtem.
     *
     * @return list<array{name: string, platform: string, spend: string, share: float, share_label: string, conversions: string, cost: string, roas: ?string}>
     */
    public function byAccount(): array
    {
        $rows = $this->snapshot['by_account'] ?? [];
        $total = $this->totals->spend();

        return array_map(function (array $row) use ($total): array {
            $m = Metrics::fromArray($row['totals']);

            return [
                'name' => $row['name'],
                'platform' => AdPlatform::tryFrom($row['platform'])?->shortLabel() ?? $row['platform'],
                'spend' => Format::money($m->spend(), $this->currency),
                'share' => $share = $total > 0 ? round($m->spend() / $total * 100, 1) : 0,
                'share_label' => Format::percent($share, 0),
                'conversions' => Format::count($m->conversions($this->goal)),
                'cost' => Format::unitPrice($m->costPerConversion($this->goal), $this->currency),
                'roas' => $this->goal->hasValue() ? Format::roas($m->roas()) : null,
            ];
        }, $rows);
    }

    /**
     * Co naměřila GA4, vedle toho, co si připsaly reklamy. Reklamní systémy
     * si konverzi přiřazují každý sám a často dvakrát, GA4 počítá nákup
     * jednou. Rozdíl je normální, podstatné je, jak se vyvíjí.
     *
     * @return array{tiles: list<array<string, mixed>>, comparison: ?string, channels: list<array<string, string>>}|null
     */
    public function analytics(): ?array
    {
        $data = $this->snapshot['analytics'] ?? null;

        if ($data === null) {
            return null;
        }

        $t = AnalyticsStats::normalize($data['totals'] ?? []);
        $p = AnalyticsStats::normalize($data['previous_totals'] ?? []);
        $rate = fn (array $sums): ?float => $sums['sessions'] > 0 ? $sums['purchases'] / $sums['sessions'] * 100 : null;
        $isShop = $this->goal === PrimaryGoal::Purchases;

        $tiles = array_values(array_filter([
            $this->tile('sessions', 'Návštěvy webu', $t['sessions'], $p['sessions'], fn ($v) => Format::number($v), 1),
            $isShop
                ? $this->tile('ga4_purchases', 'Nákupy na webu', $t['purchases'], $p['purchases'], fn ($v) => Format::count($v), 1)
                : $this->tile('ga4_key_events', 'Klíčové události', $t['key_events'], $p['key_events'], fn ($v) => Format::count($v), 1),
            $isShop ? $this->tile('ga4_revenue', 'Tržby na webu', $t['revenue'], $p['revenue'], fn ($v) => Format::money($v, $this->currency), 1) : null,
            $isShop ? $this->tile('ga4_rate', 'Konverzní poměr webu', $rate($t), $rate($p), fn ($v) => Format::percent($v), 1) : null,
        ]));

        $comparison = $isShop && $t['purchases'] > 0 && $this->totals->get('purchases') > 0
            ? 'Reklamy si za období připsaly '.Format::count($this->totals->get('purchases')).' nákupů za '.Format::money($this->totals->get('purchase_value'), $this->currency)
                .'. GA4 naměřila na celém webu '.Format::count($t['purchases']).' nákupů za '.Format::money($t['revenue'], $this->currency)
                .', ze všech zdrojů dohromady. Podíl reklam na tržbách webu podle platforem: '
                .Format::percent(min(100, $this->totals->get('purchase_value') / max(1, $t['revenue']) * 100), 0).'.'
            : null;

        $channels = array_map(fn (array $row): array => [
            'channel' => AnalyticsStats::CHANNELS[$row['channel']] ?? $row['channel'],
            'sessions' => Format::number((float) $row['sessions']),
            'share' => $t['sessions'] > 0 ? Format::percent($row['sessions'] / $t['sessions'] * 100, 0) : '–',
            'purchases' => Format::count((float) ($isShop ? $row['purchases'] : $row['key_events'])),
            'revenue' => $isShop ? Format::money((float) $row['revenue'], $this->currency) : '',
        ], $data['channels'] ?? []);

        return ['tiles' => $tiles, 'comparison' => $comparison, 'channels' => $channels];
    }

    /**
     * Data pro <x-ads.chart>: sloupce útraty a čára konverzí po dnech. Den
     * bez stažených dat má stav „missing“ (nekreslí se jako nula), poslední
     * dny „settling“ (Meta je ještě dopočítává). Značky jsou zásahy a termíny.
     *
     * @return array{labels: list<string>, bars: array{label: string, values: list<float>, display: list<string>}, line: array{label: string, values: list<float>, display: list<string>}, states: list<string>, markers: list<array{index: int, kind: string, mark: string, text: string}>}
     */
    public function chart(): array
    {
        $days = collect($this->snapshot['daily'] ?? []);
        $index = $days->pluck('date')->flip();
        $markers = [];

        foreach ($this->snapshot['changes'] ?? [] as $change) {
            if (isset($index[$change['date']])) {
                $type = AdChangeType::tryFrom($change['type']);
                $markers[] = ['index' => $index[$change['date']], 'kind' => 'change', 'mark' => $type?->mark() ?? '•', 'text' => $change['label'].': '.$change['description']];
            }
        }

        foreach ($this->snapshot['events'] ?? [] as $event) {
            if (isset($index[$event['date']])) {
                $markers[] = ['index' => $index[$event['date']], 'kind' => 'event', 'mark' => '◆', 'text' => $event['label'].': '.$event['title']];
            }
        }

        return [
            'states' => $days->map(fn (array $day): string => $day['state'] ?? 'ok')->all(),
            'markers' => $markers,
            'labels' => $days->map(fn (array $day): string => CarbonImmutable::parse($day['date'])->format('j. n.'))->all(),
            'bars' => [
                'label' => 'Útrata',
                'values' => $days->map(fn (array $day): float => (float) $day['spend'])->all(),
                'display' => $days->map(fn (array $day): string => Format::money((float) $day['spend'], $this->currency))->all(),
            ],
            'line' => [
                'label' => $this->goal->conversionLabel(),
                'values' => $days->map(fn (array $day): float => Metrics::fromArray($day)->conversions($this->goal))->all(),
                'display' => $days->map(fn (array $day): string => Format::count(Metrics::fromArray($day)->conversions($this->goal)))->all(),
            ],
        ];
    }

    /**
     * Události po cestě od zobrazení ke konverzi. U každého kroku poměr
     * k předchozímu. Meta počítá události, ne lidi: poměr neříká, že titíž
     * lidé prošli všemi kroky (nákup může přijít bez zaznamenaného košíku,
     * jeden člověk může košík otevřít třikrát). Proto „poměr“, ne „prošlo“.
     * Kroky bez dat (e-shop bez událostí košíku) se vynechají.
     *
     * @return list<array{label: string, value: string, rate: ?string, width: float}>
     */
    public function funnel(): array
    {
        $m = $this->totals;

        $steps = match ($this->goal) {
            PrimaryGoal::Purchases => [
                'Zobrazení' => $m->get('impressions'),
                'Prokliky na web' => $m->get('link_clicks'),
                'Zobrazení cílové stránky' => $m->get('landing_page_views'),
                'Přidání do košíku' => $m->get('add_to_cart'),
                'Zahájení objednávky' => $m->get('checkouts'),
                'Nákupy' => $m->get('purchases'),
            ],
            PrimaryGoal::Leads, PrimaryGoal::Recruitment => [
                'Zobrazení' => $m->get('impressions'),
                'Prokliky na web' => $m->get('link_clicks'),
                'Zobrazení cílové stránky' => $m->get('landing_page_views'),
                $this->goal->conversionLabel() => $m->get('leads'),
            ],
            PrimaryGoal::Traffic => [
                'Zobrazení' => $m->get('impressions'),
                'Prokliky na web' => $m->get('link_clicks'),
            ],
            PrimaryGoal::Awareness => [
                'Zobrazení' => $m->get('impressions'),
                'Interakce' => $m->get('engagements'),
            ],
        };

        $steps = array_filter($steps, fn (float $value, string $label): bool => $value > 0 || $label === 'Zobrazení', ARRAY_FILTER_USE_BOTH);

        if (count($steps) < 2) {
            return [];
        }

        $rows = [];
        $previous = null;
        $first = reset($steps) ?: 1;

        foreach ($steps as $label => $value) {
            $rows[] = [
                'label' => $label,
                'value' => Format::count($value),
                'rate' => $previous ? Format::percent($value / $previous * 100, 1) : null,
                // Šířka pruhu v logaritmu, jinak by nákupy proti zobrazením byly neviditelné.
                'width' => $value > 0 ? max(4, round(log10($value + 1) / log10($first + 1) * 100, 1)) : 0,
            ];
            $previous = $value;
        }

        return $rows;
    }

    /**
     * Kampaně s hlavními čísly.
     *
     * @return list<array{name: string, account: string, spend: string, spend_change: ?string, conversions: string, cost: string, roas: ?string, ctr: string, clicks: string}>
     */
    public function campaigns(): array
    {
        return collect($this->snapshot['campaigns'] ?? [])
            ->map(function (array $row): array {
                $m = Metrics::fromArray($row['totals']);
                $p = Metrics::fromArray($row['previous_totals'] ?? []);

                return [
                    'id' => $row['id'] ?? null,
                    'name' => $row['name'],
                    'account' => $row['account'],
                    'group' => $row['group'] ?? null,
                    'status' => AdCampaign::label($row['status'] ?? null),
                    'running' => in_array($row['status'] ?? null, AdCampaign::RUNNING_STATUSES, true),
                    'spend' => Format::money($m->spend(), $this->currency),
                    'spend_change' => $this->comparable ? Format::change(Metrics::change($m->spend(), $p->spend())) : null,
                    'conversions' => Format::count($m->conversions($this->goal)),
                    'cost' => Format::unitPrice($m->costPerConversion($this->goal), $this->currency),
                    'roas' => $this->goal->hasValue() ? Format::roas($m->roas()) : null,
                    'ctr' => Format::percent($m->ctr()),
                    'clicks' => Format::number($m->get('link_clicks')),
                ];
            })
            ->all();
    }

    /** @return list<string> Názvy účtů, ze kterých čísla jsou. */
    public function accounts(): array
    {
        return array_column($this->snapshot['accounts'] ?? [], 'name');
    }

    /**
     * @param  callable(?float): string  $format
     * @param  int  $direction  1 = víc je lépe, -1 = míň je lépe, 0 = neutrální
     * @return array{label: string, value: string, previous: string, change: ?string, tone: string, hint: ?string}
     */
    private function tile(string $key, string $label, ?float $current, ?float $previous, callable $format, int $direction, ?string $hint = null): array
    {
        $change = $this->comparable ? Metrics::change($current, $previous) : null;

        $tone = match (true) {
            $change === null, $direction === 0, abs($change) < self::NOISE_PCT => 'neutral',
            $change * $direction > 0 => 'good',
            default => 'bad',
        };

        return [
            'key' => $key,
            'label' => $label,
            'description' => MetricCatalog::exists($key) ? MetricCatalog::description($key, $this->goal) : null,
            'value' => $format($current),
            'previous' => $this->comparable ? $format($previous) : '–',
            'change' => Format::change($change),
            'tone' => $tone,
            'hint' => $hint,
        ];
    }
}
