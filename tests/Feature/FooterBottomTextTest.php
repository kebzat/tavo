<?php

namespace Tests\Feature;

use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Volitelný řádek pod spodní lištou patičky. Prázdná hodnota nesmí nechat
 * na webu ani prázdný wrapper — správce má právo pole nevyplnit.
 */
class FooterBottomTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_vyplneny_text_se_v_paticce_vypise(): void
    {
        $settings = app(SiteSettings::class);
        $settings->footer_bottom_text = 'Sídlo firmy: Hradec Králové';
        $settings->save();

        $this->get('/')
            ->assertOk()
            ->assertSee('Sídlo firmy: Hradec Králové', false);
    }

    public function test_prazdny_text_nevykresli_nic(): void
    {
        $settings = app(SiteSettings::class);
        $settings->footer_bottom_text = null;
        $settings->save();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('mt-5 text-[13px] leading-[1.6] text-cream/45', false);
    }

    public function test_vychozi_hodnota_z_migrace_je_prazdna(): void
    {
        $this->assertNull(app(SiteSettings::class)->footer_bottom_text);
    }
}
