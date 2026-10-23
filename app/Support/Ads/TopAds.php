<?php

namespace App\Support\Ads;

use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdTopAd;

/**
 * Nejlepší a nejslabší reklamy za posledních 30 dní (ranní stažení, jeden
 * dotaz na účet). U skupiny kampaní jen reklamy jejích kampaní. Řadí se
 * podle konverzí, u boostingu podle interakcí.
 */
final class TopAds
{
    /** Pod tímhle počtem konverzí se cena za konverzi nehodnotí jako „nejlepší“. */
    private const MIN_CONVERSIONS = 2;

    /**
     * @return list<array{name: string, campaign: ?string, spend: string, conversions: string, cost: string, roas: ?string, ctr: string, tag: ?string}>
     */
    public static function for(AdsScope $scope, int $limit = 8): array
    {
        $goal = $scope->goal();
        $currency = $scope->currency();
        $query = AdTopAd::query()->whereIn('ad_account_id', $scope->accountIds());

        if ($scope->campaignIds() !== null) {
            $external = AdCampaign::query()->whereIn('id', $scope->campaignIds() ?: [0])->get(['ad_account_id', 'external_id']);
            $query->where(function ($query) use ($external): void {
                $query->whereRaw('1 = 0');

                foreach ($external->groupBy('ad_account_id') as $accountId => $campaigns) {
                    $query->orWhere(fn ($query) => $query->where('ad_account_id', $accountId)->whereIn('campaign_external_id', $campaigns->pluck('external_id')));
                }
            });
        }

        $ads = $query->get()->map(fn (AdTopAd $ad): array => ['ad' => $ad, 'm' => $ad->metrics()]);

        if ($ads->isEmpty()) {
            return [];
        }

        $averageSpend = $ads->avg(fn (array $row): float => $row['m']->spend());
        $eligible = $ads->filter(fn (array $row): bool => $row['m']->conversions($goal) >= self::MIN_CONVERSIONS);
        $best = $goal->isPerformance()
            ? $eligible->sortBy(fn (array $row): float => $row['m']->costPerConversion($goal) ?? INF)->first()
            : $ads->sortByDesc(fn (array $row): float => $row['m']->conversions($goal))->first();

        return $ads
            ->sortByDesc(fn (array $row): float => $row['m']->conversions($goal) * 1000000 + $row['m']->spend())
            ->take($limit)
            ->map(fn (array $row): array => [
                'name' => $row['ad']->name,
                'campaign' => $row['ad']->campaign_name,
                'spend' => Format::money($row['m']->spend(), $currency),
                'conversions' => Format::count($row['m']->conversions($goal)),
                'cost' => Format::unitPrice($row['m']->costPerConversion($goal), $currency),
                'roas' => $goal->hasValue() ? Format::roas($row['m']->roas()) : null,
                'ctr' => Format::percent($row['m']->ctr()),
                'tag' => match (true) {
                    $best !== null && $row['ad']->is($best['ad']) => 'nejlevnější konverze',
                    $goal->isPerformance() && $row['m']->conversions($goal) == 0 && $row['m']->spend() > $averageSpend => 'utrácí bez konverze',
                    default => null,
                },
            ])
            ->values()
            ->all();
    }

    /** Za jaké období čísla reklam jsou, do nadpisu. */
    public static function periodLabel(AdsScope $scope): ?string
    {
        $row = AdTopAd::query()->whereIn('ad_account_id', $scope->accountIds())->first();

        return $row ? Period::between($row->date_from, $row->date_to)->label() : null;
    }
}
