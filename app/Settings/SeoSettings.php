<?php

namespace App\Settings;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Absolutní URL obrázku pro sdílení. Sociální sítě relativní cestu nepřijmou.
     *
     * Hodnota může přijít ve třech tvarech: jako celá URL (obrázek z MediaLibrary),
     * jako cesta pod `public/` (soubor v repozitáři), nebo — a to je případ nahrání
     * v administraci — jako holý název souboru na disku `public`, který je veřejně
     * dostupný až pod `/storage`.
     */
    public function imageUrl(?string $image): ?string
    {
        if (blank($image)) {
            return null;
        }

        if (str_starts_with($image, 'http')) {
            return $image;
        }

        if (str_starts_with($image, '/')) {
            return url($image);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($image);
    }
}
