<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Obsah homepage, sekce po sekci. Seznamy (reference, služby, proces,
 * zakladatelé) mají vlastní modely — tady jsou jen nadpisy a perexy.
 * Edituje se ve Filamentu: Nastavení → Homepage.
 */
class HomeSettings extends Settings
{
    // Hero
    public string $hero_eyebrow;

    public string $hero_line_1;

    public string $hero_line_2;

    public string $hero_line_3;

    /** Zvýrazněná (cihlová, kurzíva) část třetího řádku. */
    public string $hero_line_3_accent;

    public string $hero_perex;

    public string $hero_cta_primary_label;

    public string $hero_cta_primary_url;

    public string $hero_cta_secondary_label;

    public string $hero_cta_secondary_url;

    // Problém
    public string $problem_eyebrow;

    public string $problem_title;

    public string $problem_perex;

    public array $problem_points;

    // Dvě situace
    public string $situations_title;

    public array $situations;

    // Služby
    public string $services_title;

    public string $services_perex;

    // Reference
    public string $cases_title;

    public string $cases_link_label;

    // Proč to dává smysl (kruh)
    public string $loop_title;

    public string $loop_perex;

    public array $loop_items;

    // Lidé
    public string $founders_title;

    public string $founders_perex;

    public string $founders_intro;

    // Proces
    public string $process_title;

    // Závěrečné CTA
    public string $cta_eyebrow;

    public string $cta_title;

    public string $cta_perex;

    public static function group(): string
    {
        return 'home';
    }
}
