<?php

namespace App\Support\Ads\Platforms;

use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Illuminate\Support\Collection;

/**
 * Reklamní systém. Vrací denní čísla kampaní ve společném tvaru,
 * zbytek aplikace už neví, odkud přišla.
 */
interface AdsPlatform extends ConnectedSource
{
    /**
     * Denní čísla po kampaních. Kampaň bez zobrazení v daný den nevrací nic.
     *
     * @return Collection<int, DailyStat>
     *
     * @throws AdsApiException
     */
    public function dailyStats(AdAccount $account, Period $period): Collection;
}
