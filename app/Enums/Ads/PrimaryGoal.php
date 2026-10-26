<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasLabel;

/**
 * Co u klienta nebo skupiny kampaní počítáme jako konverzi. Podle toho se
 * počítá cena za konverzi, jestli dává smysl ROAS a jaká šablona čísel se
 * ukáže (e-shop, leady, návštěvnost, boosting, nábor).
 */
enum PrimaryGoal: string implements HasLabel
{
    case Purchases = 'purchases';
    case Leads = 'leads';
    case Traffic = 'traffic';

    /** Boosting obsahu a awareness: dosah, frekvence, CPM a interakce. */
    case Awareness = 'awareness';

    /** Nábor zaměstnanců: konverze jsou odpovědi a přihlášky (leady z formuláře nebo z webu). */
    case Recruitment = 'recruitment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Nákupy (e-shop)',
            self::Leads => 'Poptávky a kontakty',
            self::Traffic => 'Návštěvnost webu',
            self::Awareness => 'Dosah a interakce (boosting, awareness)',
            self::Recruitment => 'Nábor (uchazeči)',
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
            self::Recruitment => 'Uchazeči',
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
            self::Recruitment => 'Cena za uchazeče',
        };
    }

    /** Co přesně se jako konverze počítá, ať je jasné, o jaký typ jde. */
    public function conversionDefinition(): string
    {
        return match ($this) {
            self::Purchases => 'Nákupy = všechny nákupy připsané reklamě (Meta: omni_purchase, web i aplikace; Google Ads: konverze).',
            self::Leads => 'Poptávky = leady z formuláře na Metě i z webu (pixel). Rozpad je v číslech „Poptávky z formuláře“ a „Poptávky z webu“.',
            self::Recruitment => 'Uchazeči = leady z náborového formuláře na Metě i z webu (pixel).',
            self::Traffic => 'Prokliky na web = kliknutí na odkaz v reklamě.',
            self::Awareness => 'Interakce = reakce, komentáře, sdílení a kliknutí u příspěvku (post engagement).',
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
        return in_array($this, [self::Purchases, self::Leads, self::Recruitment], true);
    }

    /** Název šablony čísel, jak ji Pavel zná: e-shop, leady, boosting, nábor. */
    public function templateLabel(): string
    {
        return match ($this) {
            self::Purchases => 'E-shop',
            self::Leads => 'Leady',
            self::Traffic => 'Návštěvnost',
            self::Awareness => 'Boosting',
            self::Recruitment => 'Nábor',
        };
    }

    /** Konverze se počítají z leadů (poptávky, nábor). */
    public function countsLeads(): bool
    {
        return in_array($this, [self::Leads, self::Recruitment], true);
    }
}
