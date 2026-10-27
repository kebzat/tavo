<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Filament\Tools\Livewire\AgentHandovers;
use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Models\AgentPublication;
use App\Models\User;
use App\Support\Agent\Publisher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Živý web (grillnor): agent pracuje v kopii, Zveřejnit ji nahraje na živý
 * web najednou (CopyPublisher). Falešná služba agenta drží stav kopie a úloh.
 */
class AgentCopyPublishTest extends TestCase
{
    use RefreshDatabase;

    private const JOB = '22222222-3333-4444-5555-666666666666';

    private array $state = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajemstvi-pro-testy-dlouhe-aspon-32-znaku',
        ]);

        $this->state = [
            'kopie' => ['existuje' => true, 'zmeny' => [['stav' => 'M', 'soubor' => 'resources/views/home.blade.php'], ['stav' => 'A', 'soubor' => 'database/settings/2026_10_23_nadpis.php']], 'migrace' => 1, 'nahled' => 'http://127.0.0.1:8090'],
            'job' => ['id' => self::JOB, 'stav' => 'nahrava', 'vystup' => '', 'konflikt' => false],
        ];

        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/api/projekty' => Http::response([['slug' => 'grillnor', 'typ' => 'server', 'zdroj' => '/home/taveo.cz/grillnor', 'vlakna' => []]]),
                $path === '/api/projekty/grillnor/konverzace' => Http::response([]),
                $path === '/api/projekty/grillnor/predani' => Http::response([[
                    'jmeno' => 'nadpis', 'cas' => now()->subMinutes(3)->timestamp, 'nadpis' => 'Nový nadpis úvodní stránky',
                    'popis' => "# Nový nadpis úvodní stránky\n\nNadpis je kratší.", 'snimky' => ['01-pocitac.png'], 'kody' => [],
                ]]),
                $path === '/api/projekty/grillnor/kopie' => Http::response($this->state['kopie']),
                $path === '/api/projekty/grillnor/kopie/bez' && ($this->state['kopie']['zmeny'] ?? []) !== [] => Http::response(['chyba' => 'V kopii jsou nezveřejněné změny. Zveřejněte je, nebo zahoďte.'], 409),
                in_array($path, ['/api/projekty/grillnor/kopie/bez', '/api/projekty/grillnor/kopie/s-kopii'], true) => Http::response(['bezKopie' => str_ends_with($path, '/bez')]),
                $request->method() === 'POST' && str_starts_with($path, '/api/projekty/grillnor/kopie/') => Http::response(['id' => self::JOB, 'stav' => 'nahrava'], 202),
                $path === '/api/projekty/grillnor/uloha/'.self::JOB => Http::response($this->state['job']),
                default => Http::response(['ok' => true]),
            };
        });
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']);
    }

    private function karta()
    {
        return Livewire::actingAs($this->spravce())->test(AgentHandovers::class, ['project' => 'grillnor', 'type' => 'server']);
    }

    public function test_karta_ukaze_co_v_kopii_ceka_a_zverejnuje_se_najednou(): void
    {
        $this->karta()
            ->assertSee('Čeká na zveřejnění: 2 změněné soubory')
            ->assertSee('Včetně změn v databázi')
            ->assertSee('Nový nadpis úvodní stránky')
            ->assertActionVisible(TestAction::make('review')->arguments(['name' => 'nadpis', 'sha' => null]))
            ->assertActionHidden(TestAction::make('publish')->arguments(['name' => 'nadpis', 'sha' => null]))
            ->assertActionHidden(TestAction::make('discard')->arguments(['name' => 'nadpis', 'sha' => null]));
    }

    public function test_zverejnit_nahraje_kopii_na_zivy_web(): void
    {
        $this->karta()
            ->callAction('publishCopy')
            ->assertNotified('Zveřejňuje se')
            ->assertSee('Nahrává se na web');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/projekty/grillnor/kopie/zverejnit'));
        $publication = AgentPublication::sole();
        $this->assertSame('kopie', $publication->kind);
        $this->assertSame('Nový nadpis úvodní stránky', $publication->title);
        $this->assertSame(PublicationStatus::Publishing, $publication->status);

        $this->state['job']['stav'] = 'hotovo';
        $this->artisan('agent:zverejneni')->assertSuccessful();

        $this->assertSame(PublicationStatus::Done, $publication->refresh()->status);
    }

    public function test_nepovedene_nahrani_rekne_proc(): void
    {
        $this->state['job'] = ['id' => self::JOB, 'stav' => 'chyba', 'vystup' => 'Chyba: web po nahrání vrací 500. Vrátit: agent vratit grillnor', 'konflikt' => false];

        $this->karta()->callAction('publishCopy');
        app(Publisher::class)->advance(AgentPublication::sole());

        $publication = AgentPublication::sole();
        $this->assertSame(PublicationStatus::Failed, $publication->status);
        $this->assertStringContainsString('vrací 500', $publication->error);
    }

    public function test_vratit_obnovi_soubory_ze_snimku_pred_zverejnenim(): void
    {
        $user = $this->spravce();
        $this->state['job']['stav'] = 'hotovo';
        $this->karta()->callAction('publishCopy');
        $original = AgentPublication::sole();
        app(Publisher::class)->advance($original);

        $revert = app(Publisher::class)->revert($original->refresh(), $user);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/kopie/vratit') && ($r->data()['cas'] ?? null) === $original->created_at->getTimestamp());
        $this->assertTrue($revert->reverts->is($original));
        $this->assertSame('kopie', $revert->kind);
    }

    private function nepovedene(): AgentPublication
    {
        return AgentPublication::create([
            'kind' => 'kopie', 'project' => 'grillnor', 'branch' => 'kopie', 'title' => 'Nový nadpis úvodní stránky',
            'status' => PublicationStatus::Failed, 'error' => 'Nepovedlo se: Neznámý požadavek.', 'finished_at' => now()->subMinutes(2),
        ]);
    }

    public function test_chyba_zustane_nahore_dokud_se_nevyresi(): void
    {
        $this->nepovedene();

        $this->karta()->assertSee('Nepovedlo se: Neznámý požadavek.');
    }

    public function test_chyba_zmizi_kdyz_dalsi_pokus_projde(): void
    {
        $this->nepovedene();
        AgentPublication::create([
            'kind' => 'kopie', 'project' => 'grillnor', 'branch' => 'kopie', 'title' => 'Nový nadpis úvodní stránky',
            'status' => PublicationStatus::Done, 'finished_at' => now()->subMinute(),
        ]);

        $this->karta()->assertDontSee('Nepovedlo se: Neznámý požadavek.');
    }

    public function test_chybu_jde_skryt(): void
    {
        $publication = $this->nepovedene();

        $this->karta()
            ->callAction(TestAction::make('dismiss')->arguments(['id' => $publication->id]))
            ->assertDontSee('Nepovedlo se: Neznámý požadavek.');

        $this->assertNotNull($publication->refresh()->dismissed_at);
    }

    public function test_zahodit_vrati_kopii_na_zivy_web(): void
    {
        $this->karta()->callAction('discardCopy')->assertNotified('Kopie se vrací na živý web');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/kopie/zahodit'));
    }

    public function test_kdyz_kopie_neni_jde_pripravit(): void
    {
        $this->state['kopie'] = ['existuje' => false, 'bezKopie' => false, 'zmeny' => [], 'migrace' => 0, 'nahled' => ''];

        $this->karta()
            ->assertSee('Kopie webu ještě není')
            ->assertSee('Pracovat bez kopie')
            ->callAction('prepareCopy')
            ->assertNotified('Kopie se připravuje');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/kopie/obnovit'));
    }

    public function test_projekt_bez_kopie_nema_pripravit_kopii_ani_zverejnit(): void
    {
        $this->state['kopie'] = ['existuje' => false, 'bezKopie' => true, 'zmeny' => [], 'migrace' => 0, 'nahled' => ''];

        $this->karta()
            ->assertSee('Bez kopie: co agent udělá, je hned na webu.')
            ->assertSee('Pracovat v kopii')
            ->assertDontSee('Kopie webu ještě není')
            ->assertDontSee('Připravit kopii')
            ->assertDontSee('Čeká na zveřejnění')
            // Předání, když ho agent i tak udělá, jde jen prohlédnout a označit jako hotové.
            ->assertSee('Nový nadpis úvodní stránky')
            ->assertActionVisible(TestAction::make('review')->arguments(['name' => 'nadpis', 'sha' => null]))
            ->assertActionVisible(TestAction::make('discard')->arguments(['name' => 'nadpis', 'sha' => null]))
            ->assertActionHidden(TestAction::make('publish')->arguments(['name' => 'nadpis', 'sha' => null]));
    }

    public function test_pracovat_bez_kopie_a_zpatky(): void
    {
        $this->state['kopie'] = ['existuje' => true, 'bezKopie' => false, 'zmeny' => [], 'migrace' => 0, 'nahled' => ''];

        $this->karta()->callAction('withoutCopy')->assertNotified('Agent pracuje bez kopie');
        $this->karta()->callAction('withCopy')->assertNotified('Agent pracuje v kopii');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/projekty/grillnor/kopie/bez'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/projekty/grillnor/kopie/s-kopii'));
    }

    public function test_bez_kopie_nejde_s_nezverejnenymi_zmenami(): void
    {
        $this->karta()
            ->assertDontSee('Pracovat bez kopie')
            ->callAction('withoutCopy')
            ->assertNotified('Nepovedlo se');
    }

    public function test_stranka_ziveho_webu_kartu_ma(): void
    {
        Livewire::actingAs($this->spravce())
            ->test(AgentProject::class, ['project' => 'grillnor'])
            ->assertOk()
            ->assertSeeLivewire('tools.agent-handovers');
    }
}
