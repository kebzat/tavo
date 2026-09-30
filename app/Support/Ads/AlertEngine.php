<?php

namespace App\Support\Ads;

use App\Enums\Ads\AlertStatus;
use App\Models\Ads\AdAlert;
use App\Models\Client;
use App\Settings\AdsSettings;
use App\Support\Ads\Rules\AccountStatus;
use App\Support\Ads\Rules\AlertContext;
use App\Support\Ads\Rules\BudgetPacing;
use App\Support\Ads\Rules\CostAboveTarget;
use App\Support\Ads\Rules\CreativeFatigue;
use App\Support\Ads\Rules\NoSpendYesterday;
use App\Support\Ads\Rules\RoasBelowTarget;
use App\Support\Ads\Rules\Rule;
use App\Support\Ads\Rules\SpendSpike;
use App\Support\Ads\Rules\SpendWithoutConversions;
use App\Support\Ads\Rules\SyncFailed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Denní kontrola reklam. Projde pravidla u každého klienta a srovná
 * výsledek s upozorněními, která už existují:
 *
 * - nález, který už svítí, jen obnoví (čísla, datum posledního výskytu),
 * - nový nález založí, ledaže ho někdo za poslední týden zamítl,
 * - živé upozornění, které dnes pravidlo nenašlo, zavře jako vyřešené.
 */
class AlertEngine
{
    /** Tak dlouho zůstane zamítnuté upozornění potichu. */
    private const DISMISS_DAYS = 7;

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
    ];

    public function __construct(private readonly AdsSettings $settings) {}

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
        $context = new AlertContext($client, $this->settings, $today);
        $counts = ['opened' => 0, 'updated' => 0, 'resolved' => 0];
        $seen = [];

        foreach (self::RULES as $class) {
            $rule = app($class);

            foreach ($rule->evaluate($context) as $finding) {
                $fingerprint = implode(':', [$rule->key(), $client->getKey(), $finding->account?->getKey() ?? 0]);
                $seen[] = $fingerprint;

                $values = [
                    'severity' => $finding->severity,
                    'title' => $finding->title,
                    'recommendation' => $finding->recommendation,
                    'snapshot' => $finding->snapshot,
                    'last_seen_on' => $today,
                ];

                $live = AdAlert::query()->live()->where('fingerprint', $fingerprint)->first();

                if ($live) {
                    $live->update($values);
                    $counts['updated']++;

                    continue;
                }

                $recentlyDismissed = AdAlert::query()
                    ->where('fingerprint', $fingerprint)
                    ->where('status', AlertStatus::Dismissed)
                    ->where('updated_at', '>=', $today->subDays(self::DISMISS_DAYS))
                    ->exists();

                if ($recentlyDismissed) {
                    continue;
                }

                $client->adAlerts()->create($values + [
                    'ad_account_id' => $finding->account?->getKey(),
                    'rule' => $rule->key(),
                    'fingerprint' => $fingerprint,
                    'status' => AlertStatus::Open,
                    'detected_on' => $today,
                ]);
                $counts['opened']++;
            }
        }

        $counts['resolved'] = $client->adAlerts()
            ->live()
            ->whereNotIn('fingerprint', $seen)
            ->update(['status' => AlertStatus::Resolved, 'resolved_at' => now()]);

        return $counts;
    }
}
