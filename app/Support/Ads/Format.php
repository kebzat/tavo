<?php

namespace App\Support\Ads;

/**
 * Česká čísla pro dlaždice, tabulky a e-maily. Nezlomitelná mezera mezi
 * tisíci i před jednotkou, ať se „12 345 Kč“ nerozdělí na dva řádky.
 */
final class Format
{
    private const NBSP = "\u{00A0}";

    public static function money(?float $value, string $currency = 'CZK', int $decimals = 0): string
    {
        if ($value === null) {
            return '–';
        }

        $symbol = match (strtoupper($currency)) {
            'CZK' => 'Kč',
            'EUR' => '€',
            'USD' => '$',
            default => strtoupper($currency),
        };

        return self::number($value, $decimals).self::NBSP.$symbol;
    }

    /** Cena za konverzi nebo proklik. Pod stovkou s haléři, jinak zaokrouhleně. */
    public static function unitPrice(?float $value, string $currency = 'CZK'): string
    {
        if ($value === null) {
            return '–';
        }

        return self::money($value, $currency, $value < 100 ? 2 : 0);
    }

    public static function number(?float $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '–';
        }

        return number_format($value, $decimals, ',', self::NBSP);
    }

    /** Počet, který může být zlomkový (Meta vrací konverze celé, jiné systémy i poloviny). */
    public static function count(?float $value): string
    {
        if ($value === null) {
            return '–';
        }

        return self::number($value, floor($value) == $value ? 0 : 1);
    }

    public static function percent(?float $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '–';
        }

        return self::number($value, $decimals).self::NBSP.'%';
    }

    public static function roas(?float $value): string
    {
        if ($value === null) {
            return '–';
        }

        return self::number($value, 2).'×';
    }

    /** „+12 %“, „−8 %“. Minus je typografický, ne spojovník. */
    public static function change(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $rounded = (int) round($value);

        return match (true) {
            $rounded > 0 => '+'.$rounded.self::NBSP.'%',
            $rounded < 0 => '−'.abs($rounded).self::NBSP.'%',
            default => '0'.self::NBSP.'%',
        };
    }
}
