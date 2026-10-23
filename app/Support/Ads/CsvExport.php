<?php

namespace App\Support\Ads;

use App\Models\Ads\AdDailyStat;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export čísel do CSV pro další analýzu (Excel, Google Tabulky). Čte jen
 * z naší databáze, na Metu nesahá.
 *
 * Středník a desetinná čárka, ať český Excel soubor otevře rovnou do
 * sloupců. Poměry (CTR, CPC, cena za konverzi, ROAS) jsou u každého řádku
 * spočítané ze součtů toho řádku. Denní dosah se přes dny ani kampaně
 * sčítat nesmí, sloupec to říká v názvu.
 */
final class CsvExport
{
    public const LAYOUTS = [
        'days_campaigns' => 'Každý den a kampaň zvlášť',
        'campaigns' => 'Součty po kampaních',
        'days' => 'Součty po dnech',
    ];

    public function __construct(
        private readonly AdsScope $scope,
        private readonly Period $period,
        private readonly string $layout = 'days_campaigns',
    ) {}

    public function filename(): string
    {
        return Str::slug($this->scope->client->name.' '.($this->scope->isGroup() ? $this->scope->label() : '').' '.$this->period->from->toDateString().' '.$this->period->to->toDateString()).'.csv';
    }

    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            // BOM, ať Excel pozná UTF-8 a háčky.
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($this->rows() as $row) {
                fputcsv($out, $row, ';', '"', '');
            }

            fclose($out);
        }, $this->filename(), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<list<string>> První řádek je hlavička. */
    public function rows(): array
    {
        $goal = $this->scope->goal();
        $head = match ($this->layout) {
            'campaigns' => ['Účet', 'Kampaň', 'Skupina'],
            'days' => ['Datum'],
            default => ['Datum', 'Účet', 'Kampaň', 'Skupina'],
        };

        $rows = [[
            ...$head,
            'Útrata', 'Zobrazení', 'Dosah za den (nesčítat)', 'Kliknutí celkem', 'Prokliky na web',
            'Zobrazení cílové stránky', 'Přidání do košíku', 'Zahájené objednávky', 'Nákupy', 'Hodnota nákupů',
            'Poptávky celkem', 'Poptávky z formuláře na Metě', 'Poptávky z webu', 'Interakce',
            'CTR odkazu %', 'CPC', 'CPM', $goal->costLabel(), 'ROAS',
        ]];

        $stats = Stats::rows($this->scope->accountIds(), $this->period, $this->scope->campaignIds());

        $grouped = match ($this->layout) {
            'campaigns' => $stats->groupBy('ad_campaign_id'),
            'days' => $stats->groupBy(fn (AdDailyStat $stat): string => $stat->date->toDateString()),
            default => $stats->map(fn (AdDailyStat $stat) => collect([$stat])),
        };

        foreach ($grouped as $group) {
            $first = $group->first();
            $metrics = $group->reduce(fn (Metrics $carry, AdDailyStat $stat): Metrics => $carry->plus(Metrics::fromArray($stat->getAttributes())), Metrics::empty());

            $label = match ($this->layout) {
                'campaigns' => [$first->account?->name ?? '', $first->campaign?->name ?? '', $first->campaign?->group?->name ?? ''],
                'days' => [$first->date->toDateString()],
                default => [$first->date->toDateString(), $first->account?->name ?? '', $first->campaign?->name ?? '', $first->campaign?->group?->name ?? ''],
            };

            $rows[] = [
                ...$label,
                self::number($metrics->spend(), 2),
                self::number($metrics->get('impressions')),
                // Dosah za den dává smysl jen u řádku jednoho dne a kampaně.
                $this->layout === 'days_campaigns' ? self::number($metrics->get('reach')) : '',
                self::number($metrics->get('clicks')),
                self::number($metrics->get('link_clicks')),
                self::number($metrics->get('landing_page_views')),
                self::number($metrics->get('add_to_cart')),
                self::number($metrics->get('checkouts')),
                self::number($metrics->get('purchases')),
                self::number($metrics->get('purchase_value'), 2),
                self::number($metrics->get('leads')),
                self::number($metrics->get('form_leads')),
                self::number($metrics->get('web_leads')),
                self::number($metrics->get('engagements')),
                self::number($metrics->ctr(), 2),
                self::number($metrics->cpc(), 2),
                self::number($metrics->cpm(), 2),
                self::number($metrics->costPerConversion($goal), 2),
                self::number($metrics->roas(), 2),
            ];
        }

        return $rows;
    }

    private static function number(?float $value, int $decimals = 0): string
    {
        return $value === null ? '' : number_format($value, $decimals, ',', '');
    }
}
