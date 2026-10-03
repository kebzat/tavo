<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Proposal;
use App\Models\Testimonial;
use App\Models\User;
use App\Settings\HomeSettings;
use App\Settings\ProposalSettings;
use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Úpravy webu z 2. 10. 2026: recenze s proklikem z „5,0 na Googlu",
 * Ceník v menu, příklady hodin v ceníku, jména na fotce zakladatelů,
 * nové bloky na /pro-klienty a společná fotka v konceptech spolupráce.
 *
 * Migrace obsahu smí jen přidávat. Testy hlídají, že stávající hodnoty
 * zůstanou a opakované spuštění nic nezdvojí.
 */
class HomeReviewsAndPricingExamplesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ContentSeeder::class);
    }

    public function test_recenze_se_stridaji_a_skryta_se_nevypise(): void
    {
        Testimonial::query()->create(['author' => 'Skrytý klient', 'text' => 'Nemá být vidět.', 'published' => false, 'order_column' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="recenze"', false)
            ->assertSee('Co o nás říkají klienti')
            ->assertSeeInOrder(['Marek Bezdíček', 'ChrudimLab', 'Miroslav Hlubuček'])
            ->assertSee('href="https://www.svetcejlonu.cz/"', false)
            ->assertSee('x-data="tavoSlider"', false)
            ->assertDontSee('Skrytý klient')
            // Recenze Pavla na Toma na společný web nepatří.
            ->assertDontSee('nad zadáním opravdu přemýšlí');
    }

    public function test_tlacitko_na_google_jen_s_odkazem(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Hodnocení na Googlu');

        $this->updateHome(fn (HomeSettings $home) => $home->reviews_google_url = 'https://g.page/r/taveo');

        $this->get('/')->assertOk()->assertSee('Hodnocení na Googlu')->assertSee('href="https://g.page/r/taveo"', false);
    }

    public function test_hodnoceni_na_googlu_vede_na_recenze(): void
    {
        $migration = require database_path('settings/2026_10_02_110000_nav_pricing_and_trust_link.php');
        $migration->up();
        app()->forgetInstance(HomeSettings::class);

        $items = app(HomeSettings::class)->trust_items;
        $google = collect($items)->firstWhere('value', '5,0 ★');

        $this->assertSame('/#recenze', $google['url']);
        $this->assertSame('hodnocení klientů na Googlu', $google['label']);
        $this->assertArrayNotHasKey('url', collect($items)->firstWhere('value', '9 let'));

        $this->get('/')->assertOk()->assertSee('href="/#recenze"', false);
    }

    public function test_cenik_v_menu_se_pripoji_jednou_a_ostatni_polozky_zustanou(): void
    {
        $site = app(SiteSettings::class);
        $site->nav_links = $before = [['label' => 'Co děláme', 'url' => '/#sluzby'], ['label' => 'Kdo jsme', 'url' => '/#lide']];
        $site->save();

        $migration = require database_path('settings/2026_10_02_110000_nav_pricing_and_trust_link.php');
        $migration->up();
        $migration->up();
        app()->forgetInstance(SiteSettings::class);

        $after = json_decode(json_encode(app(SiteSettings::class)->nav_links), true);

        $this->assertSame($before, array_slice($after, 0, count($before)));
        $this->assertSame([['label' => 'Ceník', 'url' => '/#cenik']], array_slice($after, count($before)));

        $this->get('/')->assertOk()->assertSee('href="/#cenik"', false);
    }

    public function test_priklady_hodin_v_ceniku(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Kolik hodin vlastně potřebuji?', '8 hodin měsíčně', 'Co za měsíc stihneme', 'Za tři měsíce', '16 hodin měsíčně'])
            ->assertSee('8 000 Kč / měsíc');

        $this->updateHome(fn (HomeSettings $home) => $home->pricing_examples = [['hours' => '', 'items' => ['bez rozsahu']]]);

        $this->get('/')->assertOk()->assertDontSee('Kolik hodin vlastně potřebuji?')->assertDontSee('bez rozsahu');
    }

    public function test_jmena_na_fotce_jdou_jako_lide_na_fotce(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $tags = substr($html, strpos($html, 'id="lide"'));

        // Na společné fotce stojí Tom vlevo a Pavel vpravo.
        $this->assertMatchesRegularExpression('/bg-brick\/95 text-white">\s*Tom\s*<\/span>\s*<span[^>]*bg-cream\/92 text-ink">\s*Pavel/', $tags);
    }

    public function test_pro_klienty_dostane_nove_bloky_pred_vyzvou(): void
    {
        $page = Page::updateOrCreate(['slug' => 'pro-klienty'], [
            'title' => 'Tipy pro e-shopy',
            'published' => true,
            'blocks' => [
                ['type' => 'image_text', 'data' => ['title' => 'Dárek k objednávce']],
                ['type' => 'cta', 'data' => ['title' => 'Co z toho se hodí vám?']],
            ],
        ]);

        $migration = require database_path('migrations/2026_10_02_110000_add_capabilities_to_pro_klienty_page.php');
        $migration->up();
        $migration->up();

        $blocks = $page->fresh()->blocks;
        $this->assertSame(['image_text', 'feature_cards', 'feature_cards', 'cta'], array_column($blocks, 'type'));
        $this->assertSame('Dárek k objednávce', $blocks[0]['data']['title']);

        $this->get('/pro-klienty')
            ->assertOk()
            ->assertSeeInOrder(['Dárek k objednávce', 'Počítadlo do cíle', 'Dárkové balíčky', 'Cross-selling v košíku', 'Prémiové popisky produktů', 'Reels a krátká videa', 'Co z toho se hodí vám?']);
    }

    public function test_spolecna_fotka_v_konceptu_jen_kdyz_je_nahrana(): void
    {
        $proposal = Proposal::create([
            'company_name' => 'Fotka Zkouška',
            'title' => 'Koncept',
            'is_public' => true,
        ]);

        $this->get($proposal->publicUrl())->assertOk()->assertDontSee('Komplexní marketing');

        Storage::disk('public')->put('spoluprace/tym.jpg', UploadedFile::fake()->image('tym.jpg', 1600, 1200)->getContent());
        $settings = app(ProposalSettings::class);
        $settings->photo = 'spoluprace/tym.jpg';
        $settings->photo_title = 'Komplexní marketing = cesta k úspěchu';
        $settings->save();

        $this->get($proposal->publicUrl())->assertOk()->assertSee('Komplexní marketing = cesta k úspěchu');
    }

    public function test_nove_stranky_administrace_se_otevrou(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $case = CaseStudy::query()->firstOrFail();

        $this->actingAs($user);
        $this->get('/admin/testimonials')->assertOk()->assertSee('Marek Bezdíček');
        $this->get('/admin/testimonials/create')->assertOk();
        $this->get('/admin/manage-home')->assertOk()->assertSee('Kolik hodin vlastně potřebuji?');
        $this->get('/admin/case-studies/'.$case->id.'/edit')->assertOk();
        $this->get('/nastroje/spoluprace/nastaveni')->assertOk()->assertSee('Fotka před závěrečnou výzvou');
    }

    private function updateHome(callable $change): void
    {
        $home = app(HomeSettings::class);
        $change($home);
        $home->save();
        app()->forgetInstance(HomeSettings::class);
    }
}
