<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasLabel;

/** Druh zásahu v historii změn. */
enum AdChangeType: string implements HasLabel
{
    case Budget = 'budget';
    case Creative = 'creative';
    case Targeting = 'targeting';
    case Offer = 'offer';
    case Website = 'website';
    case Tracking = 'tracking';
    case CampaignStart = 'campaign_start';
    case CampaignStop = 'campaign_stop';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Budget => 'Rozpočet',
            self::Creative => 'Nová kreativa',
            self::Targeting => 'Cílení a publika',
            self::Offer => 'Nabídka, cena, sleva',
            self::Website => 'Web, pokladna, doprava',
            self::Tracking => 'Měření (pixel, konverze)',
            self::CampaignStart => 'Spuštění kampaně',
            self::CampaignStop => 'Vypnutí kampaně',
            self::Other => 'Jiná změna',
        };
    }

    /** Jedno písmeno do značky v grafu. */
    public function mark(): string
    {
        return match ($this) {
            self::Budget => 'R',
            self::Creative => 'K',
            self::Targeting => 'C',
            self::Offer => 'N',
            self::Website => 'W',
            self::Tracking => 'M',
            self::CampaignStart => '+',
            self::CampaignStop => '−',
            self::Other => '•',
        };
    }
}
