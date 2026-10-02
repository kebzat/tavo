<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Provozní nastavení reklam: komu chodí ranní souhrn a kdy se rozsvítí
 * upozornění. Edituje se v panelu nástrojů: Reklamy → Nastavení.
 */
class AdsSettings extends Settings
{
    /** Komu chodí ranní souhrn reklam. Prázdné = všem účtům. */
    public array $digest_recipients;

    /** Čerpání měsíčního rozpočtu pod tímto procentem poměrné části = pomalé. */
    public int $budget_under_pct;

    /** Čerpání nad tímto procentem poměrné části = přečerpání. */
    public int $budget_over_pct;

    /** O kolik procent smí být cena za konverzi nad cílem. */
    public int $cpa_over_pct;

    /** O kolik procent smí ROAS klesnout pod cíl. */
    public int $roas_under_pct;

    /** Pokles CTR proti předchozímu týdnu v procentech, od kterého hlásíme únavu reklam. */
    public int $ctr_drop_pct;

    /** Průměrná frekvence za týden, od které hlásíme únavu reklam. */
    public float $frequency_max;

    /** Kolik dní s útratou a bez konverze stačí k podezření na rozbité měření. */
    public int $no_conversion_days;

    /** Skok útraty den na den v procentech. */
    public int $spend_spike_pct;

    /**
     * Minimum konverzí za týden, pod kterým se cena za konverzi a ROAS
     * nehodnotí. U malých rozpočtů by jinak svítilo každé výkyvnutí.
     */
    public int $min_conversions;

    /**
     * Šest čísel na kartě klienta v přehledu, klíče z MetricCatalog. Mění se
     * třemi tečkami u dlaždice a platí pro všechny.
     */
    public array $overview_tiles;

    public static function group(): string
    {
        return 'ads';
    }
}
