<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasLabel;

/**
 * Odkud čísla bereme. Reklamní systémy (Meta, Google Ads) dávají útratu
 * a konverze podle sebe, GA4 dává to, co naměřil web. Ukázková data
 * se chovají jako skutečný účet, jen čísla vymýšlí, ať jde nástroj
 * vyzkoušet bez přístupů. Vypínají se v .env (ADS_DEMO=false).
 */
enum AdPlatform: string implements HasLabel
{
    case Meta = 'meta';
    case GoogleAds = 'google_ads';
    case Ga4 = 'ga4';
    case Demo = 'demo';
    case DemoGa4 = 'demo_ga4';

    public function getLabel(): string
    {
        return match ($this) {
            self::Meta => 'Meta (Facebook, Instagram)',
            self::GoogleAds => 'Google Ads',
            self::Ga4 => 'Google Analytics 4',
            self::Demo => 'Ukázková data: reklamy',
            self::DemoGa4 => 'Ukázková data: GA4',
        };
    }

    /** Krátký název do štítků a tabulek. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Meta => 'Meta',
            self::GoogleAds => 'Google Ads',
            self::Ga4 => 'GA4',
            self::Demo => 'Ukázka',
            self::DemoGa4 => 'Ukázka GA4',
        };
    }

    /** Analytika webu: návštěvy a naměřené nákupy, žádná útrata. */
    public function isAnalytics(): bool
    {
        return in_array($this, [self::Ga4, self::DemoGa4], true);
    }

    public function isDemo(): bool
    {
        return in_array($this, [self::Demo, self::DemoGa4], true);
    }

    /** @return list<self> */
    public static function adPlatforms(): array
    {
        return array_values(array_filter(self::cases(), fn (self $platform): bool => ! $platform->isAnalytics()));
    }

    /** @return list<self> */
    public static function analyticsPlatforms(): array
    {
        return array_values(array_filter(self::cases(), fn (self $platform): bool => $platform->isAnalytics()));
    }
}
