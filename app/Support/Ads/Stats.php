<?php

namespace App\Support\Ads;

use App\Models\Ads\AdDailyStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Součty z ad_daily_stats. Všechno se sčítá v databázi a poměry z toho
 * spočítá až Metrics.
 *
 * `$campaignIds` omezí součty na kampaně skupiny (AdsScope). Null = celý
 * účet, prázdné pole = skupina bez kampaní, tedy nic.
 */
final class Stats
{
    /** @param  list<int>  $accountIds */
    public static function totals(array $accountIds, Period $period, ?array $campaignIds = null): Metrics
    {
        $row = self::query($accountIds, $period, $campaignIds)->selectRaw(self::sums())->first();

        return Metrics::fromArray($row?->getAttributes() ?? []);
    }

    /**
     * Součty po účtech. Pro přehled klientů, kde se jedním dotazem počítají všichni.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, Metrics> ad_account_id => Metrics
     */
    public static function byAccount(array $accountIds, Period $period, ?array $campaignIds = null): Collection
    {
        return self::query($accountIds, $period, $campaignIds)
            ->selectRaw('ad_account_id, '.self::sums())
            ->groupBy('ad_account_id')
            ->get()
            ->mapWithKeys(fn (AdDailyStat $row): array => [$row->ad_account_id => Metrics::fromArray($row->getAttributes())]);
    }

    /**
     * Den po dni, dny bez dat jako nuly. Graf tak nemá díry.
     *
     * @param  list<int>  $accountIds
     * @return array<string, Metrics> Y-m-d => Metrics
     */
    public static function daily(array $accountIds, Period $period, ?array $campaignIds = null): array
    {
        $rows = self::query($accountIds, $period, $campaignIds)
            ->selectRaw('date, '.self::sums())
            ->groupBy('date')
            ->get()
            ->keyBy(fn (AdDailyStat $row): string => $row->date->toDateString());

        $days = [];

        foreach ($period->dates() as $date) {
            $days[$date] = isset($rows[$date]) ? Metrics::fromArray($rows[$date]->getAttributes()) : Metrics::empty();
        }

        return $days;
    }

    /**
     * Denní součty po účtech, pro sparklines v přehledu klientů.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, array<string, Metrics>> ad_account_id => [Y-m-d => Metrics]
     */
    public static function dailyByAccount(array $accountIds, Period $period, ?array $campaignIds = null): Collection
    {
        return self::query($accountIds, $period, $campaignIds)
            ->selectRaw('ad_account_id, date, '.self::sums())
            ->groupBy('ad_account_id', 'date')
            ->get()
            ->groupBy('ad_account_id')
            ->map(fn (Collection $rows): array => $rows
                ->mapWithKeys(fn (AdDailyStat $row): array => [$row->date->toDateString() => Metrics::fromArray($row->getAttributes())])
                ->all());
    }

    /**
     * Součty po účtech i s názvem a platformou, pro rozpad klienta s víc účty.
     *
     * @param  list<int>  $accountIds
     * @return list<array{name: string, platform: string, totals: array<string, float>}>
     */
    public static function accounts(array $accountIds, Period $period, ?array $campaignIds = null): array
    {
        return self::query($accountIds, $period, $campaignIds)
            ->join('ad_accounts', 'ad_accounts.id', '=', 'ad_daily_stats.ad_account_id')
            ->selectRaw('ad_accounts.name as account_name, ad_accounts.platform as account_platform, '.self::sums('ad_daily_stats.'))
            ->groupBy('ad_accounts.id', 'ad_accounts.name', 'ad_accounts.platform')
            ->orderByDesc('spend')
            ->get()
            ->map(fn (AdDailyStat $row): array => [
                'name' => (string) $row->account_name,
                'platform' => (string) $row->getAttributes()['account_platform'],
                'totals' => Metrics::fromArray($row->getAttributes())->toArray(),
            ])
            ->all();
    }

    /**
     * Součty po kampaních, od největší útraty. I se skupinou a stavem kampaně.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, array{campaign_id: int, name: string, account: string, group_id: ?int, status: ?string, metrics: Metrics}>
     */
    public static function campaigns(array $accountIds, Period $period, ?array $campaignIds = null): Collection
    {
        return self::query($accountIds, $period, $campaignIds)
            ->join('ad_campaigns', 'ad_campaigns.id', '=', 'ad_daily_stats.ad_campaign_id')
            ->join('ad_accounts', 'ad_accounts.id', '=', 'ad_daily_stats.ad_account_id')
            ->selectRaw('ad_campaign_id, ad_campaigns.name as campaign_name, ad_campaigns.ad_campaign_group_id as group_id, ad_campaigns.status as campaign_status, ad_accounts.name as account_name, '.self::sums('ad_daily_stats.'))
            ->groupBy('ad_campaign_id', 'ad_campaigns.name', 'ad_campaigns.ad_campaign_group_id', 'ad_campaigns.status', 'ad_accounts.name')
            ->orderByDesc('spend')
            ->get()
            ->map(fn (AdDailyStat $row): array => [
                'campaign_id' => (int) $row->ad_campaign_id,
                'name' => (string) $row->campaign_name,
                'account' => (string) $row->account_name,
                'group_id' => $row->group_id !== null ? (int) $row->group_id : null,
                'status' => $row->campaign_status,
                'metrics' => Metrics::fromArray($row->getAttributes()),
            ]);
    }

    /**
     * Součty po kampaních jedním dotazem pro víc klientů (přehled klientů
     * se skupinami). Skupinu z nich poskládá AdsScope.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, Metrics> ad_campaign_id => Metrics
     */
    public static function byCampaign(array $accountIds, Period $period): Collection
    {
        return self::query($accountIds, $period)
            ->selectRaw('ad_campaign_id, '.self::sums())
            ->groupBy('ad_campaign_id')
            ->get()
            ->mapWithKeys(fn (AdDailyStat $row): array => [(int) $row->ad_campaign_id => Metrics::fromArray($row->getAttributes())]);
    }

    /**
     * Každý den po kampaních, pro export do CSV.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, AdDailyStat>
     */
    public static function rows(array $accountIds, Period $period, ?array $campaignIds = null): Collection
    {
        return self::query($accountIds, $period, $campaignIds)
            ->with(['campaign.group', 'account'])
            ->orderBy('date')
            ->orderBy('ad_campaign_id')
            ->get();
    }

    /**
     * @param  list<int>  $accountIds
     * @param  list<int>|null  $campaignIds
     */
    private static function query(array $accountIds, Period $period, ?array $campaignIds = null): Builder
    {
        return AdDailyStat::query()
            ->whereIn('ad_daily_stats.ad_account_id', $accountIds)
            ->when($campaignIds !== null, fn (Builder $query) => $query->whereIn('ad_daily_stats.ad_campaign_id', $campaignIds))
            ->whereBetween('ad_daily_stats.date', $period->bounds());
    }

    private static function sums(string $prefix = ''): string
    {
        return collect(AdDailyStat::SUMS)
            ->map(fn (string $column): string => "coalesce(sum({$prefix}{$column}), 0) as {$column}")
            ->implode(', ');
    }
}
