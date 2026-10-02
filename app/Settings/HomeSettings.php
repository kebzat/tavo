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
    public ?string $hero_eyebrow;

    public ?string $hero_line_1;

    public ?string $hero_line_2;

    public ?string $hero_line_3;

    /** Zvýrazněná (cihlová, kurzíva) část třetího řádku. */
    public ?string $hero_line_3_accent;

    public ?string $hero_perex;

    public ?string $hero_cta_primary_label;

    public ?string $hero_cta_primary_url;

    public ?string $hero_cta_secondary_label;

    public ?string $hero_cta_secondary_url;

    // Pruh s čísly pod úvodem: [{value, label}]
    public array $trust_items;

    // Problém
    public ?string $problem_eyebrow;

    public ?string $problem_title;

    public ?string $problem_perex;

    public array $problem_points;

    // Dvě situace
    public ?string $situations_title;

    public array $situations;

    // Služby
    public ?string $services_title;

    public ?string $services_perex;

    // Nejnovější projekt za „Dvěma situacemi“ (ID reference, prázdné = sekce se nezobrazí)
    public ?int $latest_case_id;

    // Reference
    public ?string $cases_title;

    public ?string $cases_link_label;

    // Proč to dává smysl (kruh)
    public ?string $loop_title;

    public ?string $loop_perex;

    public array $loop_items;

    // Lidé
    public ?string $founders_title;

    public ?string $founders_perex;

    public ?string $founders_intro;

    // Lidé: blok o specialistech kolem Pavla a Toma
    public ?string $founders_network_title;

    public ?string $founders_network_text;

    public array $founders_network_items;

    // Proces
    public ?string $process_title;

    // Ceník: tři formy spolupráce
    public ?string $pricing_title;

    public ?string $pricing_perex;

    public array $pricing_plans;

    // Pruh pod kartami ceníku: úvodní konzultace zdarma
    public ?string $pricing_free_title;

    public ?string $pricing_free_text;

    public ?string $pricing_free_cta_label;

    // Ceník: „Kolik hodin vlastně potřebuji?" Příklady z praxe pod kartami.
    // [{hours, price, for, items: [text], after}]
    public ?string $pricing_examples_title;

    public ?string $pricing_examples_perex;

    public array $pricing_examples;

    public ?string $pricing_examples_note;

    // Recenze klientů (#recenze), proklik z „5,0 na Googlu"
    public ?string $reviews_title;

    public ?string $reviews_perex;

    /** Odkaz na hodnocení na Googlu. Prázdné = tlačítko se nezobrazí. */
    public ?string $reviews_google_url;

    // Závěrečné CTA
    public ?string $cta_eyebrow;

    public ?string $cta_title;

    public ?string $cta_perex;

    public static function group(): string
    {
        return 'home';
    }
}
