<?php

namespace App\Support\Marketing;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdChange;
use App\Models\Ads\AdEvent;
use App\Models\Ads\AdReport;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\Marketing\CreativeTest;
use App\Models\Marketing\LandingCheck;
use App\Models\Marketing\LeadFeedback;
use App\Support\Ads\AdsScope;
use App\Support\Ads\AnalyticsStats;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\CampaignGroups;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\DataStatus;
use App\Support\Ads\Format;
use App\Support\Ads\MetricCatalog;
use App\Support\Ads\Metrics;
use App\Support\Ads\Period;
use App\Support\Ads\Stats;
use Carbon\CarbonImmutable;

/**
 * Všechno, co agent potřebuje o klientovi vědět, v jednom JSONu
 * (GET /nastroje/api/agent/klienti/{slug}).
 *
 * Čísla počítá systém, ne agent: Pavel se bojí chyb ve výpočtech a
 * domyšlených dat. Každý blok proto nese zdroj, období a čerstvost
 * a chybějící data jsou null s vysvětlením, nikdy nula. Agent má čísla
 * jen vykládat a citovat.
 */
final class ClientContext
{
    /** Kolik kampaní stačí na přehled, zbytek je šum. */
    private const TOP_CAMPAIGNS = 8;

    public function __construct(private readonly Client $client, private readonly CarbonImmutable $today) {}

