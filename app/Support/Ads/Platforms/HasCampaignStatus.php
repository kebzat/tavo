<?php

namespace App\Support\Ads\Platforms;

use App\Models\Ads\AdAccount;

/**
 * Platforma umí jedním dotazem vrátit stav všech kampaní účtu (běží,
 * vypnutá, archivovaná). Ranní stažení ho uloží k ad_campaigns.status.
 */
interface HasCampaignStatus
{
    /**
     * @return array<string, string> external_id kampaně => stav (ACTIVE, PAUSED…)
     *
     * @throws AdsApiException
     */
    public function campaignStatuses(AdAccount $account): array;
}
