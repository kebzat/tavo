<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use Illuminate\Support\Collection;

/**
 * Cokoli, co jde propojit s klientem: reklamní systém nebo analytika.
 * Umí říct, jestli máme přístup, a vyjmenovat účty, které vidíme.
 */
interface ConnectedSource
{
    public function platform(): AdPlatform;

    /** Máme vyplněný přístup? Bez něj se platforma přeskakuje. */
    public function isConfigured(): bool;

    /**
     * Účty, které nám klienti nasdíleli. Nabídka při propojování klienta.
     *
     * @return Collection<int, AccountInfo>
     *
     * @throws AdsApiException
     */
    public function accounts(): Collection;

    /** @throws AdsApiException */
    public function account(string $externalId): AccountInfo;
}
