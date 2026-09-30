<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasLabel;

/**
 * Co u klienta počítáme jako konverzi. Podle toho se počítá cena za
 * konverzi a jestli dává smysl ROAS.
 */
enum PrimaryGoal: string implements HasLabel
{
    case Purchases = 'purchases';
    case Leads = 'leads';
    case Traffic = 'traffic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Nákupy (e-shop)',
            self::Leads => 'Poptávky a kontakty',
            self::Traffic => 'Návštěvnost webu',
        };
    }

    /** Jak se konverze jmenuje v dlaždici, třeba „Nákupy“. */
    public function conversionLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Nákupy',
            self::Leads => 'Poptávky',
            self::Traffic => 'Prokliky na web',
        };
    }

    /** Cena za jednu konverzi: CPA, CPL, CPC. */
    public function costLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Cena za nákup',
            self::Leads => 'Cena za poptávku',
            self::Traffic => 'Cena za proklik',
        };
    }

    /** ROAS má smysl jen tam, kde známe hodnotu konverze. */
    public function hasValue(): bool
    {
        return $this === self::Purchases;
    }
}
