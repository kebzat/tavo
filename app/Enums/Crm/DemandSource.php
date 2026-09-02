<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasLabel;

/** Portál, ze kterého poptávka přišla. */
enum DemandSource: string implements HasLabel
{
    case ShoptetPartners = 'shoptet_partners';
    case Webtrh = 'webtrh';
    case NaVolneNoze = 'navolnenoze';
    case Upgates = 'upgates';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ShoptetPartners => 'Shoptet Partners',
            self::Webtrh => 'Webtrh',
            self::NaVolneNoze => 'Na volné noze',
            self::Upgates => 'Upgates',
            self::Other => 'Jiné',
        };
    }

    /**
     * Převod z názvu portálu, jak ho píše tabulka rešerše. Porovnává se bez
     * ohledu na velikost písmen a diakritiku, neznámý portál spadne do „Jiné".
     */
    public static function fromCsv(?string $value): self
    {
        $key = self::normalize((string) $value);

        return match ($key) {
            'shoptet partneri', 'shoptet partners', 'shoptet' => self::ShoptetPartners,
            'webtrh' => self::Webtrh,
            'na volne noze', 'navolnenoze' => self::NaVolneNoze,
            'upgates' => self::Upgates,
            default => self::tryFrom($key) ?? self::Other,
        };
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
            'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
            'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        ]);
    }

    /** Odkud firma přišla, když z poptávky založíme kartu firmy. */
    public function toCompanySource(): CompanySource
    {
        return match ($this) {
            self::ShoptetPartners => CompanySource::ShoptetDemands,
            self::Webtrh => CompanySource::Webtrh,
            self::NaVolneNoze => CompanySource::NaVolneNoze,
            self::Upgates => CompanySource::Upgates,
            self::Other => CompanySource::Other,
        };
    }
}
