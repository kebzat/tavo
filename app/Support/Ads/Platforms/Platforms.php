<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;

/** Rozcestník: pro každý reklamní systém a analytiku jejich implementaci. */
class Platforms
{
    public function for(AdPlatform $platform): ConnectedSource
    {
        return app(match ($platform) {
            AdPlatform::Meta => MetaAds::class,
            AdPlatform::GoogleAds => GoogleAds::class,
            AdPlatform::Ga4 => Ga4::class,
            AdPlatform::Demo => DemoAds::class,
            AdPlatform::DemoGa4 => DemoAnalytics::class,
        });
    }

    /**
     * Platformy s vyplněným přístupem, pro výběr při propojování.
     *
     * @return list<AdPlatform>
     */
    public function configured(): array
    {
        return array_values(array_filter(AdPlatform::cases(), fn (AdPlatform $platform): bool => $this->for($platform)->isConfigured()));
    }
}
