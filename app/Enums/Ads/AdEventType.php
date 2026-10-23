<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Druh termínu v kalendáři reklam. */
enum AdEventType: string implements HasColor, HasIcon, HasLabel
{
    /** Konec licence na UGC video nebo fotku: reklamu je potřeba vypnout nebo licenci prodloužit. */
    case LicenseEnd = 'license_end';
    case PromoStart = 'promo_start';
    case PromoEnd = 'promo_end';

    /** Příprava na sezonu nebo akci: Black Friday, Vánoce, výprodej. */
    case Preparation = 'preparation';
    case CampaignEnd = 'campaign_end';
    case WaitingForClient = 'waiting_for_client';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::LicenseEnd => 'Konec licence (UGC)',
            self::PromoStart => 'Začátek akce',
            self::PromoEnd => 'Konec akce',
            self::Preparation => 'Příprava (Black Friday, sezona)',
            self::CampaignEnd => 'Konec kampaně',
            self::WaitingForClient => 'Čekání na podklady od klienta',
            self::Other => 'Jiný termín',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LicenseEnd, self::CampaignEnd => 'danger',
            self::PromoStart, self::PromoEnd => 'success',
            self::Preparation => 'warning',
            self::WaitingForClient => 'info',
            self::Other => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::LicenseEnd => Heroicon::OutlinedFilm,
            self::PromoStart, self::PromoEnd => Heroicon::OutlinedTag,
            self::Preparation => Heroicon::OutlinedCalendarDays,
            self::CampaignEnd => Heroicon::OutlinedStopCircle,
            self::WaitingForClient => Heroicon::OutlinedInboxArrowDown,
            self::Other => Heroicon::OutlinedFlag,
        };
    }

    /** Co udělat, když termín přijde. Ukáže se v ranním přehledu. */
    public function todo(): string
    {
        return match ($this) {
            self::LicenseEnd => 'Vypnout reklamy s tímto videem, nebo s autorem prodloužit licenci.',
            self::PromoStart => 'Spustit kampaně k akci a zkontrolovat cílové stránky.',
            self::PromoEnd => 'Vypnout akční reklamy a vrátit běžné sdělení.',
            self::Preparation => 'Připravit kreativy, rozpočet a cílení.',
            self::CampaignEnd => 'Vypnout kampaň nebo domluvit pokračování.',
            self::WaitingForClient => 'Připomenout se klientovi.',
            self::Other => 'Zkontrolovat poznámku.',
        };
    }
}
