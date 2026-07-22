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

    public string $copyright;

    public static function group(): string
    {
        return 'site';
    }
}
