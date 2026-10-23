<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use App\Models\Ads\AdChange;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdEvent;
use App\Models\Client;

/**
 * Čísla klienta (nebo skupiny kampaní) za období jako prosté pole.
 *
 * Stejný tvar čte detail klienta v panelu (z živých dat) i report
 * (zmrazený do ad_reports.snapshot). Zobrazení z něj skládá PerformanceView.
 *
 * Verze 4 přidala skupinu kampaní, volbu srovnání, stav dat (do kdy jsou
 * kompletní), zásahy a termíny v období.
 */
final class ClientPerformance
{
    public const VERSION = 4;

    /** @return array<string, mixed> */
    public static function build(Client $client, Period $period, ?AdsScope $scope = null, string $compare = 'previous'): array
    {
        $client->loadMissing(['adAccounts', 'adSettings']);
        $scope ??= new AdsScope($client);

        $ids = $scope->accountIds();
        $campaignIds = $scope->campaignIds();
        $previous = $period->comparison($compare);
        $status = DataStatus::for($client->adAccounts);
        $goal = $scope->goal();

        $previousCampaigns = Stats::campaigns($ids, $previous, $campaignIds)->keyBy('campaign_id');
        $analyticsIds = $client->activeAnalyticsAccountIds();
        $groups = $client->adCampaignGroups()->get()->keyBy('id');
        $since = AdDailyStat::query()->whereIn('ad_account_id', $ids)->min('date');

        return [
            'version' => self::VERSION,
            'client' => $client->name,
            'scope' => ['key' => $scope->key(), 'label' => $scope->label(), 'is_group' => $scope->isGroup()],
            'currency' => $client->adCurrency(),
            'goal' => $goal->value,
            'period' => $period->toArray(),
            'previous' => $previous->toArray(),
            'compare' => $compare,
            // Do kdy jsou data kompletní. Bez toho by chybějící dny vypadaly jako nula.
            'data' => $status->toArray() + [
                'covers_period' => $status->covers($period),
                'missing_days' => $status->missingDays($period),
                'since' => $since ? substr((string) $since, 0, 10) : null,
            ],
            'totals' => Stats::totals($ids, $period, $campaignIds)->toArray(),
            'previous_totals' => Stats::totals($ids, $previous, $campaignIds)->toArray(),
            // Dosah se přes dny nesčítá. Přesný za období máme jen u přednastavených
            // období, jinak null a dlaždice dosahu ukážou pomlčku.
            'period_reach' => [
                'current' => $scope->periodReach($period),
                'previous' => $scope->periodReach($previous),
            ],
            'daily' => collect(Stats::daily($ids, $period, $campaignIds))
                ->map(fn (Metrics $metrics, string $date): array => ['date' => $date, 'state' => $status->dayState($date)] + $metrics->toArray())
                ->values()
                ->all(),
            'campaigns' => Stats::campaigns($ids, $period, $campaignIds)
                ->map(fn (array $row): array => [
                    'id' => $row['campaign_id'],
                    'name' => $row['name'],
                    'account' => $row['account'],
                    'group' => $row['group_id'] ? $groups[$row['group_id']]?->name : null,
                    'status' => $row['status'],
                    'totals' => $row['metrics']->toArray(),
                    'previous_totals' => ($previousCampaigns[$row['campaign_id']]['metrics'] ?? Metrics::empty())->toArray(),
                ])
                ->values()
                ->all(),
            // Rozpad podle účtů má smysl až u klienta s víc účty (Meta + Google).
            'by_account' => count($ids) > 1 ? Stats::accounts($ids, $period, $campaignIds) : [],
            'analytics' => $analyticsIds && ! $scope->isGroup() ? [
                'totals' => AnalyticsStats::totals($analyticsIds, $period),
                'previous_totals' => AnalyticsStats::totals($analyticsIds, $previous),
                'channels' => AnalyticsStats::channels($analyticsIds, $period),
            ] : null,
            'dashboard' => $scope->dashboard(),
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
                'monthly_budget' => $scope->monthlyBudget(),
                'target_cpa' => $scope->targetCpa(),
                'target_roas' => $scope->targetRoas(),
            ],
            'changes' => self::changes($client, $period, $scope),
            'events' => self::events($client, $period, $scope),
        ];
    }

    /**
     * Zásahy v období. U skupiny jen ty, které se týkají jí, jejích kampaní
     * nebo celého účtu.
     *
     * @return list<array{date: string, type: string, label: string, description: string, expected: ?string, scope: ?string}>
     */
    public static function changes(Client $client, Period $period, AdsScope $scope): array
    {
        return $client->adChanges()
            ->with(['group', 'campaign'])
            ->whereBetween('changed_on', $period->bounds())
            ->when($scope->isGroup(), fn ($query) => $query->where(fn ($query) => $query
                ->where('ad_campaign_group_id', $scope->group?->getKey() ?? 0)
                ->orWhereIn('ad_campaign_id', $scope->campaignIds() ?: [0])
                ->orWhere(fn ($query) => $query->whereNull('ad_campaign_group_id')->whereNull('ad_campaign_id'))))
            ->orderBy('changed_on')
            ->get()
            ->map(fn (AdChange $change): array => [
                'date' => $change->changed_on->toDateString(),
                'type' => $change->type->value,
                'label' => $change->type->getLabel(),
                'description' => $change->description,
                'expected' => $change->expected,
                'scope' => $change->scopeLabel(),
            ])
            ->all();
    }

    /**
     * Termíny z kalendáře, které padly do období (začátek akce, konec licence).
     *
     * @return list<array{date: string, type: string, label: string, title: string}>
     */
    public static function events(Client $client, Period $period, AdsScope $scope): array
    {
        return $client->adEvents()
            ->whereBetween('due_on', $period->bounds())
            ->when($scope->isGroup(), fn ($query) => $query->where(fn ($query) => $query
                ->where('ad_campaign_group_id', $scope->group?->getKey() ?? 0)
                ->orWhereNull('ad_campaign_group_id')))
            ->orderBy('due_on')
            ->get()
            ->map(fn (AdEvent $event): array => [
                'date' => $event->due_on->toDateString(),
                'type' => $event->type->value,
                'label' => $event->type->getLabel(),
                'title' => $event->title,
            ])
            ->all();
    }
}
