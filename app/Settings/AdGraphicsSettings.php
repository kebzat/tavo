<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Reklamní grafika od agenta: klíč ke Google Gemini (Nano Banana) pro
 * generování obrázků. Edituje se v nástrojích: Reklamy → Nastavení grafiky.
 * Klíč zůstává na webu, agent obrázky žádá přes /nastroje/api/grafika.
 */
class AdGraphicsSettings extends Settings
{
    /** API klíč z Google AI Studia. Prázdné = generování obrázků vypnuté. */
    public ?string $gemini_api_key;

    /** Model pro obrázky, Nano Banana je gemini-2.5-flash-image. */
    public string $gemini_model;

    /** Kolik obrázků smí agent vygenerovat na jedno zadání (pojistka útraty). */
    public int $max_images;

    public static function group(): string
    {
        return 'grafika';
    }

    public static function encrypted(): array
    {
        return ['gemini_api_key'];
    }

    public function imagesEnabled(): bool
    {
        return filled($this->gemini_api_key);
    }
}
