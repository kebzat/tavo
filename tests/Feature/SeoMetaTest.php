<?php

namespace Tests\Feature;

use App\Settings\SeoSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Obrázek pro sdílení musí být absolutní URL, jinak si ho sociální sítě nestáhnou.
 * Nahrání v administraci uloží jen název souboru na disk `public`, kde je veřejná
 * cesta pod `/storage` — na to se tu dá pozor.
 */
class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_nahrany_obrazek_pro_sdileni_ma_verejnou_url(): void
    {
        $settings = app(SeoSettings::class);
        $settings->og_image = 'seo/sdileni.jpg';
        $settings->save();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('property="og:image" content="'.url('/storage/seo/sdileni.jpg').'"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false);
    }

    public function test_obrazek_zadany_celou_url_se_nepredelava(): void
    {
        $settings = app(SeoSettings::class);
        $settings->og_image = 'https://cdn.example.com/sdileni.jpg';
        $settings->save();

        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:image" content="https://cdn.example.com/sdileni.jpg"', false);
    }

    public function test_bez_obrazku_se_znacka_nevypisuje(): void
    {
        $settings = app(SeoSettings::class);
        $settings->og_image = null;
        $settings->save();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('og:image', false)
            ->assertSee('name="twitter:card" content="summary"', false);
    }
}
