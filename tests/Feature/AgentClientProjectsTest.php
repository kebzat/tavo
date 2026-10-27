<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Pages\Agent\AgentProjects;
use App\Models\Client;
use App\Models\User;
use App\Support\Agent\ClientProjects;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

class AgentClientProjectsTest extends TestCase
{
    use RefreshDatabase;

    /** Projekty na serveru, POST je přidává. */
    private array $projects = [];

    /** Kolik projektů služba zkusila založit. */
    private int $posts = 0;

    /** Fronta na serveru stojí (odpověď služby, když root požadavek nezpracuje). */
    private bool $stuck = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.url' => 'http://agent.test', 'services.agent.secret' => 'tajne']);
        Sleep::fake();

        // Klienti z dřívějších migrací (Česká louka…) by se zakládali taky.
        Client::query()->delete();

        Http::fake(['agent.test/api/projekty' => function (Request $request) {
            if ($request->method() !== 'POST') {
                return Http::response($this->projects);
            }

            $this->posts++;

            if ($this->stuck) {
                return Http::response(['ok' => false, 'vystup' => 'Server na požadavek neodpověděl. Běží služba taveo-agent-fronta?'], 422);
            }

            $this->projects[] = ['slug' => $request['projekt'], 'typ' => $request['typ'], 'zdroj' => '', 'vlakna' => []];

            return Http::response(['ok' => true, 'vystup' => 'Projekt je připravený.']);
        }]);
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tom']);
    }

    private function project(string $slug): array
    {
        return ['slug' => $slug, 'typ' => 'prazdny', 'zdroj' => '', 'vlakna' => []];
    }

    public function test_projekty_jsou_podle_abecedy_po_strankach_v_mrizce_i_seznamu(): void
    {
        $this->projects = array_map($this->project(...), ['zeta', 'taveo', 'ceskalouka', 'alfa', 'mozek', 'beta', 'delta', 'gama', 'kapa', 'lambda', 'omega', 'sigma']);
        Client::query()->create(['name' => 'Česká louka', 'slug' => 'ceska-louka', 'agent_project' => 'ceskalouka']);

        $page = Livewire::actingAs($this->spravce())->test(AgentProjects::class)
            ->assertSet('perPage', 'all')
            ->assertSee('12 projektů')
            ->assertSeeInOrder(['alfa', 'beta', 'Česká louka', 'delta', 'gama', 'kapa', 'lambda', 'mozek', 'omega', 'sigma', 'taveo', 'zeta']);

        $page->set('perPage', '10')
            ->assertSee('1–10 z 12 projektů')
            ->assertSee('sigma')
            ->assertDontSee('zeta')
            ->call('goToPage', 2)
            ->assertSee('11–12 z 12 projektů')
            ->assertSeeInOrder(['taveo', 'zeta'])
            ->assertDontSee('sigma');

        // Jiný počet na stránku začne od první, seznam má řádky místo karet.
        $page->set('perPage', '20')
            ->assertSet('page', 1)
            ->set('display', 'list')
            ->assertSeeHtml('wire:key="list-alfa"')
            ->assertDontSeeHtml('wire:key="grid-alfa"')
            ->set('display', 'cokoli')
            ->assertSet('display', 'grid');
    }

    public function test_hledani_filtruje_pri_psani_a_enter_otevre_prvni(): void
    {
        $this->projects = array_map($this->project(...), ['zeta', 'taveo', 'ceskalouka', 'alfa', 'beta', 'delta', 'gama', 'kapa', 'lambda', 'omega', 'sigma']);
        Client::query()->create(['name' => 'Česká louka', 'slug' => 'ceska-louka', 'agent_project' => 'ceskalouka']);

        Livewire::actingAs($this->spravce())->test(AgentProjects::class)
            ->set('perPage', '10')
            ->call('goToPage', 2)
            ->set('search', 'ČESKA')
            ->assertSet('page', 1)
            ->assertSee('Nalezeno 1 z 11')
            ->assertSee('Česká louka')
            ->assertDontSee('alfa')
            ->set('search', 'louka cesk')
            ->assertSee('Česká louka')
            ->set('search', 'ta')
            ->assertSeeInOrder(['beta', 'delta', 'taveo', 'zeta'])
            ->assertDontSee('sigma')
            ->set('search', 'xyz')
            ->assertSee('Žádný agent neodpovídá hledání „xyz“')
            ->call('openFirst')
            ->assertNoRedirect()
            ->set('search', 'sig')
            ->call('openFirst')
            ->assertRedirect(AgentProject::getUrl(['project' => 'sigma']));
    }

    public function test_zalozit_agenty_klientum_po_jednom_na_pozadi(): void
    {
        $this->projects = [$this->project('svet-cejlonu'), $this->project('jmp')];
        Client::query()->create(['name' => 'Svět Cejlonu', 'slug' => 'svet-cejlonu', 'agent_project' => 'svet-cejlonu']);
        $jmp = Client::query()->create(['name' => 'JMP', 'slug' => 'jmp']);
        $la = Client::query()->create(['name' => 'La Daumé', 'slug' => 'la-daume']);
        $real = Client::query()->create(['name' => 'Realimo', 'slug' => 'realimo']);
        Client::query()->create(['name' => 'Starý', 'slug' => 'stary', 'is_archived' => true]);

        Livewire::actingAs($this->spravce())->test(AgentProjects::class)
            ->assertActionVisible('clientProjects')
            ->mountAction('clientProjects')
            ->assertMountedActionModalSee(['JMP', 'La Daumé', 'Realimo', 'projekt la-daume'])
            ->assertMountedActionModalDontSee('Starý')
            ->assertSchemaStateSet(['clients' => [$jmp->getKey(), $la->getKey(), $real->getKey()]], 'mountedActionSchema0')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Zakládá se agentů: 3')
            ->assertSee('Zakládají se agenti (3)')
            ->assertActionHidden('clientProjects');

        $this->assertSame(0, $this->posts);
        $this->artisan('agent:projekty-klientu')->assertSuccessful();

        // Existující projekt „jmp“ bez klienta se jen přiřadí, ostatní se založí s odstupem.
        $this->assertSame(['jmp', 'la-daume', 'realimo'], [$jmp->refresh()->agent_project, $la->refresh()->agent_project, $real->refresh()->agent_project]);
        $this->assertSame(2, $this->posts);
        Sleep::assertSleptTimes(2);
        $this->assertNull($la->agent_project_request);
        $this->assertTrue(ClientProjects::missing()->isEmpty());
    }

    public function test_stojici_fronta_neceka_u_kazdeho_klienta(): void
    {
        $this->stuck = true;
        $this->projects = [$this->project('mirnix-hockey')];
        Client::query()->create(['name' => 'Jiný', 'slug' => 'jiny', 'agent_project' => 'mirnix-hockey']);
        $clients = collect(['JMP' => 'jmp', 'MIRNIX Hockey' => 'mirnix-hockey', 'Naturalky' => 'naturalky', 'Realimo' => 'realimo'])
            ->map(fn (string $slug, string $name): Client => Client::query()->create(['name' => $name, 'slug' => $slug]));
        $clients->each(fn (Client $client) => app(ClientProjects::class)->request($client, 'Tom'));

        $this->artisan('agent:projekty-klientu')->assertSuccessful();

        // JMP narazí na frontu, další se už nezkouší. MIRNIX má projekt jiného klienta.
        $this->assertSame(1, $this->posts);
        $this->assertSame('failed', $clients['MIRNIX Hockey']->refresh()->agent_project_request['status']);
        $this->assertStringContainsString('fronta na serveru nezakládá projekty', $clients['Naturalky']->refresh()->agent_project_request['error']);
        $this->assertNull($clients['JMP']->refresh()->agent_project);

        Livewire::actingAs($this->spravce())->test(AgentProjects::class)
            ->assertSee('Agent se nezaložil (4)')
            ->assertSee('JMP, MIRNIX Hockey, Naturalky, Realimo')
            ->assertActionVisible('clientProjects')
            ->mountAction('clientProjects')
            ->assertMountedActionModalSee('minule se nepovedlo: fronta na serveru');

        // Po spuštění fronty projde druhý pokus.
        $this->stuck = false;
        $clients->each(fn (Client $client) => app(ClientProjects::class)->request($client->refresh(), 'Tom'));
        $this->artisan('agent:projekty-klientu')->assertSuccessful();

        $this->assertSame('naturalky', $clients['Naturalky']->refresh()->agent_project);
        $this->assertStringContainsString('už patří jinému klientovi', $clients['MIRNIX Hockey']->refresh()->agent_project_request['error']);
    }

    public function test_bez_zadosti_prikaz_nic_nevola(): void
    {
        Client::query()->create(['name' => 'JMP', 'slug' => 'jmp']);

        $this->artisan('agent:projekty-klientu')->assertSuccessful();

        Http::assertNothingSent();
    }
}
