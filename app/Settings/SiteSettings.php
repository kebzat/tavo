<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Globální nastavení webu — navigace, patička, značka.
 * Edituje se ve Filamentu: Nastavení → Web.
 */
class SiteSettings extends Settings
{
    public string $brand_name;

    public ?string $brand_claim;

    public array $nav_links;

    public string $nav_cta_label;

    public string $nav_cta_url;

    public array $footer_columns;

    public ?string $footer_note;

    /** Volitelný řádek pod spodní lištou patičky. Prázdné = nevykreslí se. */
    public ?string $footer_bottom_text;

    public string $copyright;

    public static function group(): string
    {
        return 'site';
    }
}
