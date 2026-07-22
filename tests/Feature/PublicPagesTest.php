<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_homepage_zobrazi_obsah_z_nastaveni_a_databaze(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Weby, e-shopy', false)
            ->assertSee('Co umíme', false)
            ->assertSee('Rodinný e-shop, který přestal růst', false)
            ->assertSee('Jak spolu pracujeme', false);
    }

    public function test_vypis_referenci_a_filtr_podle_kategorie(): void
    {
        $this->get('/reference')
            ->assertOk()
            ->assertSee('Rodinný e-shop, který přestal růst', false)
            ->assertSee('Landing page pro sezónní kampaň', false);

        $this->get('/reference?kategorie=e-commerce')
            ->assertOk()
            ->assertSee('Rodinný e-shop, který přestal růst', false)
            ->assertDontSee('Landing page pro sezónní kampaň', false);
    }

    public function test_detail_reference(): void
    {
        $this->get('/reference/rodinny-eshop')
            ->assertOk()
            ->assertSee('Výchozí stav', false)
            ->assertSee('+41 %', false);
    }

    public function test_neverejna_reference_vrati_404(): void
    {
        CaseStudy::where('slug', 'rodinny-eshop')->update(['published' => false]);

        $this->get('/reference/rodinny-eshop')->assertNotFound();
    }

    public function test_detail_sluzby(): void
    {
        $this->get('/sluzby/weby-a-eshopy')
            ->assertOk()
            ->assertSee('Co konkrétně stavíme', false);
    }

    public function test_sluzba_bez_detailni_stranky_vrati_404(): void
    {
        $this->get('/sluzby/marketing')->assertNotFound();
    }

    public function test_staticka_stranka(): void
    {
        $this->get('/cookies')
            ->assertOk()
            ->assertSee('Nezbytné cookies', false);
    }

    public function test_neexistujici_stranka_vrati_404(): void
    {
        $this->get('/tady-nic-neni')->assertNotFound();
    }

    public function test_sitemap_obsahuje_vsechny_verejne_adresy(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee('/reference/rodinny-eshop', false);
        $response->assertSee('/sluzby/weby-a-eshopy', false);

        foreach (Page::published()->pluck('slug') as $slug) {
            $response->assertSee('/'.$slug, false);
        }
    }

    public function test_robots_povoluje_indexaci_a_odkazuje_na_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('sitemap.xml');
    }

    public function test_nezverejnena_sluzba_neni_v_sitemap(): void
    {
        Service::where('slug', 'weby-a-eshopy')->update(['published' => false]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/sluzby/weby-a-eshopy', false);
    }
}
