<?php

namespace App\Support;

use App\Settings\SeoSettings;

/**
 * Měřicí kódy webu a to, do které kategorie souhlasu patří.
 *
 * Analytické: GA4 a Microsoft Clarity. Marketingové: Meta Pixel.
 * GTM je kontejner, který může nést obojí, proto se načte po souhlasu
 * s kteroukoliv kategorií a o zbytek se postará Consent Mode.
 *
 * Samotné načítání a souhlas řeší resources/js/consent.js, sem patří jen
 * to, co vyplnil správce v Nastavení → SEO a měření.
 */
final class Tracking
{
    public function __construct(private readonly SeoSettings $seo) {}

    /**
     * Konfigurace pro consent.js. Prázdná ID vynechává, ať JS nemusí
     * rozlišovat null a prázdný řetězec.
     *
     * @return array<string, string>
     */
    public function config(): array
    {
        return array_filter([
            'gtm' => $this->clean($this->seo->gtm_id),
            'ga4' => $this->clean($this->seo->ga4_id),
            'clarity' => $this->clean($this->seo->clarity_id),
            'metaPixel' => $this->clean($this->seo->meta_pixel_id),
        ]);
    }

    public function hasAnalytics(): bool
    {
        $config = $this->config();

        return isset($config['ga4']) || isset($config['clarity']) || isset($config['gtm']);
    }

    public function hasMarketing(): bool
    {
        $config = $this->config();

        return isset($config['metaPixel']) || isset($config['gtm']);
    }

    /** Bez jediného měřicího kódu web používá jen nezbytné cookies a lišta nemá smysl. */
    public function needsConsent(): bool
    {
        return $this->hasAnalytics() || $this->hasMarketing();
    }

    private function clean(?string $id): ?string
    {
        return blank($id) ? null : trim($id);
    }
}
