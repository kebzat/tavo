<?php

namespace Tests\Feature;

use App\Settings\SeoSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    private function configure(array $values): void
    {
        $seo = app(SeoSettings::class);
        $seo->fill(['gtm_id' => null, 'ga4_id' => null, 'clarity_id' => null, 'meta_pixel_id' => null])->fill($values);
        $seo->save();
    }

    public function test_bez_merici_kodu_web_nema_cookie_listu_ani_consent_mode(): void
    {
        $this->configure([]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('__tavoTracking', false)
            ->assertDontSee('Nastavení cookies', false)
            ->assertDontSee('Přijmout vše', false);
    }

    public function test_merici_kody_se_predaji_do_js_a_google_zacina_na_zamitnuto(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123', 'clarity_id' => 'yql4xqp3ev', 'meta_pixel_id' => '3060467497620222']);

        $this->get('/')
            ->assertOk()
            ->assertSee("analytics_storage: 'denied'", false)
            ->assertSee('"ga4":"G-ABC123"', false)
            ->assertSee('"clarity":"yql4xqp3ev"', false)
            ->assertSee('"metaPixel":"3060467497620222"', false)
            ->assertSee('Přijmout vše', false)
            ->assertSee('Odmítnout', false)
            ->assertSee('Analytické', false)
            ->assertSee('Marketingové', false)
            ->assertSee('Nastavení cookies', false);
    }

    public function test_zadny_merici_kod_se_nenacte_primo_ze_serveru(): void
    {
        $this->configure(['ga4_id' => 'G-ABC123', 'clarity_id' => 'yql4xqp3ev', 'meta_pixel_id' => '3060467497620222']);

        // <noscript> pixel od Mety by poslal PageView bez souhlasu.
        $this->get('/')
            ->assertDontSee('facebook.com/tr', false)
            ->assertDontSee('connect.facebook.net', false)
            ->assertDontSee('clarity.ms/tag', false)
            ->assertDontSee('googletagmanager.com/gtag', false);
    }

    public function test_jen_pixel_nenabizi_analytickou_kategorii(): void
    {
        $this->configure(['meta_pixel_id' => '3060467497620222']);

        $this->get('/')
            ->assertSee('Marketingové', false)
            ->assertDontSee('Google Analytics a Microsoft Clarity. Ukážou', false);
    }
}
