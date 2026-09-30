<?php

namespace App\Support\Ads\Ai;

use App\Models\Client;

/** Bez klíče k Claude API. Tlačítko s návrhem úprav se neukáže. */
class NullAdsAdvisor implements AdsAdvisor
{
    public function enabled(): bool
    {
        return false;
    }

    public function advise(Client $client, array $snapshot, array $alerts): ?string
    {
        return null;
    }

    public function lastError(): ?string
    {
        return 'Claude API je vypnuté (ANTHROPIC_ENABLED).';
    }
}
