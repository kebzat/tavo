<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdGroupReach;
use App\Support\Ads\Platforms\ReachStat;
use Illuminate\Support\Collection;

/**
 * Přesný dosah skupiny kampaní za období. Stejně jako u celého účtu se
 * dosah přes dny ani kampaně sčítat nedá, Meta ho pro výběr kampaní počítá
 * zvlášť: ranní stažení pošle za každou skupinu jeden dotaz navíc.
 *
 * Řádek nese `signature` kampaní, pro které platí. Když se skupina přes
 * den změní, starý dosah se neukáže, dlaždice dá pomlčku do rána.
 */
final class GroupReach
{
    /**
     * Kampaně skupiny v jednom účtu, jak je zná platforma.
     *
     * @return list<string> external_id
     */
    public static function externalIds(AdCampaignGroup $group, AdAccount $account): array
    {
        return $group->campaigns()
            ->where('ad_account_id', $account->getKey())
            ->orderBy('external_id')
            ->pluck('external_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /** @param  list<string>  $externalIds */
    public static function signature(array $externalIds): string
    {
        sort($externalIds);

        return sha1(implode(',', $externalIds));
    }

    /**
     * @param  list<Period>  $periods
     * @param  list<string>  $externalIds
     * @param  Collection<int, ReachStat>  $stats
     */
    public static function store(AdCampaignGroup $group, AdAccount $account, array $periods, array $externalIds, Collection $stats): void
    {
        $byRange = $stats->keyBy(fn (ReachStat $stat): string => $stat->from.'|'.$stat->to);
        $signature = self::signature($externalIds);
        $now = now();

        $rows = array_map(function (Period $period) use ($group, $account, $byRange, $signature, $now): array {
            $stat = $byRange[$period->from->toDateString().'|'.$period->to->toDateString()] ?? null;

            return [
                'ad_campaign_group_id' => $group->getKey(),
                'ad_account_id' => $account->getKey(),
                'date_from' => $period->from->toDateString(),
                'date_to' => $period->to->toDateString(),
                'reach' => $stat->reach ?? 0,
                'impressions' => $stat->impressions ?? 0,
                'frequency' => round($stat->frequency ?? 0, 4),
                'unique_link_clicks' => $stat->uniqueLinkClicks ?? 0,
                'signature' => $signature,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $periods);

        AdGroupReach::query()->where('ad_campaign_group_id', $group->getKey())->where('ad_account_id', $account->getKey())->delete();
        AdGroupReach::query()->insert($rows);
    }

    /**
     * Dosah skupiny za období, sečtený přes účty (u víc účtů Mety se lidé
     * můžou překrývat, je to horní odhad). Null, když některý účet řádek
     * pro současné složení skupiny nemá.
     *
     * @return array{reach: float, impressions: float, unique_link_clicks: float}|null
     */
    public static function total(AdCampaignGroup $group, Period $period): ?array
    {
        $accounts = AdAccount::query()
            ->whereIn('id', $group->campaigns()->distinct()->pluck('ad_account_id'))
            ->where('is_active', true)
            ->get()
            ->filter(fn (AdAccount $account): bool => $account->platform->hasPeriodReach());

        if ($accounts->isEmpty()) {
            return null;
        }

        $total = ['reach' => 0.0, 'impressions' => 0.0, 'unique_link_clicks' => 0.0];

        foreach ($accounts as $account) {
            $row = AdGroupReach::query()
                ->where('ad_campaign_group_id', $group->getKey())
                ->where('ad_account_id', $account->getKey())
                ->whereDate('date_from', $period->from->toDateString())
                ->whereDate('date_to', $period->to->toDateString())
                ->first();

            if ($row === null || $row->signature !== self::signature(self::externalIds($group, $account))) {
                return null;
            }

            $total['reach'] += $row->reach;
            $total['impressions'] += $row->impressions;
            $total['unique_link_clicks'] += $row->unique_link_clicks;
        }

        return $total;
    }
}
