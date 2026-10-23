<?php

namespace App\Support\Ads;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\AlertStatus;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Client;
use App\Settings\AdsSettings;
use App\Support\Ads\Rules\AccountStatus;
use App\Support\Ads\Rules\AdsWithIssues;
use App\Support\Ads\Rules\AlertContext;
use App\Support\Ads\Rules\AnalyticsMismatch;
use App\Support\Ads\Rules\BudgetPacing;
use App\Support\Ads\Rules\CostAboveTarget;
use App\Support\Ads\Rules\CreativeFatigue;
use App\Support\Ads\Rules\Finding;
use App\Support\Ads\Rules\LandingPageProblem;
use App\Support\Ads\Rules\LandingPixelMissing;
use App\Support\Ads\Rules\LeadFeedbackMissing;
use App\Support\Ads\Rules\NoSpendYesterday;
use App\Support\Ads\Rules\PurchasesAboveCheckouts;
use App\Support\Ads\Rules\PurchasesWithoutValue;
use App\Support\Ads\Rules\RoasBelowTarget;
use App\Support\Ads\Rules\Rule;
use App\Support\Ads\Rules\SpendSpike;
use App\Support\Ads\Rules\SpendWithoutConversions;
use App\Support\Ads\Rules\SyncFailed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Denní kontrola reklam. Projde pravidla u každého klienta (a u každé jeho
 * skupiny kampaní) a srovná výsledek s upozorněními, která už existují:
 *
 * - nález, který už svítí, jen obnoví (čísla, datum posledního výskytu),
 *   stav „Řeším“ nebo „Čekám na klienta“ zůstane,
 * - problém, který se vrátil do dvou týdnů od zavření, otevře znovu jako
 *   totéž upozornění (počítá výskyty),
 * - nový nález založí, ledaže ho někdo za poslední týden zamítl,
 * - živé upozornění, které dnes pravidlo nenašlo, zavře jako „vrátilo se
 *   do normálu“.
 *
 * Když data nejsou kompletní (výpadek stahování), výkonová pravidla
 * neběží a jejich upozornění se ani nezavírají: z neúplných čísel by vyšly
 * falešné propady i falešná zlepšení. Svítí jen problém se stahováním.
 */
class AlertEngine
{
    /** Tak dlouho zůstane zamítnuté upozornění potichu. */
    private const DISMISS_DAYS = 7;

    /** Problém, který se vrátí do tolika dní od zavření, je totéž upozornění. */
    private const REOPEN_DAYS = 14;

    /** @var list<class-string<Rule>> */
    public const RULES = [
        SyncFailed::class,
        AccountStatus::class,
        SpendWithoutConversions::class,
        NoSpendYesterday::class,
        BudgetPacing::class,
        CostAboveTarget::class,
        RoasBelowTarget::class,
        CreativeFatigue::class,
        SpendSpike::class,
        PurchasesWithoutValue::class,
        PurchasesAboveCheckouts::class,
        AnalyticsMismatch::class,
        AdsWithIssues::class,
        LandingPageProblem::class,
        LandingPixelMissing::class,
        LeadFeedbackMissing::class,
    ];

    /** Bez těchhle by se nedalo poznat, že data nechodí. Nejdou ztlumit ani zmírnit. */
    public const ALWAYS_ON = ['sync_failed', 'account_status'];

    /**
     * Problémy s daty: stahování, stav účtu, měření, cílové stránky. Běží
     * vždy za celý účet, i když data nejsou kompletní.
     */
    public const DATA_RULES = [
        'sync_failed', 'account_status', 'measurement_value', 'measurement_duplicates',
        'analytics_mismatch', 'ad_issues', 'landing_page', 'landing_pixel', 'lead_feedback',
    ];

    /**
     * Výkon proti cíli. U klienta se skupinami kampaní běží za každou skupinu
     * s jejím cílem a prahy; za celý účet jen rozpočet a nulová útrata.
     */
    public const PERFORMANCE_RULES = [
        'no_conversions', 'no_spend', 'budget_pacing', 'cost_above_target',
        'roas_below_target', 'creative_fatigue', 'spend_spike',
    ];

    /** Výkonová pravidla, která za celý účet běží i u klienta se skupinami. */
    private const ACCOUNT_LEVEL = ['budget_pacing', 'no_spend'];

