<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Filament\Tools\Livewire\AgentHandovers;
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
 * Okamžité zveřejnění taveo: služba agenta sestaví vydání a root ho nahraje
 * rovnou na web, GitHub je jen historie (InstantPublisher). Falešná služba
 * agenta drží stav úloh vydání, falešný GitHub jen to, co je k historii.
 */
class AgentInstantPublishTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const JOB = '11111111-2222-3333-4444-555555555555';

    private array $state = [];

    /** @var list<string> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajemstvi-pro-testy-dlouhe-aspon-32-znaku',
            'services.agent.infra_project' => 'taveo',
            'services.agent.deploy_projects' => ['taveo'],
            'services.github.token' => 'github_pat_test',
        ]);

        $this->state = [
            'job' => ['id' => self::JOB, 'stav' => 'nahrava', 'vystup' => '', 'konflikt' => false],
            'branch_sha' => self::SHA,
        ];

        Http::fake(fn (Request $request) => $this->respond($request));
    }

    private function respond(Request $request)
    {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH);
        $this->calls[] = $request->method().' '.$path;

        if (str_starts_with($url, 'http://agent.test')) {
            return match (true) {
                $path === '/api/projekty' => Http::response([
                    ['slug' => 'taveo', 'typ' => 'git', 'zdroj' => 'git@github.com:kebzat/tavo.git', 'vlakna' => []],
                ]),
                $path === '/api/projekty/taveo/predani' => Http::response([[
                    'vetev' => 'agent/paticka', 'jmeno' => 'paticka', 'sha' => self::SHA, 'cas' => now()->subMinutes(2)->timestamp,
                    'nadpis' => 'Patička: odkaz na Instagram', 'popis' => "# Patička s Instagramem\n\nV patičce je odkaz.", 'snimky' => ['01-pocitac.png'],
                ]]),
                $request->method() === 'POST' && $path === '/api/projekty/taveo/vydani' => Http::response(['id' => self::JOB, 'stav' => ($request->data()['nasadit'] ?? false) || ($request->data()['zpet'] ?? false) ? 'nahrava' : 'sestavuje'], 202),
                $path === '/api/projekty/taveo/vydani/'.self::JOB => Http::response($this->state['job']),
                default => Http::response(['ok' => true]),
            };
        }

        return match (true) {
            str_contains($path, '/compare/') => Http::response(['ahead_by' => 1, 'behind_by' => 0, 'files' => [['filename' => 'resources/views/footer.blade.php']], 'commits' => [['commit' => ['message' => 'Patička']]]]),
            str_contains($path, '/branches/') => $this->state['branch_sha'] ? Http::response(['commit' => ['sha' => $this->state['branch_sha']]]) : Http::response([], 404),
            $request->method() === 'GET' && str_ends_with($path, '/pulls') => Http::response([]),
            $request->method() === 'POST' && str_ends_with($path, '/pulls') => Http::response(['number' => 9, 'node_id' => 'PR_9']),
            str_ends_with($path, '/merge') => Http::response(['sha' => str_repeat('b', 40), 'merged' => true]),
            str_ends_with($path, '/graphql') => Http::response(['data' => ['revertPullRequest' => ['revertPullRequest' => [
                'number' => 10, 'id' => 'PR_10', 'headRefName' => 'revert-9-agent/paticka', 'headRefOid' => str_repeat('c', 40),
            ]]]]),
            str_contains($path, '/git/refs/heads/') => Http::response([], 204),
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

    public function test_vydani_se_sestavi_predem_jakmile_je_uprava_v_karte(): void
    {
        $item = app(Publisher::class)->handovers('taveo')[0];

        $this->assertTrue($item['instant']);
        $this->assertNull($item['tests']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/projekty/taveo/vydani') && ($r->data()['sha'] ?? null) === self::SHA && ($r->data()['nasadit'] ?? null) === false);

        // Podruhé už ne.
        app(Publisher::class)->handovers('taveo');
        $this->assertSame(1, collect($this->calls)->filter(fn ($c) => $c === 'POST /api/projekty/taveo/vydani')->count());
    }

    public function test_zverejneni_nahraje_rovnou_na_web_a_git_doplni_potom(): void
    {
        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->assertDontSee('Testy')
            ->callAction(TestAction::make('publish')->arguments(['name' => 'paticka', 'sha' => self::SHA]))
            ->assertNotified('Zveřejňuje se')
            ->assertSee('Nahrává se na web');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/vydani') && ($r->data()['nasadit'] ?? null) === true);
        $publication = AgentPublication::sole();
        $this->assertSame(PublicationStatus::Publishing, $publication->status);
        $this->assertFalse($this->called('/merge'));

        // Root nahrál: na webu, a teprve pak historie na GitHubu.
        $this->state['job'] = ['id' => self::JOB, 'stav' => 'hotovo', 'vystup' => 'Na webu za 9 s.', 'konflikt' => false];
        $this->artisan('agent:zverejneni')->assertSuccessful();

        $publication->refresh();
        $this->assertSame(PublicationStatus::Done, $publication->status);
        $this->assertSame(9, $publication->pr_number);
        $this->assertNotNull($publication->merge_sha);
        $this->assertTrue($this->called('DELETE /api/projekty/taveo/predani/paticka'));
        $this->assertTrue($this->called('POST /api/aktualizovat'));
        // Na webu už je: sloučení do main nasazení z GitHubu nespustí.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/merge') && str_contains($r->data()['commit_message'] ?? '', '[skip ci]'));

        // Zveřejněná úprava už v kartě není.
        $this->assertSame([], app(Publisher::class)->handovers('taveo'));
    }

    public function test_historie_pocka_az_vetev_dorazi_na_github(): void
    {
        $this->state['branch_sha'] = null;
        $this->state['job']['stav'] = 'hotovo';

        $publication = app(Publisher::class)->start('taveo', 'paticka', self::SHA, $this->spravce());
        app(Publisher::class)->advance($publication->refresh());

        $this->assertSame(PublicationStatus::Done, $publication->refresh()->status);
        $this->assertNull($publication->merge_sha);
        // Dokud není v historii, v kartě se znovu neukazuje.
        $this->assertSame([], app(Publisher::class)->handovers('taveo'));

        $this->state['branch_sha'] = self::SHA;
        $this->artisan('agent:zverejneni')->assertSuccessful();

        $this->assertNotNull($publication->refresh()->merge_sha);
    }

    public function test_konflikt_s_main_rekne_co_dal(): void
    {
        $this->state['job'] = ['id' => self::JOB, 'stav' => 'chyba', 'vystup' => 'Úprava se srazí s tím, co je mezitím na webu: routes/console.php', 'konflikt' => true];

        $publication = app(Publisher::class)->start('taveo', 'paticka', self::SHA, $this->spravce());
        app(Publisher::class)->advance($publication->refresh());

        $this->assertSame(PublicationStatus::Conflict, $publication->refresh()->status);
        $this->assertStringContainsString('Sluč do větve aktuální main', $publication->error);
        $this->assertFalse($this->called('/merge'));
    }

    public function test_nepovedene_nahrani_se_ukaze(): void
    {
        $this->state['job'] = ['id' => self::JOB, 'stav' => 'chyba', 'vystup' => 'Chyba: web po nahrání vrací 500', 'konflikt' => false];

        $publication = app(Publisher::class)->start('taveo', 'paticka', self::SHA, $this->spravce());
        app(Publisher::class)->advance($publication->refresh());

        $this->assertSame(PublicationStatus::Failed, $publication->refresh()->status);
        $this->assertStringContainsString('vrací 500', $publication->error);
    }

    public function test_vratit_nahraje_predchozi_verzi_a_historii_doplni_revertem(): void
    {
        $user = $this->spravce();
        $this->state['job']['stav'] = 'hotovo';
        $original = app(Publisher::class)->start('taveo', 'paticka', self::SHA, $user);
        app(Publisher::class)->advance($original->refresh());

        $this->state['job']['stav'] = 'nahrava';
        $revert = app(Publisher::class)->revert($original->refresh(), $user);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/vydani') && ($r->data()['zpet'] ?? null) === true);
        $this->assertSame(PublicationStatus::Publishing, $revert->status);

        $this->state['job']['stav'] = 'hotovo';
        app(Publisher::class)->advance($revert->refresh());

        $revert->refresh();
        $this->assertSame(PublicationStatus::Done, $revert->status);
        $this->assertSame('Vrácení: Patička s Instagramem', $revert->title);
        $this->assertSame(10, $revert->pr_number);
        $this->assertTrue($this->called('POST /graphql'));
    }

    public function test_dokud_se_jina_uprava_nahrava_dalsi_pocka(): void
    {
        AgentPublication::create([
            'project' => 'taveo', 'repo' => 'kebzat/tavo', 'branch' => 'agent/menu', 'sha' => str_repeat('d', 40),
            'title' => 'Nové menu', 'status' => PublicationStatus::Publishing, 'job_id' => self::JOB,
        ]);

        Livewire::actingAs($this->spravce())
            ->test(AgentHandovers::class, ['project' => 'taveo'])
            ->callAction(TestAction::make('publish')->arguments(['name' => 'paticka', 'sha' => self::SHA]))
            ->assertNotified('Zveřejnění se nespustilo');

        $this->assertSame(1, AgentPublication::count());
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/vydani') && ($r->data()['nasadit'] ?? null) === true);
    }

    public function test_po_vraceni_se_ceka_na_historii(): void
    {
        $original = AgentPublication::create([
            'project' => 'taveo', 'repo' => 'kebzat/tavo', 'branch' => 'agent/menu', 'sha' => str_repeat('d', 40), 'title' => 'Nové menu',
            'status' => PublicationStatus::Done, 'job_id' => 'uloha-1', 'pr_number' => 8, 'pr_node_id' => 'PR_8', 'merge_sha' => str_repeat('e', 40),
        ]);
        $revert = AgentPublication::create([
            'project' => 'taveo', 'repo' => 'kebzat/tavo', 'branch' => 'agent/menu', 'title' => 'Vrácení: Nové menu',
            'status' => PublicationStatus::Done, 'job_id' => 'uloha-2', 'reverts_id' => $original->id,
        ]);

        try {
            app(Publisher::class)->start('taveo', 'paticka', self::SHA, $this->spravce());
            $this->fail('Zveřejnění mělo počkat na historii vrácení.');
        } catch (GitHubError $e) {
            $this->assertStringContainsString('Vrácení: Nové menu', $e->getMessage());
        }

        // Vrácení dorazilo do main: už nic nebrání.
        $revert->update(['merge_sha' => str_repeat('f', 40)]);
        $this->assertSame(PublicationStatus::Publishing, app(Publisher::class)->start('taveo', 'paticka', self::SHA, $this->spravce())->status);
    }

    public function test_bez_tokenu_githubu_jde_zverejnit_jen_bez_historie(): void
    {
        config(['services.github.token' => null]);
        $this->state['job']['stav'] = 'hotovo';

        $publication = app(Publisher::class)->start('taveo', 'paticka', self::SHA, $this->spravce());
        app(Publisher::class)->advance($publication->refresh());

        $this->assertSame(PublicationStatus::Done, $publication->refresh()->status);
        $this->assertNull($publication->merge_sha);
        $this->assertFalse($this->called('/pulls'));
    }
}
