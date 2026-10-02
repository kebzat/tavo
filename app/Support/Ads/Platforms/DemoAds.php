<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Vymyšlený reklamní systém pro ukázková data. Chová se jako Meta, jen nevolá žádné API. */
class DemoAds implements AdsPlatform
{
    public function platform(): AdPlatform
    {
        return AdPlatform::Demo;
    }

    public function isConfigured(): bool
    {
        return (bool) config('ads.demo_enabled');
    }

    public function accounts(): Collection
    {
        return collect(DemoCatalog::adAccounts())
            ->map(fn (array $spec, string $id): AccountInfo => $this->info($id, $spec))
            ->values();
    }

    public function account(string $externalId): AccountInfo
    {
        $spec = DemoCatalog::adAccounts()[$externalId] ?? throw new AdsApiException("Ukázkový účet {$externalId} neexistuje.");

        return $this->info($externalId, $spec);
    }

    public function dailyStats(AdAccount $account, Period $period): Collection
    {
        $spec = DemoCatalog::adAccounts()[$account->external_id] ?? null;

        if ($spec === null) {
            return collect();
        }

        $stats = collect();

        foreach ($period->dates() as $date) {
            // Dnešek ani budoucnost se nevymýšlí, skutečný systém je taky nemá celé.
            if (CarbonImmutable::parse($date)->gte(CarbonImmutable::today())) {
                continue;
            }

            foreach ($spec['campaigns'] as $i => $campaign) {
                $stats->push($this->day($account->external_id, $i, $campaign, $date, $spec));
            }
        }

        return $stats;
    }

    /**
     * @param  array{name: string, spend: int, cpm: int, ctr: float, cr: float, aov: int}  $campaign
     * @param  array<string, mixed>  $spec
     */
    private function day(string $accountId, int $index, array $campaign, string $date, array $spec): DailyStat
    {
        $key = "{$accountId}|{$index}|{$date}";
        $noise = fn (string $salt, float $spread): float => 1 + DemoCatalog::noise($key.$salt) * $spread;
        $daysAgo = DemoCatalog::daysAgo($date);
        $weekend = CarbonImmutable::parse($date)->isWeekend() ? 0.85 : 1.0;
        $fatigue = ($spec['fatigue'] ?? false) && $index === 0 && $daysAgo <= 7 ? 0.62 : 1.0;
        $broken = ($spec['broken_tracking'] ?? false) && $daysAgo <= 3;

        $spend = round($campaign['spend'] * $weekend * $noise('s', 0.25), 2);
        $impressions = (int) round($spend / $campaign['cpm'] * 1000);
        $linkClicks = (int) round($impressions * $campaign['ctr'] / 100 * $fatigue * $noise('c', 0.2));
        $conversions = $broken ? 0.0 : max(0.0, round($linkClicks * $campaign['cr'] / 100 * $noise('k', 0.5)));
        $isShop = $campaign['aov'] > 0;

        return new DailyStat(
            campaignId: "{$accountId}_{$index}",
            campaignName: $campaign['name'],
            objective: $isShop ? 'OUTCOME_SALES' : 'OUTCOME_LEADS',
            date: $date,
            spend: $spend,
            impressions: $impressions,
            reach: (int) round($impressions / 1.6),
            clicks: (int) round($linkClicks * 1.5),
            linkClicks: $linkClicks,
            purchases: $isShop ? $conversions : 0,
            purchaseValue: $isShop ? round($conversions * $campaign['aov'] * $noise('v', 0.2), 2) : 0,
            leads: $isShop ? 0 : $conversions,
            addToCart: $isShop && ! $broken ? round($linkClicks * 0.09 * $noise('a', 0.3)) : 0,
            checkouts: $isShop && ! $broken ? round($linkClicks * 0.05 * $noise('o', 0.3)) : 0,
            // Část prokliků odejde dřív, než se stránka načte.
            landingPageViews: round($linkClicks * 0.78 * $noise('l', 0.1)),
        );
    }

    /**
     * Dosah za období z vymyšlených denních čísel. Lidé se přes dny opakují,
     * takže frekvence s délkou období roste, jako u skutečného účtu.
     */
    public function periodReach(AdAccount $account, array $periods): Collection
    {
        return collect($periods)
            ->map(function (Period $period) use ($account): ReachStat {
                $days = $this->dailyStats($account, $period);
                $impressions = (int) $days->sum('impressions');
                $frequency = min(4.2, 1.25 + 0.06 * $period->days()) * (1 + DemoCatalog::noise($account->external_id.'|reach|'.$period->from->toDateString()) * 0.08);
                $reach = $frequency > 0 ? (int) round($impressions / $frequency) : 0;

                return new ReachStat(
                    from: $period->from->toDateString(),
                    to: $period->to->toDateString(),
                    reach: $reach,
                    impressions: $impressions,
                    frequency: $reach > 0 ? round($impressions / $reach, 4) : 0,
                    uniqueLinkClicks: (int) round($days->sum('linkClicks') * 0.82),
                );
            })
            ->values();
    }

    /** @param  array<string, mixed>  $spec */
    private function info(string $id, array $spec): AccountInfo
    {
        return new AccountInfo($id, $spec['name'], 'CZK', 'Europe/Prague', 'active', 'Ukázková data');
    }
}
