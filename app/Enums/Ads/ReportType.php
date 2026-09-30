<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasLabel;

enum ReportType: string implements HasLabel
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weekly => 'Týdenní',
            self::Monthly => 'Měsíční',
            self::Custom => 'Vlastní období',
        };
    }

    /** Nadpis sdílené stránky a předmět e-mailu. */
    public function title(): string
    {
        return match ($this) {
            self::Weekly => 'Týdenní přehled reklam',
            self::Monthly => 'Měsíční přehled reklam',
            self::Custom => 'Přehled reklam',
        };
    }
}