    /** Tahle pravidla mlčí pod minimální útratou za týden (Nastavení reklam). */
    private const NEED_MIN_SPEND = ['no_conversions', 'cost_above_target', 'roas_below_target', 'creative_fatigue', 'spend_spike'];

    public function __construct(private readonly AdsSettings $settings) {}

    /**
     * Naléhavost u klienta: „high“ zvedne Pozor na Hoří (velký rozpočet,
     * akce s termínem), „low“ sníží Pozor na Tip (malý účet, awareness).
     */
    private function withUrgency(Finding $finding, string $urgency, string $rule): Finding
    {
        if (in_array($rule, self::ALWAYS_ON, true)) {
            return $finding;
        }

        $severity = match (true) {
            $urgency === 'high' && $finding->severity === AlertSeverity::Warning => AlertSeverity::Critical,
            $urgency === 'low' && $finding->severity === AlertSeverity::Warning => AlertSeverity::Info,
            default => $finding->severity,
        };

        return $severity === $finding->severity ? $finding : $finding->withSeverity($severity);
    }

    /** @return array{opened: int, updated: int, resolved: int} */
    public function run(?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $counts = ['opened' => 0, 'updated' => 0, 'resolved' => 0];

        $clients = Client::query()->withAds()->with(['adAccounts', 'adSettings'])->get();

        foreach ($clients as $client) {
            foreach ($this->check($client, $today) as $key => $count) {
                $counts[$key] += $count;
            }
        }

        return $counts;
    }

    /** @return array{opened: int, updated: int, resolved: int} */
    public function check(Client $client, CarbonImmutable $today): array
    {
        $counts = ['opened' => 0, 'updated' => 0, 'resolved' => 0];
        $seen = [];
        $groups = $client->adCampaignGroups()->get();
        $account = new AlertContext($client, $this->settings, $today);
        $fresh = $account->dataStatus()->isFresh();

        // Celý účet: problémy s daty vždy, výkon jen bez skupin (jinak rozpočet a nulová útrata účtu).
        $rules = $groups->isEmpty() ? [...self::DATA_RULES, ...self::PERFORMANCE_RULES] : [...self::DATA_RULES, ...self::ACCOUNT_LEVEL];
        $found = $this->evaluate($account, $rules, $fresh, $counts, $seen);

        foreach ($groups as $group) {
            /** @var AdCampaignGroup $group */
            $context = new AlertContext($client, $this->settings, $today, new AdsScope($client, $group));
            // Když stojí celý účet, nulovou útratu skupin nehlásíme znovu.
            $skip = in_array('no_spend', $found, true) ? ['no_spend'] : [];
            $this->evaluate($context, array_diff(self::PERFORMANCE_RULES, $skip), $fresh, $counts, $seen);
        }

        // Co dnes nesvítí, vrátilo se do normálu. Výkon při neúplných datech nezavíráme.
        $stale = $client->adAlerts()
            ->live()
            ->whereNotIn('fingerprint', $seen)
            ->when(! $fresh, fn ($query) => $query->where('category', AdAlert::DATA))
            ->get();

        foreach ($stale as $alert) {
            /** @var AdAlert $alert */
            $alert->status = AlertStatus::Resolved;
            $alert->resolved_at = now();
            $alert->resolved_by = 'system';
            $alert->recovered_on = $today;
            $alert->log('Vrátilo se do normálu', $today);
            $alert->save();
            $counts['resolved']++;
        }

        return $counts;
    }

