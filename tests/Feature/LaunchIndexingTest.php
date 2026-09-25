<?php

namespace Tests\Feature;

use App\Settings\SeoSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Po spuštění webu musí být homepage i robots.txt otevřené vyhledávačům.
 */
class LaunchIndexingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_web_je_po_migracich_indexovatelny(): void
    {
        $this->assertTrue(app(SeoSettings::class)->indexable);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertDontSee('noindex', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertDontSee("Disallow: /\n", false)
            ->assertSee('Sitemap:', false);
    }
}
