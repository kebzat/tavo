<?php

namespace App\Support\Ads;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
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

    /** @param  array<string, mixed>  $snapshot */
    public function __construct(private readonly array $snapshot)
    {
        $this->currency = $snapshot['currency'] ?? 'CZK';
        $this->goal = PrimaryGoal::tryFrom($snapshot['goal'] ?? '') ?? PrimaryGoal::Purchases;
        $this->period = Period::between($snapshot['period']['from'], $snapshot['period']['to']);
        $this->previousPeriod = Period::between($snapshot['previous']['from'], $snapshot['previous']['to']);
        $this->totals = Metrics::fromArray($snapshot['totals'] ?? []);
        $this->previous = Metrics::fromArray($snapshot['previous_totals'] ?? []);
    }

    public function hasData(): bool
    {
        return $this->totals->hasData();
    }

    /**
     * Dlaždice s hlavními čísly. Tón říká, jestli je změna dobrá: u ceny
     * za konverzi je růst špatně, u konverzí dobře, u útraty ani jedno.
     *
     * @return list<array{label: string, value: string, previous: string, change: ?string, tone: string, hint: ?string}>
     */
    public function tiles(): array
    {
        $m = $this->totals;
        $p = $this->previous;
        $goal = $this->goal;
        $targets = $this->snapshot['targets'] ?? [];

        $tiles = [
            $this->tile('spend', 'Útrata', $m->spend(), $p->spend(), fn ($v) => Format::money($v, $this->currency), 0),
            $this->tile('conversions', $goal->conversionLabel(), $m->conversions($goal), $p->conversions($goal), fn ($v) => Format::count($v), 1),
            $this->tile('cost', $goal->costLabel(), $m->costPerConversion($goal), $p->costPerConversion($goal), fn ($v) => Format::unitPrice($v, $this->currency), -1,
                filled($targets['target_cpa'] ?? null) && $goal !== PrimaryGoal::Traffic ? 'cíl '.Format::unitPrice((float) $targets['target_cpa'], $this->currency) : null),
        ];

        $tiles = match ($goal) {
            PrimaryGoal::Purchases => [
                ...$tiles,
                $this->tile('roas', 'ROAS', $m->roas(), $p->roas(), fn ($v) => Format::roas($v), 1,
                    filled($targets['target_roas'] ?? null) ? 'cíl '.Format::roas((float) $targets['target_roas']) : null),
                $this->tile('value', 'Hodnota nákupů', $m->get('purchase_value'), $p->get('purchase_value'), fn ($v) => Format::money($v, $this->currency), 1),
                $this->tile('clicks', 'Prokliky na web', $m->get('link_clicks'), $p->get('link_clicks'), fn ($v) => Format::number($v), 1),
            ],
            PrimaryGoal::Leads => [
                ...$tiles,
                $this->tile('conversion_rate', 'Konverzní poměr', $m->conversionRate($goal), $p->conversionRate($goal), fn ($v) => Format::percent($v), 1),
                $this->tile('clicks', 'Prokliky na web', $m->get('link_clicks'), $p->get('link_clicks'), fn ($v) => Format::number($v), 1),
                $this->tile('cpc', 'Cena za proklik', $m->cpc(), $p->cpc(), fn ($v) => Format::unitPrice($v, $this->currency), -1),
            ],
            PrimaryGoal::Traffic => [
                ...$tiles,
                $this->tile('impressions', 'Zobrazení', $m->get('impressions'), $p->get('impressions'), fn ($v) => Format::number($v), 1),
            ],
        };

        $tiles = [
            ...$tiles,
            $this->tile('ctr', 'CTR', $m->ctr(), $p->ctr(), fn ($v) => Format::percent($v), 1),
            $this->tile('cpm', 'CPM', $m->cpm(), $p->cpm(), fn ($v) => Format::unitPrice($v, $this->currency), -1),
        ];

        $chosen = $this->snapshot['dashboard']['tiles'] ?? null;

        return $chosen
            ? array_values(array_filter($tiles, fn (array $tile): bool => in_array($tile['key'], $chosen, true)))
            : $tiles;
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

    /** Všechny dlaždice, které jde u klienta zapnout, pro formulář nastavení. */
    public static function tileOptions(PrimaryGoal $goal): array
    {
        return array_filter([
            'spend' => 'Útrata',
            'conversions' => $goal->conversionLabel(),
            'cost' => $goal->costLabel(),
            'roas' => $goal->hasValue() ? 'ROAS' : null,
            'value' => $goal->hasValue() ? 'Hodnota nákupů' : null,
            'conversion_rate' => $goal === PrimaryGoal::Leads ? 'Konverzní poměr' : null,
            'clicks' => $goal === PrimaryGoal::Traffic ? null : 'Prokliky na web',
            'cpc' => $goal === PrimaryGoal::Leads ? 'Cena za proklik' : null,
            'impressions' => $goal === PrimaryGoal::Traffic ? 'Zobrazení' : null,
            'ctr' => 'CTR',
            'cpm' => 'CPM',
        ]);
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
     * Data pro <x-ads.chart>: sloupce útraty a čára konverzí po dnech.
     *
     * @return array{labels: list<string>, bars: array{label: string, values: list<float>, display: list<string>}, line: array{label: string, values: list<float>, display: list<string>}}
     */
    public function chart(): array
    {
        $days = collect($this->snapshot['daily'] ?? []);

        return [
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
     * Cesta od zobrazení k nákupu. U každého kroku podíl z předchozího.
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
                'Přidání do košíku' => $m->get('add_to_cart'),
                'Zahájení objednávky' => $m->get('checkouts'),
                'Nákupy' => $m->get('purchases'),
            ],
            PrimaryGoal::Leads => [
                'Zobrazení' => $m->get('impressions'),
                'Prokliky na web' => $m->get('link_clicks'),
                'Poptávky' => $m->get('leads'),
            ],
            PrimaryGoal::Traffic => [
                'Zobrazení' => $m->get('impressions'),
                'Prokliky na web' => $m->get('link_clicks'),
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
                    'name' => $row['name'],
                    'account' => $row['account'],
                    'spend' => Format::money($m->spend(), $this->currency),
                    'spend_change' => Format::change(Metrics::change($m->spend(), $p->spend())),
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
        $change = Metrics::change($current, $previous);

        $tone = match (true) {
            $change === null, $direction === 0, abs($change) < self::NOISE_PCT => 'neutral',
            $change * $direction > 0 => 'good',
            default => 'bad',
        };

        return [
            'key' => $key,
            'label' => $label,
            'value' => $format($current),
            'previous' => $format($previous),
            'change' => Format::change($change),
            'tone' => $tone,
            'hint' => $hint,
        ];
    }
}
