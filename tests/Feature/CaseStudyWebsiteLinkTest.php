<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Odkaz na živý web projektu na detailu reference. Bez adresy se nesmí
 * vykreslit ani prázdný odkaz.
 */
class CaseStudyWebsiteLinkTest extends TestCase
{
    use RefreshDatabase;

    private function case(?string $url): CaseStudy
    {
        return CaseStudy::create([
            'title' => 'Testovací reference',
            'slug' => 'testovaci-reference',
            'published' => true,
            'website_url' => $url,
        ]);
    }

    public function test_vyplnena_adresa_se_ukaze_jako_odkaz(): void
    {
        $this->case('https://www.2e-kompresory.cz/');

        $this->get('/reference/testovaci-reference')
            ->assertOk()
            ->assertSee('href="https://www.2e-kompresory.cz/" target="_blank" rel="noopener"', false)
            ->assertSee('Podívat se na web');
    }

    public function test_bez_adresy_odkaz_neni(): void
    {
        $this->case(null);

        $this->get('/reference/testovaci-reference')
            ->assertOk()
            ->assertDontSee('Podívat se na web');
    }
}
