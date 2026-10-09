<?php

namespace Tests\Feature;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\UserRole;
use App\Filament\Tools\Resources\Proposals\Pages\CreateProposal;
use App\Filament\Tools\Resources\Proposals\Pages\EditProposal;
use App\Filament\Tools\Resources\Proposals\Pages\ListProposals;
use App\Models\Audit;
use App\Models\Client;
use App\Models\Crm\Company;
use App\Models\Proposal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use RefreshDatabase;

    private function proposal(array $attributes = []): Proposal
    {
        return Proposal::create([
            'company_name' => 'Hračky Zkouška',
            'title' => 'Co bychom udělali do Vánoc',
            'intro' => 'Prošli jsme web.',
            'prepared_at' => '2026-09-30',
            'highlights' => [['value' => '5 / 5', 'label' => 'na Heurece'], ['value' => '', 'label' => 'prázdná dlaždice']],
            'timeline' => [
                ['label' => '2016', 'title' => 'Kdysi', 'body' => 'Zelený web.'],
                ['label' => 'Návrh', 'title' => 'S námi', 'body' => 'Nový vzhled.'],
            ],
            'findings' => [
                ['tone' => 'problem', 'title' => 'Košík ukáže jen poslední kus', 'body' => 'Popis košíku.'],
                ['tone' => 'strength', 'title' => '', 'body' => 'Řádek bez nadpisu se nezobrazí.'],
            ],
            'recommendations' => [['title' => 'Osvěžit web', 'who' => 'Tom', 'body' => 'Do 14 dní.']],
            'steps' => [
                ['when' => '1. týden', 'title' => 'Refresh webu', 'body' => '', 'later' => false],
                ['when' => 'Po Vánocích', 'title' => 'Redesign', 'body' => '', 'later' => true],
            ],
            'examples' => [['placement' => 'after_recommendations', 'kind' => 'Foto a video', 'title' => 'Videa s dětmi', 'body' => 'Krátce.']],
            'principles' => [['title' => 'Nejdřív tržby', 'body' => 'Pak hezké věci.']],
            'is_public' => true,
            ...$attributes,
        ]);
    }

    public function test_adresa_je_slug_firmy(): void
    {
        $proposal = $this->proposal();
        $druha = $this->proposal();

        $this->assertSame('hracky-zkouska', $proposal->slug);
        $this->assertSame('hracky-zkouska-2', $druha->slug);
        $this->assertSame(url('/potencialni-spoluprace/hracky-zkouska'), $proposal->publicUrl());
    }

    public function test_stranka_ukaze_vsechny_sekce(): void
    {
        $this->proposal();

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSeeInOrder([
                'Co bychom udělali do Vánoc',
                '5 / 5',
                'Co jsme objevili',
                'Problém',
                'Košík ukáže jen poslední kus',
                'Kdysi, dnes a s námi',
                'Kdysi',
                'S námi',
                'Co doporučujeme',
                'Osvěžit web',
                'Videa s dětmi',
                'Akční kroky v prvních týdnech',
                'Refresh webu',
                'Potom postupně',
                'Redesign',
                'Jak k tomu přistupujeme',
                'Nejdřív tržby',
            ])
            ->assertDontSee('prázdná dlaždice')
            ->assertDontSee('Řádek bez nadpisu se nezobrazí.');
    }

    public function test_rozcestnik_vede_jen_na_sekce_ktere_na_strance_jsou(): void
    {
        $this->proposal(['principles' => null]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk()
            ->assertSeeInOrder(['href="#redesign"', 'href="#doporuceni"', 'href="#kroky"'], false)
            ->assertSee('id="redesign"', false)
            ->assertSee('Naše doporučení')
            ->assertSee('Akční plán')
            ->assertDontSee('href="#pristup"', false)
            ->assertDontSee('Obecné doporučení');
    }

    public function test_iq_hracky_maji_vybrane_tipy_a_zbytek_je_na_strance_pro_klienty(): void
    {
        $tips = collect(Proposal::firstWhere('slug', 'iq-hracky')->examples)
            ->firstWhere('title', 'Jak to vypadá, když je to zvládnuté');

        $this->assertSame([
            'Horní lišta: Venira.cz',
            'Video v galerii produktu: Venira.cz',
            'Popis produktu: běžný a prémiový (Alza.cz)',
            'Stavová lišta: Sparkys.cz',
            'Košík: Pompo.cz',
            'Košík, který prodává: Rybizak.cz',
        ], array_column($tips['items'], 'title'));
        $this->assertSame(route('pages.show', 'pro-klienty'), $tips['link_url']);

        $this->get('/pro-klienty')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSeeInOrder(['Dárek k objednávce', 'Pop-up za kontakt', 'Stránka o dopravě a platbě', 'Chytré vyhledávání', 'Nejčastěji kupováno společně', 'Drobnosti, které se vyplatí']);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('pro-klienty');
    }

    public function test_prazdna_sekce_se_nezobrazi(): void
    {
        $this->proposal(['findings' => [], 'principles' => null, 'examples' => [], 'timeline' => null]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk()
            ->assertDontSee('Co jsme objevili')
            ->assertDontSee('Kdysi, dnes a s námi')
            ->assertDontSee('Jak k tomu přistupujeme')
            ->assertSee('Co doporučujeme');
    }

    public function test_nesdilenou_stranku_vidi_jen_prihlaseny(): void
    {
        $this->proposal(['is_public' => false]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk();
    }

    public function test_otevreni_se_zapise_do_crm(): void
    {
        $company = Company::create([
            'name' => 'Hračky Zkouška',
            'segment' => CompanySegment::Eshop,
            'source' => CompanySource::Research,
            'status' => CompanyStatus::Contacted,
        ]);
        $client = Client::create(['name' => 'Hračky Zkouška', 'slug' => 'hracky-zkouska', 'crm_company_id' => $company->id]);
        $this->proposal(['client_id' => $client->id]);

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/potencialni-spoluprace/hracky-zkouska')->assertOk();
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/potencialni-spoluprace/hracky-zkouska');

        $proposal = Proposal::firstWhere('slug', 'hracky-zkouska');
        $this->assertSame(1, $proposal->view_count);
        $this->assertSame('Otevřeli nabídku spolupráce poprvé', $company->activities()->value('subject'));
    }

    public function test_odkaze_na_sdileny_audit_klienta(): void
    {
        $client = Client::create(['name' => 'Hračky Zkouška', 'slug' => 'hracky-zkouska']);
        $audit = Audit::create(['client_id' => $client->id, 'title' => 'Audit', 'is_public' => true]);
        $this->proposal(['client_id' => $client->id]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertSee($audit->publicUrl(), false)
            ->assertSee('Přečíst celý audit');
    }

    public function test_robots_zakazuje_nabidky(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /potencialni-spoluprace');
    }

    public function test_spravce_zalozi_a_upravi_nabidku_v_nastrojich(): void
    {
        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(CreateProposal::class)
            ->fillForm([
                'company_name' => 'Hračkárna Nová',
                'slug' => 'hrackarna-nova',
                'title' => 'Co bychom udělali',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposal = Proposal::firstWhere('slug', 'hrackarna-nova');
        $this->assertNotNull($proposal);
        $this->assertFalse($proposal->is_public);

        Livewire::test(ListProposals::class)->assertCanSeeTableRecords([$proposal]);
        Livewire::test(EditProposal::class, ['record' => $proposal->getRouteKey()])->assertOk();
    }

    public function test_iq_hracky_z_migrace_ma_obsah(): void
    {
        $proposal = Proposal::firstWhere('slug', 'iq-hracky');

        $this->assertNotNull($proposal);
        $this->assertSame('V čem vám můžeme pomoct?', $proposal->title);
        $this->assertSame('Stručné shrnutí', $proposal->findings_title);
        $this->assertSame(['3 z 5', '1 z 5', '2 z 5'], array_column($proposal->highlightTiles(), 'value'));
        $this->assertCount(10, $proposal->findingItems());
        $this->assertCount(8, $proposal->recommendationItems());
        $this->assertCount(8, $proposal->experienceItems());
        $this->assertNotEmpty($proposal->stepGroups()['later']);
        $this->assertSame(['Kdysi', 'Dnes', 'S námi'], array_column($proposal->timelineItems(), 'title'));

        $examples = $proposal->examplesByPlacement();
        $this->assertCount(4, $examples['after_recommendations'][0]['items']);
        $this->assertSame('video', $examples['after_recommendations'][0]['items_layout']);
        $this->assertNotEmpty($examples['after_principles']);
    }

    public function test_nalezy_se_seskupi_podle_nalehavosti(): void
    {
        $proposal = $this->proposal(['findings' => [
            ['priority' => 'later', 'title' => 'Dárkový rádce'],
            ['title' => 'Bez naléhavosti'],
            ['priority' => 'urgent', 'title' => 'Letní banner'],
            ['priority' => 'urgent', 'title' => 'Cizí odznak'],
        ]]);

        $groups = $proposal->findingGroups();
        $this->assertSame([null, 'Urgentní', 'Až bude čas'], array_column($groups, 'label'));
        $this->assertSame(['Letní banner', 'Cizí odznak'], array_column($groups[1]['items'], 'title'));

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk()
            ->assertSeeInOrder(['Bez naléhavosti', 'Urgentní', 'Letní banner', 'Cizí odznak', 'Až bude čas', 'Dárkový rádce'])
            ->assertDontSee('Důležité');
    }

    public function test_nalezy_bez_nalehavosti_zustanou_v_jednom_seznamu(): void
    {
        $groups = $this->proposal()->findingGroups();

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['label']);
    }

    public function test_le_chocolat_z_migrace_ma_obsah(): void
    {
        $proposal = Proposal::firstWhere('slug', 'le-chocolat');

        $this->assertNotNull($proposal);
        $this->assertFalse($proposal->is_public);
        $this->assertSame(['3 z 5'], array_column($proposal->highlightTiles(), 'value'));
        $this->assertSame(['Urgentní', 'Důležité', 'Až bude čas'], array_column($proposal->findingGroups(), 'label'));
        $this->assertSame(['Kdysi', 'Dnes', 'S námi'], array_column($proposal->timelineItems(), 'title'));
        $this->assertCount(6, $proposal->examplesByPlacement()['after_principles'][0]['items']);
        $this->assertNotEmpty($proposal->stepGroups()['later']);
    }

    public function test_migrace_neprepise_co_spravce_upravil(): void
    {
        $proposal = Proposal::firstWhere('slug', 'iq-hracky');
        $proposal->update(['title' => 'Vlastní nadpis', 'findings_title' => null, 'experiences' => null]);

        $migration = require database_path('migrations/2026_09_30_160000_update_iq_hracky_proposal_after_review.php');
        $migration->up();

        $proposal->refresh();
        $this->assertSame('Vlastní nadpis', $proposal->title);
        $this->assertSame('Stručné shrnutí', $proposal->findings_title);
        $this->assertCount(8, $proposal->experienceItems());
    }

    public function test_zkusenosti_zvyrazni_cisla(): void
    {
        $this->proposal(['experiences' => [
            ['text' => 'Meta Ads vrátí 3–10 Kč z koruny.', 'emphasis' => ['3–10 Kč']],
            ['text' => '', 'emphasis' => []],
        ]]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertSee('Pár zkušeností z posledních měsíců')
            ->assertSee('Meta Ads vrátí <strong class="font-extrabold text-brick whitespace-nowrap">3–10 Kč</strong> z koruny.', false);
    }

    public function test_mesicni_spoluprace_ukaze_varianty_a_doporucenou(): void
    {
        $this->proposal(['packages' => [
            ['title' => 'Údržba', 'price' => '4 900 Kč', 'period' => 'měsíčně', 'scope' => '5 hodin práce', 'features' => "Opravy\n\n  Aktualizace  "],
            ['title' => 'Rozvoj', 'price' => '10 000 Kč', 'scope' => '10–11 hodin práce', 'features' => 'Nové funkce', 'recommended' => true],
            ['title' => '', 'price' => '1 Kč'],
        ]]);

        $this->assertSame(['Opravy', 'Aktualizace'], Proposal::firstWhere('slug', 'hracky-zkouska')->packageItems()[0]['features']);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertSee('href="#spoluprace"', false)
            ->assertSeeInOrder(['Akční kroky', 'Měsíční spolupráce', 'Údržba', '4 900 Kč', '5 hodin práce', 'Rozvoj', 'Doporučujeme', '10 000 Kč'])
            ->assertDontSee('1 Kč');
    }

    public function test_bez_variant_neni_sekce_spoluprace(): void
    {
        $this->proposal();

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertDontSee('href="#spoluprace"', false)
            ->assertDontSee('id="spoluprace"', false);
    }

    public function test_video_z_disku_se_nacte_az_po_kliknuti(): void
    {
        $this->proposal(['examples' => [[
            'placement' => 'after_principles',
            'title' => 'Formát, který funguje',
            'items' => [
                ['title' => 'Rozbalování', 'video_url' => 'https://drive.google.com/file/d/1WnBwA_3Lcz8GRLc6iK2htmjU4TNtINR3/view?usp=sharing'],
                ['title' => 'Bez videa i obrázku', 'video_url' => 'https://example.com/video.mp4'],
            ],
        ]]]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertSeeInOrder(['Jak k tomu přistupujeme', 'Formát, který funguje', 'Rozbalování'])
            ->assertSee('https://drive.google.com/thumbnail?id=1WnBwA_3Lcz8GRLc6iK2htmjU4TNtINR3', false)
            ->assertSee('<template x-if="playing">', false)
            ->assertDontSee('Bez videa i obrázku');
    }

    public function test_bannery_na_vysku_jdou_do_mrizky_s_videi(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('spoluprace/banner.jpg', UploadedFile::fake()->image('banner.jpg', 338, 600)->getContent());
        Storage::disk('public')->put('spoluprace/sirokej.jpg', UploadedFile::fake()->image('sirokej.jpg', 1200, 800)->getContent());

        $video = ['title' => 'Reels', 'video_url' => 'https://drive.google.com/file/d/1WnBwA_3Lcz8GRLc6iK2htmjU4TNtINR3/view'];
        $proposal = $this->proposal(['examples' => [
            ['placement' => 'after_principles', 'title' => 'Videa a bannery', 'items' => [$video, ['title' => 'Story', 'image' => 'spoluprace/banner.jpg']]],
            ['placement' => 'after_steps', 'title' => 'Se screenshotem', 'items' => [$video, ['title' => 'Web', 'image' => 'spoluprace/sirokej.jpg']]],
        ]]);

        $examples = $proposal->examplesByPlacement();
        $this->assertSame('video', $examples['after_principles'][0]['items_layout']);
        $this->assertSame('image', $examples['after_steps'][0]['items_layout']);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk()
            ->assertSee('object-contain', false);
    }
}
