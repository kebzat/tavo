<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Vymyšlená GA4 pro ukázková data: návštěvy a nákupy po kanálech. */
class DemoAnalytics implements AnalyticsPlatform
{
    /** Podíl návštěv a konverzní poměr kanálu vůči průměru webu. */
    private const CHANNELS = [
        'Organic Search' => [0.34, 1.1],
        'Direct' => [0.22, 1.4],
        'Paid Social' => [0.20, 0.6],
        'Paid Search' => [0.12, 1.3],
        'Email' => [0.06, 2.2],
        'Referral' => [0.06, 0.9],
    ];

    public function platform(): AdPlatform
    {
        return AdPlatform::DemoGa4;
    }

    public function isConfigured(): bool
    {
        return (bool) config('ads.demo_enabled');
    }

    public function accounts(): Collection
    {
        return collect(DemoCatalog::analyticsAccounts())
            ->map(fn (array $spec, string $id): AccountInfo => new AccountInfo($id, $spec['name'], 'CZK', 'Europe/Prague', 'active', 'Ukázková data'))
            ->values();
    }

    public function account(string $externalId): AccountInfo
    {
        $spec = DemoCatalog::analyticsAccounts()[$externalId] ?? throw new AdsApiException("Ukázková property {$externalId} neexistuje.");

        return new AccountInfo($externalId, $spec['name'], 'CZK', 'Europe/Prague', 'active', 'Ukázková data');
    }

    public function dailyTraffic(AdAccount $account, Period $period): Collection
    {
        $spec = DemoCatalog::analyticsAccounts()[$account->external_id] ?? null;
        $stats = collect();

        if ($spec === null) {
            return $stats;
        }

        foreach ($period->dates() as $date) {
            if (CarbonImmutable::parse($date)->gte(CarbonImmutable::today())) {
                continue;
            }

            $weekend = CarbonImmutable::parse($date)->isWeekend() ? 0.8 : 1.0;

            foreach (self::CHANNELS as $channel => [$share, $crFactor]) {
                $key = "{$account->external_id}|{$channel}|{$date}";
                $sessions = (int) round($spec['sessions'] * $share * $weekend * (1 + DemoCatalog::noise($key.'s') * 0.2));
                $purchases = max(0.0, round($sessions * $spec['cr'] * $crFactor / 100 * (1 + DemoCatalog::noise($key.'p') * 0.5)));

                $stats->push(new TrafficStat(
                    date: $date,
                    channel: $channel,
                    sessions: $sessions,
                    users: (int) round($sessions * 0.82),
                    engagedSessions: (int) round($sessions * 0.58),
                    keyEvents: $purchases,
                    purchases: $purchases,
                    revenue: round($purchases * $spec['aov'] * (1 + DemoCatalog::noise($key.'r') * 0.15), 2),
                ));
            }
        }

        return $stats;
    }
}
