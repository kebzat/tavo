<?php

namespace App\Support\Ads\Platforms;

use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;

/**
 * Platforma umí jedním dotazem vrátit součty jednotlivých reklam za období
 * (pro přehled nejlepších reklam).
 */
interface HasAdInsights
{
    /**
     * @return list<array{external_id: string, name: string, campaign_external_id: ?string, campaign_name: ?string, spend: float, impressions: int, link_clicks: int, purchases: float, purchase_value: float, leads: float, engagements: float}>
     *
     * @throws AdsApiException
     */
    public function adInsights(AdAccount $account, Period $period): array;
}
