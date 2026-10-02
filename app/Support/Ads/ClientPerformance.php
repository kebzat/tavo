<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use App\Models\Client;

/**
 * Čísla klienta za období jako prosté pole.
 *
 * Stejný tvar čte detail klienta v panelu (z živých dat) i report
 * (zmrazený do ad_reports.snapshot). Zobrazení z něj skládá PerformanceView.
 */
final class ClientPerformance
{
    public const VERSION = 3;

    /** @return array<string, mixed> */
    public static function build(Client $client, Period $period): array
    {
        $client->loadMissing(['adAccounts', 'adSettings']);

        $ids = $client->activeAdAccountIds();
        $previous = $period->previous();
        $settings = $client->adSettings;

        $previousCampaigns = Stats::campaigns($ids, $previous)->keyBy('campaign_id');
        $analyticsIds = $client->activeAnalyticsAccountIds();
        $reachIds = PeriodReach::accountIds($client->adAccounts);

        return [
            'version' => self::VERSION,
            'client' => $client->name,
            'currency' => $client->adCurrency(),
            'goal' => $client->adGoal()->value,
            'period' => $period->toArray(),
            'previous' => $previous->toArray(),
            'totals' => Stats::totals($ids, $period)->toArray(),
            'previous_totals' => Stats::totals($ids, $previous)->toArray(),
            // Dosah se přes dny nesčítá. Přesný za období máme jen u přednastavených
            // období, jinak null a dlaždice dosahu ukážou pomlčku. Od verze 3.
            'period_reach' => [
                'current' => PeriodReach::total($reachIds, $period),
                'previous' => PeriodReach::total($reachIds, $previous),
            ],
            'daily' => collect(Stats::daily($ids, $period))
                ->map(fn (Metrics $metrics, string $date): array => ['date' => $date] + $metrics->toArray())
                ->values()
                ->all(),
            'campaigns' => Stats::campaigns($ids, $period)
                ->map(fn (array $row): array => [
                    'name' => $row['name'],
                    'account' => $row['account'],
                    'totals' => $row['metrics']->toArray(),
                    'previous_totals' => ($previousCampaigns[$row['campaign_id']]['metrics'] ?? Metrics::empty())->toArray(),
                ])
                ->values()
                ->all(),
            // Rozpad podle účtů má smysl až u klienta s víc účty (Meta + Google).
            'by_account' => count($ids) > 1 ? Stats::accounts($ids, $period) : [],
            'analytics' => $analyticsIds ? [
                'totals' => AnalyticsStats::totals($analyticsIds, $period),
                'previous_totals' => AnalyticsStats::totals($analyticsIds, $previous),
                'channels' => AnalyticsStats::channels($analyticsIds, $period),
            ] : null,
            'dashboard' => $settings?->dashboard,
            'accounts' => $client->adAccounts
                ->filter(fn (AdAccount $account): bool => $account->is_active && ! $account->isAnalytics())
                ->map(fn (AdAccount $account): array => [
                    'name' => $account->name,
                    'platform' => $account->platform->value,
                    'currency' => $account->currency,
                ])
                ->values()
                ->all(),
            'targets' => [
                'monthly_budget' => $settings?->monthly_budget,
                'target_cpa' => $settings?->target_cpa,
                'target_roas' => $settings?->target_roas,
            ],
        ];
    }
}
