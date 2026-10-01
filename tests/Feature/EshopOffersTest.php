<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\EshopOffers\Pages\CreateEshopOffer;
use App\Filament\Resources\EshopOffers\Pages\EditEshopOffer;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Models\EshopOffer;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EshopOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_migrace_prenesla_ctyri_nabidky(): void
    {
        $this->assertSame(
            ['mereni-pro-eshopy', 'aplikace-pro-shoptet-premium', 'migrace-na-shoptet', 'rozvoj-eshopu'],
            EshopOffer::ordered()->pluck('slug')->all(),
        );

        $offer = EshopOffer::firstWhere('slug', 'rozvoj-eshopu');
        $this->assertCount(2, $offer->introParagraphs());
        $this->assertCount(3, $offer->contentSections());
        $this->assertCount(2, $offer->contentSections()[0]['paragraphs']);
    }

    public function test_vsechny_nabidky_pro_eshopy_se_vykresli(): void
    {
        foreach (EshopOffer::all() as $offer) {
            $this->get('/'.$offer->slug)
                ->assertOk()
                ->assertSee($offer->headline, false)
                ->assertSee($offer->seo_title.' | Taveo', false)
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

        foreach (EshopOffer::firstWhere('slug', 'rozvoj-eshopu')->faqItems() as $item) {
            $response->assertSee($item['question'], false)->assertSee($item['answer'], false);
        }
    }

    public function test_stranka_odkazuje_na_zbyle_tri_nabidky(): void
    {
        $response = $this->get('/mereni-pro-eshopy')->assertOk();

        foreach (EshopOffer::where('slug', '!=', 'mereni-pro-eshopy')->get() as $other) {
            $response->assertSee($other->url(), false);
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

        foreach (EshopOffer::all() as $offer) {
            $response->assertSee(url('/'.$offer->slug), false);
        }
    }

    public function test_uprava_v_administraci_se_propise_na_web(): void
    {
        EshopOffer::firstWhere('slug', 'rozvoj-eshopu')->update([
            'headline' => 'Nový nadpis z administrace',
            'faq' => [],
        ]);

        $this->get('/rozvoj-eshopu')
            ->assertOk()
            ->assertSee('Nový nadpis z administrace', false)
            ->assertDontSee('Časté otázky', false)
            ->assertDontSee('"@type":"FAQPage"', false);
    }

    public function test_nezverejnena_nabidka_zmizi_z_webu(): void
    {
        EshopOffer::firstWhere('slug', 'migrace-na-shoptet')->update(['published' => false]);

        $this->get('/migrace-na-shoptet')->assertNotFound();
        $this->get('/')->assertDontSee('Migrace na Shoptet', false);
        $this->get('/sitemap.xml')->assertDontSee(url('/migrace-na-shoptet'), false);
        $this->get('/rozvoj-eshopu')->assertDontSee(url('/migrace-na-shoptet'), false);
    }

    public function test_spravce_zalozi_nabidku_v_administraci(): void
    {
        $this->spravce();

        Livewire::test(CreateEshopOffer::class)
            ->fillForm([
                'nav_label' => 'Feedy pro e-shopy',
                'slug' => 'feedy-pro-eshopy',
                'headline' => 'Feedy, které projdou',
                'intro' => "První odstavec.\n\nDruhý odstavec.",
                'cta_title' => 'Napište nám.',
                'published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('/feedy-pro-eshopy')
            ->assertOk()
            ->assertSee('Feedy, které projdou', false)
            ->assertSee('<p data-reveal class="text-perex mt-[34px] mb-0 max-w-[62ch] text-body">Druhý odstavec.</p>', false);
    }

    public function test_slug_nesmi_kolidovat_se_statickou_strankou(): void
    {
        $this->spravce();

        Livewire::test(EditEshopOffer::class, ['record' => EshopOffer::firstWhere('slug', 'rozvoj-eshopu')->getKey()])
            ->fillForm(['slug' => 'cookies'])
            ->call('save')
            ->assertHasFormErrors(['slug']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Kolize', 'slug' => 'rozvoj-eshopu'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    private function spravce(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }
}
