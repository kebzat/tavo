<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Výchozí SEO hodnoty + analytika.
 * Edituje se ve Filamentu: Nastavení → SEO.
 */
class SeoSettings extends Settings
{
    public string $default_title;

    public ?string $title_suffix;

    public ?string $default_description;

    public ?string $og_image;

    /** GTM kontejner (GTM-XXXX). Načte se až po souhlasu s cookies. */
    public ?string $gtm_id;

    /** Vypnuto = do <head> se přidá noindex (pro staging). */
    public bool $indexable;

    public static function group(): string
    {
        return 'seo';
    }
}
