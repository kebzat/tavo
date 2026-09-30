<?php

namespace App\Support\Ads\Platforms;

use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Illuminate\Support\Collection;

/**
 * Analytika webu (GA4). Návštěvy a naměřené nákupy po dnech a kanálech.
 * Slouží jako protiváha k číslům, která si reklamní systémy přiřazují samy.
 */
interface AnalyticsPlatform extends ConnectedSource
{
    /**
     * @return Collection<int, TrafficStat>
     *
     * @throws AdsApiException
     */
    public function dailyTraffic(AdAccount $account, Period $period): Collection;
}
