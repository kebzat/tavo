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

    /**
     * Třídy obalového odstavce. Negativní test se o ně opírá, proto je
     * pozitivní test kotví taky — jinak by změna stylu jen tiše vypnula
     * hlídání prázdného wrapperu, místo aby shodila test.
     */
    private const WRAPPER_CLASSES = 'mt-5 text-[13px] leading-[1.6] text-cream/45';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_vyplneny_text_se_v_paticce_vypise(): void
    {
        $this->setFooterBottomText('Sídlo firmy: Hradec Králové');

        $this->get('/')
            ->assertOk()
            ->assertSee('Sídlo firmy: Hradec Králové', false)
            ->assertSee(self::WRAPPER_CLASSES, false);
    }

    public function test_prazdny_text_nevykresli_nic(): void
    {
        $this->setFooterBottomText(null);

        $this->get('/')
            ->assertOk()
            ->assertDontSee(self::WRAPPER_CLASSES, false);
    }

    public function test_vyprazdneny_text_z_administrace_nevykresli_nic(): void
    {
        // Filament pošle z vyprázdněného TextInputu prázdný řetězec, ne null.
        $this->setFooterBottomText('');

        $this->get('/')
            ->assertOk()
            ->assertDontSee(self::WRAPPER_CLASSES, false);
    }

    public function test_vychozi_hodnota_z_migrace_je_prazdna(): void
    {
        $this->assertNull(app(SiteSettings::class)->footer_bottom_text);
    }

    private function setFooterBottomText(?string $value): void
    {
        $settings = app(SiteSettings::class);
        $settings->footer_bottom_text = $value;
        $settings->save();

        // Spatie drží settings jako singleton. Bez zapomenutí instance by
        // view composer dostal tenhle samý objekt a test by prošel i tehdy,
        // kdyby se do databáze nic nezapsalo.
        app()->forgetInstance(SiteSettings::class);
    }
}
