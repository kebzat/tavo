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

    public function test_prazdna_sekce_se_nezobrazi(): void
    {
        $this->proposal(['findings' => [], 'principles' => null, 'examples' => []]);

        $this->get('/potencialni-spoluprace/hracky-zkouska')
            ->assertOk()
            ->assertDontSee('Co jsme objevili')
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
        $this->assertCount(9, $proposal->findingItems());
        $this->assertNotEmpty($proposal->stepGroups()['later']);
    }
}
