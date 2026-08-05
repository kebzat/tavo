<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Settings\SeoSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_verejny_web_pusti_roboty_dal_a_povoli_velky_nahled(): void
    {
        $settings = app(SeoSettings::class);
        $settings->indexable = true;
        $settings->save();

        // Bez max-image-preview:large ukáže Google u odkazu jen malou miniaturu.
        $this->get('/')
            ->assertOk()
            ->assertSee('name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1"', false);
    }

    public function test_vypnute_indexovani_roboty_odmitne(): void
    {
        $settings = app(SeoSettings::class);
        $settings->indexable = false;
        $settings->save();

        $this->get('/')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("Disallow: /\n", false);
    }

    public function test_obrazek_pro_sdileni_nese_rozmery_i_popisek(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('seo/sdileni.jpg', UploadedFile::fake()->image('s.jpg', 1200, 630)->get());

        $settings = app(SeoSettings::class);
        $settings->og_image = 'seo/sdileni.jpg';
        $settings->save();

        // Bez rozměrů vykreslí Facebook napoprvé odkaz bez obrázku.
        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:image:width" content="1200"', false)
            ->assertSee('property="og:image:height" content="630"', false)
            ->assertSee('property="og:image:alt"', false)
            ->assertSee('name="twitter:image"', false);
    }

    public function test_reference_pouzije_ke_sdileni_vlastni_nahled(): void
    {
        Storage::fake('public');

        $case = CaseStudy::query()->firstOrFail();
        $case->addMedia(UploadedFile::fake()->image('nahled.jpg', 1200, 900))
            ->toMediaCollection(CaseStudy::MEDIA_THUMB);

        // Storage::fake vrací relativní cestu; na ostrém disku z ní `imageUrl()`
        // udělá absolutní URL (viz test výš), tady jde o to, že se použije
        // náhled reference, a ne obecný obrázek z nastavení.
        $this->get('/reference/'.$case->slug)
            ->assertOk()
            ->assertSee('property="og:image" content="/storage/'.$case->refresh()->thumbPath().'"', false);
    }

    public function test_stranka_ma_barvu_listy_prohlizece(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="theme-color" content="#f4ede1"', false);
    }
}
