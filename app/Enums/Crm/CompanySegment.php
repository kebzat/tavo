<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasLabel;

/** Skupina firem, na kterou míří jedna a tatáž nabídka i argumentace. */
enum CompanySegment: string implements HasLabel
{
    case Local = 'local';
    case DentalHealth = 'dental_health';
    case Svj = 'svj';
    case Conference = 'conference';
    case Eshop = 'eshop';
    case Agency = 'agency';
    case FormerClient = 'former_client';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => 'Lokální firma',
            self::DentalHealth => 'Zubní / zdraví',
            self::Svj => 'SVJ / správa',
            self::Conference => 'Konference',
            self::Eshop => 'E-shop',
            self::Agency => 'Agentura',
            self::FormerClient => 'Bývalý klient',
            self::Other => 'Jiné',
        };
    }

    /**
     * Převod z hlavičky CSV z rešerše. Klíč je text ve sloupci `segment`,
     * porovnává se bez ohledu na velikost písmen a okolní mezery.
     *
     * @return array<string, self>
     */
    public static function csvMap(): array
    {
        return [
            'lokální firma' => self::Local,
            'zubní / zdraví' => self::DentalHealth,
            'svj / správa' => self::Svj,
            'konference' => self::Conference,
            'e-shop' => self::Eshop,
            'agentura' => self::Agency,
            'bývalý klient' => self::FormerClient,
        ];
    }

    /** Najde segment podle textu z CSV. Neznámý text spadne do „Jiné". */
    public static function fromCsv(?string $value): self
    {
        $key = mb_strtolower(trim((string) $value));

        return self::csvMap()[$key] ?? self::tryFrom($key) ?? self::Other;
    }
}
