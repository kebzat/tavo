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
            ->assertSee('Stavíme weby', false)
            ->assertSee('Co děláme', false)
            ->assertSee('ChrudimLab', false)
            ->assertSee('Jak to probíhá', false);
    }

    public function test_homepage_uvadi_lokalitu_kvuli_lokalnimu_seo(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Hradec Králové', false)
            ->assertSee('"@type":"ProfessionalService"', false)
            ->assertSee('Královéhradecký kraj', false);
    }

    public function test_vypis_referenci_a_filtr_podle_kategorie(): void
    {
        $this->get('/reference')
            ->assertOk()
            ->assertSee('ChrudimLab', false)
            ->assertSee('Reklamní grafika', false);

        $this->get('/reference?kategorie=weby')
            ->assertOk()
            ->assertSee('ChrudimLab', false)
            ->assertDontSee('Reklamní grafika', false);
    }

    public function test_vypis_referenci_nenabizi_prazdne_kategorie(): void
    {
        CaseStudy::query()->update(['published' => false]);

        $this->get('/reference')
            ->assertOk()
            ->assertDontSee('kategorie=weby', false);
    }

    public function test_detail_reference(): void
    {
        $this->get('/reference/chrudimlab')
            ->assertOk()
            ->assertSee('Zadání', false)
            ->assertSee('Co jsme na projektu dělali', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_detail_reference_bez_metrik_ukaze_poznamku(): void
    {
        $this->get('/reference/chrudimlab')
            ->assertOk()
            ->assertSee('nemáme je pro web ověřená', false);
    }

    public function test_neverejna_reference_vrati_404(): void
    {
        CaseStudy::where('slug', 'chrudimlab')->update(['published' => false]);

        $this->get('/reference/chrudimlab')->assertNotFound();
    }

    public function test_detail_sluzby(): void
    {
        $this->get('/sluzby/tvorba-eshopu')
            ->assertOk()
            ->assertSee('Na čem e-shop postavíme', false)
            ->assertSee('"@type":"Service"', false);
    }

    public function test_puvodni_sluzby_z_designu_uz_nejsou_verejne(): void
    {
        $this->get('/sluzby/weby-a-eshopy')->assertNotFound();
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

        $response->assertSee('/reference/chrudimlab', false);
        $response->assertSee('/sluzby/tvorba-eshopu', false);

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
        Service::where('slug', 'tvorba-eshopu')->update(['published' => false]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/sluzby/tvorba-eshopu', false);
    }
}
