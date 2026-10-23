<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Vymyšlený reklamní systém pro ukázková data. Chová se jako Meta, jen nevolá žádné API. */
class DemoAds implements AdsPlatform, HasAdInsights, HasCampaignStatus
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
                // Vypnutá kampaň v posledních 60 dnech nic neutrácí, předtím běžela.
                if (($campaign['paused'] ?? false) && DemoCatalog::daysAgo($date) <= 60) {
                    continue;
                }

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
        $kind = $campaign['kind'] ?? ($isShop ? 'sales' : 'leads');
        $conversions = $kind === 'awareness' ? 0.0 : $conversions;

        return new DailyStat(
            campaignId: "{$accountId}_{$index}",
            campaignName: $campaign['name'],
            objective: match ($kind) {
                'awareness' => 'OUTCOME_ENGAGEMENT',
                'sales' => 'OUTCOME_SALES',
                default => 'OUTCOME_LEADS',
            },
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
            engagements: $kind === 'awareness' ? round($impressions * 0.035 * $noise('e', 0.3)) : round($impressions * 0.004),
            // Leady: zhruba dvě třetiny z formuláře na Metě, zbytek z webu.
            formLeads: $isShop ? 0 : round($conversions * 0.65),
            webLeads: $isShop ? 0 : $conversions - round($conversions * 0.65),
        );
    }

    /**
     * Dosah za období z vymyšlených denních čísel. Lidé se přes dny opakují,
     * takže frekvence s délkou období roste, jako u skutečného účtu.
     */
    public function periodReach(AdAccount $account, array $periods, ?array $campaignIds = null): Collection
    {
        return collect($periods)
            ->map(function (Period $period) use ($account, $campaignIds): ReachStat {
                $days = $this->dailyStats($account, $period)
                    ->when($campaignIds !== null, fn ($days) => $days->whereIn('campaignId', $campaignIds));
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

    /**
     * Ukázkové reklamy: každá kampaň má dvě až tři kreativy, které si dělí
     * její čísla v různém poměru, ať je v přehledu vidět vítěz a propadák.
     */
    public function adInsights(AdAccount $account, Period $period): array
    {
        $creatives = ['Video UGC', 'Carousel produktů', 'Statický banner'];
        $ads = [];

        foreach ($this->dailyStats($account, $period)->groupBy('campaignId') as $campaignId => $days) {
            $count = 2 + (crc32((string) $campaignId) % 2);

            for ($i = 0; $i < $count; $i++) {
                $share = [[0.55, 0.45], [0.5, 0.3, 0.2]][$count - 2][$i];
                // Kreativa s menší útratou má jinou účinnost, ať se pořadí podle ceny liší od pořadí podle útraty.
                $efficiency = 1 + DemoCatalog::noise($campaignId.'|ad|'.$i) * 0.45;

                $ads[] = [
                    'external_id' => $campaignId.'_ad'.$i,
                    'name' => $creatives[$i].' · '.$days->first()->campaignName,
                    'campaign_external_id' => (string) $campaignId,
                    'campaign_name' => $days->first()->campaignName,
                    'spend' => round($days->sum('spend') * $share, 2),
                    'impressions' => (int) round($days->sum('impressions') * $share),
                    'link_clicks' => (int) round($days->sum('linkClicks') * $share * $efficiency),
                    'purchases' => round($days->sum('purchases') * $share * $efficiency),
                    'purchase_value' => round($days->sum('purchaseValue') * $share * $efficiency, 2),
                    'leads' => round($days->sum('leads') * $share * $efficiency),
                    'engagements' => round($days->sum('engagements') * $share),
                ];
            }
        }

        return $ads;
    }

    /** Ukázkové kampaně běží, jen ty s `paused` v katalogu jsou vypnuté. */
    public function campaignStatuses(AdAccount $account): array
    {
        $spec = DemoCatalog::adAccounts()[$account->external_id] ?? ['campaigns' => []];

        return collect($spec['campaigns'])
            ->mapWithKeys(fn (array $campaign, int $i): array => ["{$account->external_id}_{$i}" => ($campaign['paused'] ?? false) ? 'PAUSED' : 'ACTIVE'])
            ->all();
    }

    /** @param  array<string, mixed>  $spec */
    private function info(string $id, array $spec): AccountInfo
    {
        return new AccountInfo($id, $spec['name'], 'CZK', 'Europe/Prague', 'active', 'Ukázková data');
    }
}