    /**
     * Projde pravidla v jednom kontextu (celý účet nebo skupina).
     *
     * @param  list<string>  $keys  která pravidla
     * @param  array{opened: int, updated: int, resolved: int}  $counts
     * @param  list<string>  $seen  otisky nálezů, doplní se
     * @return list<string> klíče pravidel, která něco našla
     */
    private function evaluate(AlertContext $context, array $keys, bool $fresh, array &$counts, array &$seen): array
    {
        $muted = (array) ($context->overrides()['muted'] ?? []);
        $urgency = $context->overrides()['urgency'] ?? 'normal';
        $weekSpend = $context->totals($context->week())->spend();
        $found = [];

        foreach (self::RULES as $class) {
            $rule = app($class);
            $key = $rule->key();

            if (! in_array($key, $keys, true)) {
                continue;
            }

            $performance = in_array($key, self::PERFORMANCE_RULES, true);

            // Ztlumené jen u klienta nebo skupiny (např. boosting bez cílové ceny).
            // Výpadek stahování a stav účtu se ztlumit nedají.
            if (in_array($key, $muted, true) && ! in_array($key, self::ALWAYS_ON, true)) {
                continue;
            }

            // Z neúplných dat nic nevyhodnocujeme, zkreslilo by to propady i odhady.
            if ($performance && ! $fresh) {
                continue;
            }

            if (in_array($key, self::NEED_MIN_SPEND, true) && $weekSpend < $context->threshold('min_spend')) {
                continue;
            }

            foreach ($rule->evaluate($context) as $finding) {
                $finding = $this->withUrgency($finding, $urgency, $key);
                $found[] = $key;

                if ($context->group() !== null) {
                    $finding = $finding->withTitle($context->prefix().$finding->title);
                }

                $fingerprint = self::fingerprint($key, $context, $finding);
                $seen[] = $fingerprint;
                $this->store($context, $key, $performance, $finding, $fingerprint, $counts);
            }
        }

        return $found;
    }

    /**
     * @param  array{opened: int, updated: int, resolved: int}  $counts
     */
    private function store(AlertContext $context, string $rule, bool $performance, Finding $finding, string $fingerprint, array &$counts): void
    {
        $today = $context->today;
        $values = [
            'severity' => $finding->severity,
            'title' => $finding->title,
            'facts' => $finding->facts,
            'basis' => $finding->basis ?? ($performance ? $this->basis($context) : null),
            'recommendation' => $finding->recommendation,
            'snapshot' => $finding->snapshot,
            'last_seen_on' => $today,
        ];

        $live = AdAlert::query()->live()->where('fingerprint', $fingerprint)->first();

        if ($live) {
            $live->update($values);
            $counts['updated']++;

            return;
        }

        // Vrátil se problém, který se nedávno sám spravil: totéž upozornění, další výskyt.
        $recent = AdAlert::query()
            ->where('fingerprint', $fingerprint)
            ->where('status', AlertStatus::Resolved)
            ->where('resolved_by', 'system')
            ->where('resolved_at', '>=', $today->subDays(self::REOPEN_DAYS))
            ->latest('resolved_at')
            ->first();

        if ($recent) {
            $recent->fill($values + ['status' => AlertStatus::Open, 'resolved_at' => null, 'resolved_by' => null, 'recovered_on' => null]);
            $recent->occurrences++;
            $recent->log('Znovu ('.$recent->occurrences.'. výskyt)', $today);
            $recent->save();
            $counts['opened']++;

            return;
        }

        $recentlyDismissed = AdAlert::query()
            ->where('fingerprint', $fingerprint)
            ->where('status', AlertStatus::Dismissed)
            ->where('updated_at', '>=', $today->subDays(self::DISMISS_DAYS))
            ->exists();

        if ($recentlyDismissed) {
            return;
        }

        $alert = $context->client->adAlerts()->make($values + [
            'ad_account_id' => $finding->account?->getKey(),
            'ad_campaign_group_id' => $context->group()?->getKey(),
            'rule' => $rule,
            'category' => $performance ? AdAlert::PERFORMANCE : AdAlert::DATA,
            'fingerprint' => $fingerprint,
            'status' => AlertStatus::Open,
            'detected_on' => $today,
        ]);
        $alert->log('Nové', $today);
        $alert->save();
        $counts['opened']++;
    }

    /**
     * Otisk: pravidlo, klient, účet a u skupiny i skupina. Celý účet má
     * tvar bez skupiny, ať se párují i upozornění z doby před skupinami.
     */
    private static function fingerprint(string $rule, AlertContext $context, Finding $finding): string
    {
        $parts = [$rule, $context->client->getKey(), $finding->account?->getKey() ?? 0];

        if ($context->group() !== null) {
            $parts[] = 'g'.$context->group()->getKey();
        }

        return implode(':', $parts);
    }

    /** Z jakého množství dat výkonové pravidlo vycházelo: týden, útrata, konverze. */
    private function basis(AlertContext $context): string
    {
        $week = $context->week();
        $totals = $context->totals($week);
        $goal = $context->goal();

        return $week->label().': útrata '.Format::money($totals->spend(), $context->currency())
            .', '.mb_strtolower($goal->conversionLabel()).' '.Format::count($totals->conversions($goal))
            .', zobrazení '.Format::number($totals->get('impressions'));
    }
}
