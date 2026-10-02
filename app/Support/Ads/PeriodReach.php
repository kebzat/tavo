<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use App\Models\Ads\AdPeriodReach;
use App\Support\Ads\Platforms\ReachStat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Přesný dosah, frekvence a unikátní prokliky za období.
 *
 * Tahle čísla se přes dny ani kampaně sčítat nedají: tentýž člověk by se
 * započítal každý den znovu. Ranní synchronizace proto jedním dotazem na účet
 * stáhne dosah za přednastavená období (a jejich srovnání) a tady se čtou.
 * Pro vlastní období od–do je nemáme, dlaždice tam ukáže pomlčku.
 */
final class PeriodReach
{
    public const UNAVAILABLE_HINT = 'jen u přednastavených období';

    /**
     * Období, za která se dosah stahuje: přednastavená, jejich srovnání
     * a minulý týden pondělí až neděle (týdenní report).
     *
     * @return list<Period>
     */
    public static function ranges(?CarbonInterface $today = null): array
    {
        $periods = [];

        foreach (array_keys(Period::PRESETS) as $key) {
            $period = Period::preset($key, $today);
            $periods[] = $period;
            $periods[] = $period->previous();
        }

        $lastWeek = Period::week(CarbonImmutable::parse($today ?? now())->subWeek());
        $periods[] = $lastWeek;
        $periods[] = $lastWeek->previous();

        return array_values(collect($periods)->unique(fn (Period $period): string => implode('|', $period->toArray()))->all());
    }

    /**
     * Přepíše dosah účtu. Období, které platforma nevrátila (účet v něm
     * neběžel), se uloží jako nula: víme, že dosah byl nulový.
     *
     * @param  list<Period>  $periods
     * @param  Collection<int, ReachStat>  $stats
     */
    public static function store(AdAccount $account, array $periods, Collection $stats): void
    {
        $byRange = $stats->keyBy(fn (ReachStat $stat): string => $stat->from.'|'.$stat->to);
        $now = now();

        $rows = array_map(function (Period $period) use ($account, $byRange, $now): array {
            $stat = $byRange[$period->from->toDateString().'|'.$period->to->toDateString()] ?? null;

            return [
                'ad_account_id' => $account->getKey(),
                'date_from' => $period->from->toDateString(),
                'date_to' => $period->to->toDateString(),
                'reach' => $stat->reach ?? 0,
                'impressions' => $stat->impressions ?? 0,
                'frequency' => round($stat->frequency ?? 0, 4),
                'unique_link_clicks' => $stat->uniqueLinkClicks ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $periods);

        AdPeriodReach::query()->where('ad_account_id', $account->getKey())->delete();
        AdPeriodReach::query()->insert($rows);
    }

    /**
     * Dosah za období po účtech.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, AdPeriodReach> ad_account_id => řádek
     */
    public static function byAccount(array $accountIds, Period $period): Collection
    {
        if ($accountIds === []) {
            return collect();
        }

        return AdPeriodReach::query()
            ->whereIn('ad_account_id', $accountIds)
            ->whereDate('date_from', $period->from->toDateString())
            ->whereDate('date_to', $period->to->toDateString())
            ->get()
            ->keyBy('ad_account_id');
    }

    /**
     * Součet za účty klienta. Null, když účet s dosahem pro období řádek nemá
     * (vlastní období, nebo se ještě nestáhlo): půlka čísla by mátla.
     *
     * @param  list<int>  $accountIds  účty, které dosah umí (AdPlatform::hasPeriodReach)
     * @param  Collection<int, AdPeriodReach>|null  $rows  předem načtené řádky (přehled klientů)
     * @return array{reach: float, impressions: float, unique_link_clicks: float}|null
     */
    public static function total(array $accountIds, Period $period, ?Collection $rows = null): ?array
    {
        if ($accountIds === []) {
            return null;
        }

        $rows ??= self::byAccount($accountIds, $period);
        $total = ['reach' => 0.0, 'impressions' => 0.0, 'unique_link_clicks' => 0.0];

        foreach ($accountIds as $id) {
            $row = $rows[$id] ?? null;

            if ($row === null) {
                return null;
            }

            $total['reach'] += $row->reach;
            $total['impressions'] += $row->impressions;
            $total['unique_link_clicks'] += $row->unique_link_clicks;
        }

        return $total;
    }

    /**
     * Účty klienta, u kterých se dosah stahuje.
     *
     * @param  Collection<int, AdAccount>  $accounts
     * @return list<int>
     */
    public static function accountIds(Collection $accounts): array
    {
        return $accounts
            ->filter(fn (AdAccount $account): bool => $account->is_active && $account->platform->hasPeriodReach())
            ->pluck('id')
            ->values()
            ->all();
    }
}
