<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Filament\Tools\Livewire\AgentHandovers;
use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Models\AgentPublication;
use App\Models\User;
use App\Support\Agent\GitHubError;
use App\Support\Agent\Publisher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tlačítko Zveřejnit: hotová práce agenta → pull request → testy → main → web.
 * Falešný agent (schránka se snímky) a falešný GitHub, který si pamatuje stav.
 */
class AgentPublishTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const MERGE_SHA = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    /** Stav falešného GitHubu. */
    private array $gh = [];

    /** @var list<string> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajemstvi-pro-testy-dlouhe-aspon-32-znaku',
            'services.agent.infra_project' => 'taveo',
            'services.github.token' => 'github_pat_test',
            // Tady cesta přes GitHub. Okamžité zveřejnění: AgentInstantPublishTest.
            'services.agent.deploy_projects' => [],
        ]);

        $this->gh = [
            'branch_sha' => self::SHA,
            'pr_runs' => [['name' => 'CI', 'status' => 'completed', 'conclusion' => 'success']],
            'mergeable' => true,
            'main_runs' => [
                ['name' => 'CI', 'status' => 'completed', 'conclusion' => 'success'],
                ['name' => 'Deploy', 'status' => 'completed', 'conclusion' => 'success'],
            ],
            'ahead' => 2,
            'handover_sha' => self::SHA,
            'closed_pulls' => [],
        ];

        Http::fake(fn (Request $request) => $this->respond($request));
    }

    private function respond(Request $request)
    {
        $url = $request->url();
        $this->calls[] = $request->method().' '.preg_replace('~^https?://[^/]+~', '', $url);

        if (str_starts_with($url, 'http://agent.test')) {
            return match (true) {
                str_ends_with($url, '/api/projekty') => Http::response([
                    ['slug' => 'taveo', 'typ' => 'git', 'zdroj' => 'git@github.com:kebzat/tavo.git', 'vlakna' => []],
                    ['slug' => 'grillnor', 'typ' => 'server', 'zdroj' => '/home/taveo.cz/grillnor', 'vlakna' => []],
                    ['slug' => 'svet-cejlonu', 'typ' => 'prazdny', 'zdroj' => '', 'vlakna' => []],
                ]),
                str_ends_with($url, '/api/projekty/grillnor/predani') => Http::response([[
                    'jmeno' => 'oteviraci-doba', 'cas' => now()->subMinutes(3)->timestamp, 'nadpis' => 'Kontakt: nová otevírací doba',
                    'popis' => "# Kontakt: nová otevírací doba\n\nV patičce jsou nové hodiny.", 'snimky' => ['01-pocitac.png'], 'kody' => [],
                ]]),
                str_ends_with($url, '/api/projekty/svet-cejlonu/predani') => Http::response([[
                    'jmeno' => 'paticka', 'cas' => now()->subMinutes(3)->timestamp, 'nadpis' => 'Nová patička v Shoptetu',
                    'popis' => "# Nová patička v Shoptetu\n\nVložte do Šablona → HTML kódy → Patička.", 'snimky' => [],
                    'kody' => [['soubor' => 'paticka.html', 'obsah' => '<div class="paticka">Čaj z Cejlonu</div>']],
                ]]),
                str_ends_with($url, '/konverzace') => Http::response([]),
                str_ends_with($url, '/api/projekty/taveo/predani') => Http::response([[
                    'vetev' => 'agent/predplatne', 'jmeno' => 'predplatne', 'sha' => $this->gh['handover_sha'], 'cas' => now()->subMinutes(5)->timestamp,
                    'nadpis' => 'Nástroje: využití předplatného v hlavičce',
                    'popis' => "# Ukazatel předplatného v hlavičce\n\nUprostřed hlavičky je vidět relace a týden.",
                    'snimky' => ['01-pocitac.png', '02-telefon.png'],
                ]]),
                str_contains($url, '/predani/predplatne/01-pocitac.png') => Http::response("\x89PNG-obrazek", 200, ['Content-Type' => 'image/png']),
                default => Http::response(['ok' => true]),
            };
        }

        $path = parse_url($url, PHP_URL_PATH);

        return match (true) {
            str_contains($path, '/compare/') => Http::response([
                'ahead_by' => $this->gh['ahead'], 'behind_by' => 0,
                'files' => [['filename' => 'resources/views/x.blade.php'], ['filename' => 'infra/agent/terminal/server.js']],
                'commits' => [['commit' => ['message' => "Ukazatel předplatného\n\nPodrobnosti"]]],
            ]),
            str_contains($path, '/branches/') => $this->gh['branch_sha'] ? Http::response(['commit' => ['sha' => $this->gh['branch_sha']]]) : Http::response([], 404),
            $request->method() === 'GET' && str_ends_with($path, '/pulls') => Http::response(($request['state'] ?? null) === 'closed' ? $this->gh['closed_pulls'] : []),
            $request->method() === 'POST' && str_ends_with($path, '/pulls') => Http::response(['number' => 42, 'node_id' => 'PR_42']),
            str_ends_with($path, '/pulls/42') || str_ends_with($path, '/pulls/43') => Http::response(['mergeable' => $this->gh['mergeable'], 'merged' => false]),
            str_ends_with($path, '/merge') => Http::response(['sha' => self::MERGE_SHA, 'merged' => true]),
            str_ends_with($path, '/actions/runs') => Http::response(['workflow_runs' => array_map(
                fn (array $run) => $run + ['html_url' => 'https://github.com/run', 'updated_at' => now()->subMinutes(5)->toIso8601String()],
                ($request['head_sha'] ?? null) === self::MERGE_SHA ? $this->gh['main_runs'] : $this->gh['pr_runs'],
            )]),
            str_contains($path, '/git/refs/heads/') => Http::response([], 204),
            str_ends_with($path, '/graphql') => Http::response(['data' => ['revertPullRequest' => ['revertPullRequest' => [
                'number' => 43, 'id' => 'PR_43', 'headRefName' => 'revert-42-agent/predplatne', 'headRefOid' => 'cccccccccccccccccccccccccccccccccccccccc',
            ]]]]),
            default => Http::response(['message' => 'neznámé'], 500),
        };
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']);
    }

    private function called(string $needle): bool
    {
        return collect($this->calls)->contains(fn (string $call) => str_contains($call, $needle));
    }

    public function test_hotova_prace_ukaze_nadpis_snimky_a_varovani_u_nastaveni_serveru(): void
    {
        $item = app(Publisher::class)->handovers('taveo')[0];

        $this->assertSame('Ukazatel předplatného v hlavičce', $item['title']);
        $this->assertSame(['01-pocitac.png', '02-telefon.png'], $item['images']);
        $this->assertSame(['infra/agent/terminal/server.js'], $item['sensitive']);
        $this->assertSame(2, $item['files']);
        $this->assertTrue($item['on_github']);
    }

    public function test_co_uz_main_ma_se_neukazuje(): void
    {
        $this->gh['ahead'] = 0;

        $this->assertSame([], app(Publisher::class)->handovers('taveo'));
    }

    public function test_uprava_sloucena_squashem_na_githubu_zmizi_i_ze_schranky(): void
    {
        $this->gh['closed_pulls'] = [['number' => 3, 'merged_at' => '2026-10-23T08:00:00Z', 'head' => ['sha' => self::SHA]]];

        $this->assertSame([], app(Publisher::class)->handovers('taveo'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/projekty/taveo/predani/predplatne') && $r['sha'] === self::SHA);
    }

    public function test_zavreny_neslouceny_pull_request_upravu_neschova(): void
    {
        $this->gh['closed_pulls'] = [['number' => 3, 'merged_at' => null, 'head' => ['sha' => self::SHA]]];

        $this->assertCount(1, app(Publisher::class)->handovers('taveo'));
    }

    public function test_zverejneni_projde_testy_slouci_a_nasadi(): void
    {
        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());
        $publication->refresh();

        $this->assertSame(PublicationStatus::Deploying, $publication->status);
        $this->assertSame(42, $publication->pr_number);
        $this->assertSame(self::MERGE_SHA, $publication->merge_sha);
        $this->assertTrue($this->called('PUT /repos/kebzat/tavo/pulls/42/merge'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/merge') && $r['sha'] === self::SHA && $r['merge_method'] === 'squash');

        // Po sloučení: větev pryč z GitHubu i ze schránky, služba agenta se aktualizuje.
        $this->assertTrue($this->called('DELETE /repos/kebzat/tavo/git/refs/heads/agent/predplatne'));
        $this->assertTrue($this->called('DELETE /api/projekty/taveo/predani/predplatne'));
        $this->assertTrue($this->called('POST /api/aktualizovat'));

        $this->artisan('agent:zverejneni')->assertSuccessful();

        $this->assertSame(PublicationStatus::Done, $publication->refresh()->status);
        $this->assertNotNull($publication->finished_at);
    }

    public function test_bez_vetve_na_githubu_se_ceka(): void
    {
        $this->gh['branch_sha'] = null;

        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());

        $this->assertSame(PublicationStatus::Waiting, $publication->refresh()->status);
        $this->assertFalse($this->called('POST /repos/kebzat/tavo/pulls'));
    }

    public function test_dokud_testy_bezi_nic_se_neslucuje(): void
    {
        $this->gh['pr_runs'] = [['name' => 'CI', 'status' => 'in_progress', 'conclusion' => null]];

        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());

        $this->assertSame(PublicationStatus::Testing, $publication->refresh()->status);
        $this->assertFalse($this->called('/merge'));
    }

    public function test_neuspesne_testy_zastavi_zverejneni(): void
    {
        $this->gh['pr_runs'] = [['name' => 'CI', 'status' => 'completed', 'conclusion' => 'failure']];

        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());

        $this->assertSame(PublicationStatus::TestsFailed, $publication->refresh()->status);
        $this->assertStringContainsString('Neprošlo: CI', $publication->error);
        $this->assertFalse($this->called('/merge'));
    }

    public function test_konflikt_s_main_se_neslucuje(): void
    {
        $this->gh['mergeable'] = false;

        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());

        $this->assertSame(PublicationStatus::Conflict, $publication->refresh()->status);
        $this->assertFalse($this->called('/merge'));
    }

    public function test_selhane_nasazeni_se_ukaze(): void
    {
        $this->gh['main_runs'][1]['conclusion'] = 'failure';

        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());
        app(Publisher::class)->advance($publication->refresh());

        $this->assertSame(PublicationStatus::Failed, $publication->refresh()->status);
        $this->assertStringContainsString('Nasazení na web selhalo', $publication->error);
    }

    public function test_zverejni_se_jen_zkontrolovany_commit(): void
    {
        $this->expectException(GitHubError::class);

        app(Publisher::class)->start('taveo', 'predplatne', str_repeat('d', 40), $this->spravce());
    }

    public function test_dvojklik_zalozi_jedno_zverejneni(): void
    {
        $this->gh['pr_runs'] = [['name' => 'CI', 'status' => 'in_progress', 'conclusion' => null]];
        $user = $this->spravce();

        $first = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $user);
        $second = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $user);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, AgentPublication::count());
    }

    public function test_vraceni_zalozi_opacny_pull_request_a_jde_stejnou_cestou(): void
    {
        $user = $this->spravce();
        $original = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $user);
        app(Publisher::class)->advance($original->refresh());

        $revert = app(Publisher::class)->revert($original->refresh(), $user);

        $this->assertTrue($revert->reverts->is($original));
        $this->assertSame('Vrácení: Ukazatel předplatného v hlavičce', $revert->title);
        $this->assertSame(PublicationStatus::Deploying, $revert->refresh()->status);
        $this->assertTrue($this->called('PUT /repos/kebzat/tavo/pulls/43/merge'));
    }

    public function test_tlacitko_v_nastrojich_spusti_zverejneni(): void
    {
        $this->gh['pr_runs'] = [['name' => 'CI', 'status' => 'in_progress', 'conclusion' => null]];

        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->assertSee('Ukazatel předplatného v hlavičce')
            ->assertSee('2 snímky')
            ->assertSee('Mění nastavení serveru')
            ->callAction(TestAction::make('publish')->arguments(['name' => 'predplatne', 'sha' => self::SHA]))
            ->assertNotified('Zveřejňuje se')
            ->assertSee('Testy');

        $publication = AgentPublication::sole();
        $this->assertSame('Pavel', $publication->user->name);
        $this->assertSame(PublicationStatus::Testing, $publication->status);
    }

    public function test_bez_tokenu_jde_jen_zkontrolovat(): void
    {
        config(['services.github.token' => null]);

        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->assertSee('chybí')
            ->assertActionDisabled(TestAction::make('publish')->arguments(['name' => 'predplatne', 'sha' => self::SHA]));
    }

    public function test_snimek_vidi_jen_prihlaseny_do_nastroju(): void
    {
        $url = route('filament.tools.agent.snimek', ['project' => 'taveo', 'name' => 'predplatne', 'file' => '01-pocitac.png']);

        $this->get($url)->assertRedirect();

        $this->actingAs($this->spravce())->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get(route('filament.tools.agent.snimek', ['project' => 'taveo', 'name' => 'predplatne', 'file' => '..%2F.env']))->assertNotFound();
    }

    public function test_bez_automatickeho_nasazeni_je_uprava_jen_sloucena(): void
    {
        $this->gh['main_runs'] = [['name' => 'CI', 'status' => 'completed', 'conclusion' => 'success']];

        $publication = app(Publisher::class)->start('taveo', 'predplatne', self::SHA, $this->spravce());
        $this->travel(4)->minutes();
        app(Publisher::class)->advance($publication->refresh());

        $this->assertSame(PublicationStatus::Merged, $publication->refresh()->status);
        $this->assertSame('Sloučeno, bez nasazení', $publication->status->getLabel());
    }

    public function test_zivy_web_kartu_nema(): void
    {
        Livewire::actingAs($this->spravce())
            ->test(AgentProject::class, ['project' => 'grillnor'])
            ->assertOk()
            ->assertDontSeeLivewire('tools.agent-handovers');

        Livewire::actingAs($this->spravce())
            ->test(AgentProject::class, ['project' => 'svet-cejlonu'])
            ->assertSeeLivewire('tools.agent-handovers');
    }

    public function test_podklady_pro_shoptet_jdou_zkopirovat_a_odskrtnout(): void
    {
        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'svet-cejlonu', 'type' => 'prazdny'])
            ->assertSee('Hotové podklady')
            ->assertSee('1 kód k vložení')
            ->mountAction(TestAction::make('review')->arguments(['name' => 'paticka', 'sha' => null]))
            ->assertMountedActionModalSee(['Zkopírovat', 'Čaj z Cejlonu', 'Šablona → HTML kódy'])
            ->callMountedAction()
            ->assertNotified('Hotovo');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/projekty/svet-cejlonu/predani/paticka') && ! isset($r['sha']));
    }

    public function test_stav_testu_z_behu_github_actions(): void
    {
        $push = fn (string $conclusion, string $status = 'completed') => ['name' => 'CI', 'event' => 'push', 'status' => $status, 'conclusion' => $conclusion ?: null];
        $pr = fn (string $conclusion, string $status = 'completed') => ['name' => 'CI', 'event' => 'pull_request', 'status' => $status, 'conclusion' => $conclusion ?: null];

        // Větev je aktuální s main: stačí testy z odeslání větve, i když pull request ještě běží.
        $this->assertSame('passed', Publisher::testState([$push('success'), $pr('', 'in_progress')], behind: 0));
        // Větev je pozadu: platí jen pull request, ten testuje výsledek sloučení.
        $this->assertNull(Publisher::testState([$push('success')], behind: 3));
        $this->assertSame('running', Publisher::testState([$push('success'), $pr('', 'in_progress')], behind: 3));
        $this->assertSame('failed', Publisher::testState([$push('failure')], behind: 0));
        // Nasazení se do testů nepočítá.
        $this->assertSame('passed', Publisher::testState([$push('success'), ['name' => 'Deploy', 'event' => 'push', 'status' => 'completed', 'conclusion' => 'failure']], behind: 0));
    }

    public function test_karta_ukaze_testy_predem_a_po_dokonceni_historii_dole(): void
    {
        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->assertSee('Testy prošly')
            ->callAction(TestAction::make('publish')->arguments(['name' => 'predplatne', 'sha' => self::SHA]))
            ->assertNotified('Zveřejňuje se');

        $publication = AgentPublication::sole();
        app(Publisher::class)->advance($publication->refresh());
        $this->assertSame(PublicationStatus::Done, $publication->refresh()->status);

        // Hotové už není nahoře v průběhu, jen drobně v historii (sloučený pull request).
        $this->gh['closed_pulls'] = [['number' => 42, 'merged_at' => now()->toIso8601String(), 'head' => ['sha' => self::SHA]]];

        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->assertDontSee('Nasazení')
            ->assertDontSee('Zkontrolovat')
            ->assertSeeInOrder(['Ukazatel předplatného v hlavičce', 'Na webu', 'Vrátit']);
    }

    public function test_s_neuspesnymi_testy_nejde_zverejnit(): void
    {
        $this->gh['pr_runs'] = [['name' => 'CI', 'status' => 'completed', 'conclusion' => 'failure']];

        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->assertSee('Testy neprošly')
            ->assertActionDisabled(TestAction::make('publish')->arguments(['name' => 'predplatne', 'sha' => self::SHA]));
    }
}
