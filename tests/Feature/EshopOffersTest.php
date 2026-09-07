<?php

namespace Tests\Feature;

use App\Support\EshopOffers;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EshopOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_vsechny_nabidky_pro_eshopy_se_vykresli(): void
    {
        foreach (EshopOffers::all() as $slug => $offer) {
            $this->get('/'.$slug)
                ->assertOk()
                ->assertSee($offer['headline'], false)
                ->assertSee($offer['seo_title'].' | Taveo', false)
                ->assertSee('Časté otázky', false);
        }
    }

    public function test_stranka_nese_vlastni_meta_udaje(): void
    {
        $this->get('/mereni-pro-eshopy')
            ->assertOk()
            ->assertSee('<meta name="description" content="Opravíme měření vašeho e-shopu', false)
            ->assertSee('<link rel="canonical" href="'.url('/mereni-pro-eshopy').'">', false)
            ->assertSee('<meta property="og:title" content="Měření pro e-shopy', false)
            ->assertSee('<meta property="og:description" content="Opravíme měření vašeho e-shopu', false);
    }

    public function test_stranka_nese_strukturovana_data(): void
    {
        $this->get('/migrace-na-shoptet')
            ->assertOk()
            ->assertSee('"@type":"Service"', false)
            ->assertSee('"serviceType":"Migrace e-shopu na Shoptet"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"ProfessionalService"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_odpovedi_z_faq_jsou_na_strance_videt(): void
    {
        $response = $this->get('/rozvoj-eshopu');

        foreach (EshopOffers::all()['rozvoj-eshopu']['faq'] as $item) {
            $response->assertSee($item['question'], false)->assertSee($item['answer'], false);
        }
    }

    public function test_stranka_odkazuje_na_zbyle_tri_nabidky(): void
    {
        $response = $this->get('/mereni-pro-eshopy')->assertOk();

        foreach (EshopOffers::others('mereni-pro-eshopy') as $other) {
            $response->assertSee($other['url'], false);
        }
    }

    public function test_paticka_ma_skupinu_pro_eshopy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Pro e-shopy', false)
            ->assertSee('Aplikace pro Shoptet Premium', false);
    }

    public function test_nabidky_jsou_v_sitemape(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        foreach (EshopOffers::slugs() as $slug) {
            $response->assertSee(url('/'.$slug), false);
        }
    }
}