    public static function for(Client $client, ?CarbonImmutable $today = null): self
    {
        $client->loadMissing(['adAccounts', 'adSettings']);

        return new self($client, $today ?? CarbonImmutable::today());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'klient' => [
                'nazev' => $this->client->name,
                'slug' => $this->client->slug,
                'web' => $this->client->website_url,
                'projekt_agenta' => $this->client->agent_project,
            ],
            'karta' => $this->card(),
            'reklamy' => $this->ads(),
            'web_ga4' => $this->analytics(),
            'cilove_stranky' => $this->landingPages(),
            'upozorneni' => $this->alerts(),
            'terminy' => $this->events(),
            'zasahy' => $this->changes(),
            'kvalita_leadu' => $this->leadFeedback(),
            'kreativni_testy' => $this->creativeTests(),
            'ukoly' => $this->tasks(),
            'reporty_k_doplneni' => $this->reportDrafts(),
            'vcerejsi_prehled' => $this->client->briefings()->where('date', '<', $this->today->toDateString())->first()?->only(['date', 'body']),
            'pravidla_vykladu' => [
                'Čísla reklam jsou připsaná reklamním systémem podle jeho atribuce, ne objednávky e-shopu. Meta a Google si konverze připisují samy a často dvakrát, nesčítej je jako celkové prodeje.',
                'GA4 je měření webu ze všech zdrojů. Se součtem z reklam se nesčítá, jen porovnává.',
                'null znamená „nemáme data“ (neuvádět jako nulu), důvod je v poli poznamka.',
                'Změny v procentech jsou spočítané systémem, nepřepočítávej je.',
                'Když reklamy.stav_dat.stav není „ok“, data nejsou kompletní: nekomentuj propady ani čerpání, napiš, do kdy data máme.',
                'Skupiny kampaní mají vlastní cíl a rozpočet. Hodnoť každou skupinu proti jejímu cíli, ne celý účet.',
                'Zásahy (zasahy) jsou fakta s datem. Že změna čísel souvisí se zásahem, je hypotéza: piš „možná souvisí s…“, ne „protože“. Když příčinu nevíš, napiš to.',
                'Pokles CTR sám o sobě není problém, když skupina plní cíl ceny nebo ROAS.',
                'Poměry v cestě k nákupu jsou poměry událostí, ne lidí. Netvrď, že X % lidí prošlo dalším krokem.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function card(): array
    {
        return [
            'pole' => $this->client->profileFields() ?: null,
            'kontakty' => $this->client->contacts ?: null,
            'rozhodnuti' => collect($this->client->decisions ?? [])
                ->map(fn (array $decision): array => $decision + ['plati' => blank($decision['valid_until'] ?? null) || $decision['valid_until'] >= $this->today->toDateString()])
                ->values()->all() ?: null,
            'poznamka' => $this->client->profileFields() === [] ? 'Karta klienta je prázdná. Nic si nedomýšlej, uveď, co chybí.' : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function ads(): ?array
    {
        $ids = $this->client->activeAdAccountIds();

        if ($ids === []) {
            return null;
        }

        $goal = $this->client->adGoal();
        $currency = $this->client->adCurrency();
        $settings = $this->client->adSettings;
        $week = Period::preset('7d', $this->today);
        $previousWeek = $week->previous();
        $status = DataStatus::for($this->client->adAccounts, $this->today);
        $pace = BudgetPace::for($ids, $settings?->monthly_budget, $this->today, $status->completeThrough);
        $comparable = $status->covers($week);

        return [
            'stav_dat' => [
                'stav' => $status->state,
                'kompletni_do' => $status->completeThrough?->toDateString(),
                'chyba' => $status->error,
                'ukazkova_data' => $status->demo,
                'popis' => $status->sentence(),
            ],
            'cil' => $goal->getLabel(),
            'mena' => $currency,
            'mesicni_rozpocet' => $settings?->monthly_budget,
            'cilova_cena_za_konverzi' => $goal->isPerformance() ? $settings?->target_cpa : null,
            'cilovy_roas' => $goal->hasValue() ? $settings?->target_roas : null,
            'ucty' => $this->client->adAccounts->where('is_active', true)->map(fn (AdAccount $account): array => [
                'platforma' => $account->platform->getLabel(),
                'nazev' => $account->name,
                'stav' => $account->status,
                'stazeno' => $account->last_synced_at?->toIso8601String(),
                'chyba_stahovani' => $account->last_sync_error,
                'problemove_reklamy' => $account->issues ?: null,
            ])->values()->all(),
            'vcera' => $this->metrics(Stats::totals($ids, Period::between($this->today->subDay(), $this->today->subDay())), $goal, $currency),
            'poslednich_7_dni' => [
                'obdobi' => $week->label(),
                'cisla' => $this->metrics(Stats::totals($ids, $week), $goal, $currency, $comparable ? Stats::totals($ids, $previousWeek) : null),
                'srovnani_s' => $comparable ? $previousWeek->label() : null,
                'poznamka' => $comparable ? null : 'Data za období nejsou kompletní, srovnání chybí záměrně.',
            ],
            'poslednich_30_dni' => $this->metrics(Stats::totals($ids, Period::preset('30d', $this->today)), $goal, $currency),
            'cerpani_rozpoctu' => $pace ? [
                'utraceno' => Format::money($pace->spent, $currency),
                'podle_planu_melo_byt' => Format::money($pace->expected, $currency),
                'tempo_procent_planu' => $pace->pacePercent() === null ? null : round($pace->pacePercent()),
                'odhad_za_mesic' => Format::money($pace->projected(), $currency),
                'poznamka' => $pace->note(),
            ] : ['poznamka' => 'Měsíční rozpočet není vyplněný.'],
            'skupiny_kampani' => $this->groups($week, $previousWeek, $comparable, $status),
            'kampane_cekaji_na_zarazeni' => CampaignGroups::waiting($this->client)->pluck('name')->all() ?: null,
            'kampane_7_dni' => Stats::campaigns($ids, $week)->take(self::TOP_CAMPAIGNS)->map(fn (array $row): array => [
                'kampan' => $row['name'],
                'cisla' => $this->metrics($row['metrics'], $goal, $currency),
            ])->values()->all(),
            'zdroj' => 'Reklamní systémy (Meta, Google Ads), čísla z databáze TAVEO po ranním stažení. Atribuce podle nastavení účtu.',
        ];
    }

    /**
     * Čísla podle cíle klienta: hodnota, formát a změna proti srovnání.
     *
     * @return array<string, array{hodnota: ?float, text: string, zmena: ?string}>
     */
    private function metrics(Metrics $metrics, PrimaryGoal $goal, string $currency, ?Metrics $compare = null): array
    {
        $keys = array_values(array_unique([...MetricCatalog::defaults($goal), 'impressions', 'link_clicks', 'cpm', 'ctr']));
        $rows = [];

        foreach ($keys as $key) {
            if (! MetricCatalog::appliesTo($key, $goal) || MetricCatalog::needsPeriodReach($key)) {
                continue;
            }

            $value = MetricCatalog::value($key, $metrics, $goal);
            $previous = $compare ? MetricCatalog::value($key, $compare, $goal) : null;

            $rows[MetricCatalog::label($key, $goal)] = [
                'hodnota' => $value === null ? null : round($value, 4),
                'text' => MetricCatalog::format($key, $value, $currency),
                'zmena' => $value !== null && $previous ? Format::change(($value - $previous) / $previous * 100) : null,
            ];
        }

        return $rows;
    }

    /**
     * Skupiny kampaní s vlastním cílem a rozpočtem, čísla za 7 dní.
     *
     * @return list<array<string, mixed>>|null
     */
    private function groups(Period $week, Period $previousWeek, bool $comparable, DataStatus $status): ?array
    {
        $groups = $this->client->adCampaignGroups()->get();

        if ($groups->isEmpty()) {
            return null;
        }

        $currency = $this->client->adCurrency();

        return $groups->map(function (AdCampaignGroup $group) use ($week, $previousWeek, $comparable, $status, $currency): array {
            $scope = new AdsScope($this->client, $group);
            $pace = $scope->budgetPace($status);

            return [
                'skupina' => $group->name,
                'typ' => $group->goal->templateLabel(),
                'cil' => $group->goal->getLabel(),
                'mesicni_rozpocet' => $group->monthly_budget,
                'cilova_cena_za_konverzi' => $group->goal->isPerformance() ? $group->target_cpa : null,
                'cilovy_roas' => $group->goal->hasValue() ? $group->target_roas : null,
                'kampane' => $group->campaigns()->pluck('name')->all(),
                'poslednich_7_dni' => $this->metrics($scope->totals($week), $group->goal, $currency, $comparable ? $scope->totals($previousWeek) : null),
                'tempo_rozpoctu_procent' => $pace?->pacePercent() === null ? null : round($pace->pacePercent()),
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> Nevyřešené termíny: po termínu a na 30 dní dopředu. */
    private function events(): array
    {
        return $this->client->adEvents()->open()->with(['group', 'campaign'])
            ->whereDate('due_on', '<=', $this->today->addDays(30))
            ->orderBy('due_on')
            ->get()
            ->map(fn (AdEvent $event): array => [
                'kdy' => $event->due_on->toDateString(),
                'za_kolik' => $event->whenLabel($this->today),
                'druh' => $event->type->getLabel(),
                'co' => $event->title,
                'k_cemu' => $event->scopeLabel(),
                'co_udelat' => $event->type->todo(),
                'po_terminu' => $event->isOverdue($this->today),
            ])->all();
    }

    /** @return list<array<string, mixed>> Zásahy za posledních 45 dní. */
    private function changes(): array
    {
        return $this->client->adChanges()->with(['group', 'campaign'])
            ->whereDate('changed_on', '>=', $this->today->subDays(45))
            ->latest('changed_on')
            ->get()
            ->map(fn (AdChange $change): array => [
                'kdy' => $change->changed_on->toDateString(),
                'co' => $change->type->getLabel(),
                'popis' => $change->description,
                'cekame' => $change->expected,
                'k_cemu' => $change->scopeLabel(),
            ])->all();
    }

    /** @return array<string, mixed>|null */
    private function analytics(): ?array
    {
        $ids = $this->client->activeAnalyticsAccountIds();

        if ($ids === []) {
            return null;
        }

        $week = Period::preset('7d', $this->today);

        return [
            'obdobi' => $week->label(),
            'soucty' => AnalyticsStats::totals($ids, $week),
            'kanaly' => array_slice(AnalyticsStats::channels($ids, $week), 0, 6),
            'zdroj' => 'Google Analytics 4 (web, všechny zdroje návštěvnosti)',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function landingPages(): array
    {
        return $this->client->landingChecks()->get()->map(fn (LandingCheck $check): array => [
            'url' => $check->url,
            'funguje' => $check->ok,
            'meta_pixel' => $check->has_meta_pixel,
            'chyba' => $check->error,
            'zkontrolovano' => $check->checked_at->toIso8601String(),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function alerts(): array
    {
        return $this->client->adAlerts()->live()->with('group')->worstFirst()->get()->map(fn (AdAlert $alert): array => [
            'zavaznost' => $alert->severity->getLabel(),
            'kategorie' => $alert->category === AdAlert::DATA ? 'data a měření' : 'výkon proti cíli',
            'skupina' => $alert->group?->name,
            'nazev' => $alert->title,
            'co_se_zmenilo' => $alert->facts,
            'z_dat' => $alert->basis,
            'doporuceni' => $alert->recommendation,
            'stav' => $alert->status->getLabel(),
            'ceka_se_na' => $alert->waiting_for,
            'odlozeno_do' => $alert->isSnoozed($this->today) ? $alert->snoozed_until->toDateString() : null,
            'vyskyt' => $alert->occurrences,
            'od' => $alert->detected_on->toDateString(),
            'nove_dnes' => $alert->detected_on->isSameDay($this->today),
        ])->all();
    }

    /** @return list<array<string, mixed>>|array{poznamka: string} */
    private function leadFeedback(): array
    {
        if (! $this->client->adGoal()->countsLeads() && ! $this->client->adCampaignGroups()->whereIn('goal', [PrimaryGoal::Leads->value, PrimaryGoal::Recruitment->value])->exists()) {
            return [];
        }

        $rows = $this->client->leadFeedback()->limit(3)->get();

        return $rows->isEmpty()
            ? ['poznamka' => 'Zpětná vazba od klienta ke kvalitě leadů chybí. Levný lead nehodnoť jako úspěch.']
            : $rows->map(fn (LeadFeedback $row): array => $row->only(['period_from', 'period_to', 'leads', 'contacted', 'qualified', 'deals', 'deal_value', 'source', 'note']))->all();
    }

    /** @return list<array<string, mixed>> */
    private function creativeTests(): array
    {
        return $this->client->creativeTests()->limit(15)->get()->map(fn (CreativeTest $test): array => [
            'test' => $test->title,
            'od' => $test->started_on?->toDateString(),
            'hypoteza' => $test->hypothesis,
            'argument' => $test->argument,
            'hook' => $test->hook,
            'format' => $test->format,
            'vysledek' => $test->result,
            'verdikt' => $test->verdict->getLabel(),
            'poucení' => $test->learning,
        ])->all();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function tasks(): array
    {
        $row = fn (ClientTask $task): array => [
            'ukol' => $task->title,
            'stav' => $task->status->getLabel(),
            'priorita' => $task->priority,
            'termin' => $task->due_on?->toDateString(),
            'ceka_se_na' => $task->waiting_for,
            'odkud' => $task->source,
        ];

        return [
            'otevrene' => $this->client->tasks()->open()->orderByRaw('due_on is null')->orderBy('due_on')->limit(20)->get()->map($row)->all(),
            'navrhy_ke_schvaleni' => $this->client->taskProposals()->latest()->limit(20)->get()->map($row)->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function reportDrafts(): array
    {
        $ids = $this->client->activeAdAccountIds();
        $goal = $this->client->adGoal();
        $currency = $this->client->adCurrency();

        // Čísla za období reportu (a skupinu), ať agent píše fakta z nich, ne z posledních 7 dní.
        return $this->client->adReports()->draft()->with('group')->whereNull('facts')->latest('period_end')->limit(2)->get()
            ->map(function (AdReport $report) use ($ids, $goal, $currency): array {
                $period = Period::between($report->period_start, $report->period_end);
                $scope = new AdsScope($this->client, $report->group);
                $campaignIds = $scope->campaignIds();
                $goal = $scope->goal();

                return [
                    'id' => $report->getKey(),
                    'nazev' => $report->title,
                    'skupina' => $report->group?->name,
                    'cil' => array_filter(['cena_za_konverzi' => $scope->targetCpa(), 'roas' => $scope->targetRoas(), 'rozpocet' => $scope->monthlyBudget()]) ?: null,
                    'obdobi' => $period->label(),
                    'srovnani_s' => $period->previous()->label(),
                    'cisla' => $ids === [] ? null : $this->metrics(Stats::totals($ids, $period, $campaignIds), $goal, $currency, Stats::totals($ids, $period->previous(), $campaignIds)),
                    'kampane' => $ids === [] ? [] : Stats::campaigns($ids, $period, $campaignIds)->take(self::TOP_CAMPAIGNS)->map(fn (array $row): array => [
                        'kampan' => $row['name'],
                        'cisla' => $this->metrics($row['metrics'], $goal, $currency),
                    ])->values()->all(),
                    'zasahy_v_obdobi' => ClientPerformance::changes($this->client, $period, $scope),
                    'komentar_pavla' => $report->summary,
                ];
            })->all();
    }
}
