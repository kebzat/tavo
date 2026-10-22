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

    /** Boosting obsahu a awareness: dosah, frekvence, CPM a interakce. */
    case Awareness = 'awareness';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Nákupy (e-shop)',
            self::Leads => 'Poptávky a kontakty',
            self::Traffic => 'Návštěvnost webu',
            self::Awareness => 'Dosah a interakce (boosting, awareness)',
        };
    }

    /** Jak se konverze jmenuje v dlaždici, třeba „Nákupy“. */
    public function conversionLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Nákupy',
            self::Leads => 'Poptávky',
            self::Traffic => 'Prokliky na web',
            self::Awareness => 'Interakce',
        };
    }

    /** Cena za jednu konverzi: CPA, CPL, CPC. */
    public function costLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Cena za nákup',
            self::Leads => 'Cena za poptávku',
            self::Traffic => 'Cena za proklik',
            self::Awareness => 'Cena za interakci',
        };
    }

    /** ROAS má smysl jen tam, kde známe hodnotu konverze. */
    public function hasValue(): bool
    {
        return $this === self::Purchases;
    }

    /**
     * Jde o výkon (nákupy, poptávky), nebo o dosah a návštěvnost? U dosahu
     * nedávají smysl pravidla „útrata bez konverzí“ ani cílová cena za konverzi.
     */
    public function isPerformance(): bool
    {
        return in_array($this, [self::Purchases, self::Leads], true);
    }
}
