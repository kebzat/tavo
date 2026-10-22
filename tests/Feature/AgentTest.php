<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Pages\Agent\AgentProjects;
use App\Filament\Tools\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'tajemstvi-pro-testy-dlouhe-aspon-32-znaku';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => self::SECRET,
            'services.agent.ws_url' => null,
            'app.url' => 'https://taveo.cz',
        ]);
    }

    private function projekty(array $vlakna = []): array
    {
        return [
            ['slug' => 'grillnor', 'typ' => 'server', 'zdroj' => '/home/taveo.cz/grillnor', 'vlakna' => $vlakna],
            ['slug' => 'taveo', 'typ' => 'git', 'zdroj' => 'git@github.com:kebzat/tavo.git', 'vlakna' => []],
        ];
    }

    private function vlakno(string $id, string $nazev, int $aktivita, bool $claude = true): array
    {
        return ['id' => $id, 'poradi' => (int) ltrim($id, '@'), 'nazev' => $nazev, 'autor' => 'Tom', 'aktivita' => $aktivita, 'claude' => $claude];
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tom']);
    }

    public function test_api_dostane_tajemstvi_a_vrati_projekty(): void
    {
        Http::fake(['agent.test/api/projekty' => Http::response($this->projekty())]);

        $this->assertSame('grillnor', app(AgentServer::class)->projects()[0]['slug']);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.self::SECRET));
    }

    public function test_bez_tajemstvi_nebo_bez_spojeni_hlasi_srozumitelnou_chybu(): void
    {
        config(['services.agent.secret' => null]);

        try {
            app(AgentServer::class)->projects();
            $this->fail('Bez tajemství se nemá volat.');
        } catch (AgentUnavailable $e) {
            $this->assertStringContainsString('AGENT_SECRET', $e->getMessage());
        }

        config(['services.agent.secret' => self::SECRET]);
        Http::fake(['agent.test/*' => Http::response(['chyba' => 'Záloha před vláknem se nepovedla.'], 500)]);

        $this->expectExceptionMessage('Záloha před vláknem se nepovedla.');
        app(AgentServer::class)->startThread('grillnor', 'Test', 'Tom');
    }

    public function test_vstupenka_do_terminalu_je_podepsana_a_kratka(): void
    {
        $url = app(AgentServer::class)->terminalUrl('grillnor', '@3', 'Tom', 'taveo.cz');

        $this->assertStringStartsWith('wss://taveo.cz/agent-terminal/?t=', $url);

        [$data, $podpis] = explode('.', substr($url, strpos($url, '?t=') + 3));
        $ocekavany = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $obsah = json_decode(base64_decode(strtr($data, '-_', '+/')), true);

        $this->assertSame($ocekavany, $podpis);
        $this->assertSame(['grillnor', '@3', 'Tom'], [$obsah['p'], $obsah['v'], $obsah['u']]);
        $this->assertLessThanOrEqual(time() + 60, $obsah['exp']);
    }

    public function test_seznam_projektu_ukaze_klienta_a_vlakna(): void
    {
        Client::create(['name' => 'Grillnor s.r.o.', 'slug' => 'grillnor', 'agent_project' => 'grillnor']);
        Http::fake(['agent.test/api/projekty' => Http::response($this->projekty([
            $this->vlakno('@1', 'Banner', now()->subMinutes(5)->timestamp),
            $this->vlakno('@2', 'Filtry', now()->subHour()->timestamp),
        ]))]);

        Livewire::actingAs($this->spravce())
            ->test(AgentProjects::class)
            ->assertOk()
            ->assertSee('Grillnor s.r.o.')
            ->assertSee('živý web')
            ->assertSee('2 vlákna · naposledy před 5 minutami')
            ->assertSee('Žádné běžící vlákno');
    }

    public function test_nedostupny_agent_stranku_neshodi(): void
    {
        Http::fake(['agent.test/*' => fn () => throw new ConnectionException('nic')]);

        Livewire::actingAs($this->spravce())
            ->test(AgentProjects::class)
            ->assertOk()
            ->assertSee('Agent na serveru neodpovídá');
    }

    public function test_pridani_repozitare_ukaze_klice_a_propoji_klienta(): void
    {
        $klient = Client::create(['name' => 'IQ hračky', 'slug' => 'iq-hracky']);
        Http::fake(['agent.test/api/projekty' => Http::response(['ok' => false, 'vystup' => "Na GitHubu přidejte dva klíče:\nssh-ed25519 AAAA taveo-agent-iq-cteni"], 422)]);

        Livewire::actingAs($this->spravce())
            ->test(AgentProjects::class)
            ->callAction('addProject', ['slug' => 'iq-hracky', 'type' => 'git', 'source' => 'git@github.com:kebzat/iq.git', 'client_id' => $klient->getKey()])
            ->assertHasNoFormErrors()
            ->assertSet('output', "Na GitHubu přidejte dva klíče:\nssh-ed25519 AAAA taveo-agent-iq-cteni");

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['typ'] === 'git' && $request['zdroj'] === 'git@github.com:kebzat/iq.git');
        $this->assertSame('iq-hracky', $klient->fresh()->agent_project);
    }

    public function test_spatny_nazev_projektu_neprojde(): void
    {
        Http::fake(['agent.test/api/projekty' => Http::response($this->projekty())]);

        Livewire::actingAs($this->spravce())
            ->test(AgentProjects::class)
            ->callAction('addProject', ['slug' => 'Grillnor Web', 'type' => 'prazdny'])
            ->assertHasFormErrors(['slug']);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_detail_projektu_otevre_posledni_vlakno_a_zalozi_nove(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => '@7'], 201);
            }

            return Http::response($this->projekty([
                $this->vlakno('@1', 'Banner', now()->subHour()->timestamp),
                $this->vlakno('@2', 'Filtry v kategorii', now()->subMinute()->timestamp, claude: false),
            ]));
        });

        $page = Livewire::actingAs($this->spravce())
            ->test(AgentProject::class, ['project' => 'grillnor'])
            ->assertOk()
            ->assertSet('thread', '@2')
            ->assertSee('Filtry v kategorii')
            ->assertSee('Živý web na serveru');

        $this->assertStringContainsString('wss://', $page->instance()->terminalUrl());

        $page->callAction('newThread', ['name' => 'Úprava košíku', 'continue' => true])
            ->assertSet('thread', '@7')
            ->assertDispatched('agent-thread-selected');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/projekty/grillnor/vlakna')
            && $request['nazev'] === 'Úprava košíku'
            && $request['autor'] === 'Tom'
            && $request['navazat'] === true);
    }

    public function test_cizi_vlakno_ani_neznamy_projekt_neprojdou(): void
    {
        Http::fake(['agent.test/api/projekty' => Http::response($this->projekty([$this->vlakno('@1', 'Banner', time())]))]);
        $spravce = $this->spravce();

        Livewire::actingAs($spravce)
            ->test(AgentProject::class, ['project' => 'grillnor'])
            ->call('selectThread', '@99')
            ->assertSet('thread', '@1')
            ->callAction('closeThread', arguments: ['thread' => '@99']);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');

        $this->actingAs($spravce)->get(AgentProject::getUrl(['project' => 'neexistuje'], panel: 'tools'))->assertNotFound();
    }

    public function test_ukonceni_a_prejmenovani_vlakna(): void
    {
        Http::fake(fn (Request $request) => $request->method() === 'GET'
            ? Http::response($this->projekty([$this->vlakno('@1', 'Banner', time()), $this->vlakno('@2', 'Filtry', time() - 60)]))
            : Http::response(['ok' => true]));

        Livewire::actingAs($this->spravce())
            ->test(AgentProject::class, ['project' => 'grillnor'])
            ->callAction('renameThread', ['name' => 'Banner na Black Friday'], ['thread' => '@2'])
            ->callAction('closeThread', arguments: ['thread' => '@1']);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/vlakna/%402/prejmenovat') && $request['nazev'] === 'Banner na Black Friday');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/vlakna/%401'));
    }

    public function test_neuspesne_ukonceni_nezmeni_otevrene_vlakno(): void
    {
        Http::fake(fn (Request $request) => $request->method() === 'GET'
            ? Http::response($this->projekty([$this->vlakno('@1', 'Banner', time()), $this->vlakno('@2', 'Filtry', time() - 60)]))
            : Http::response(['chyba' => 'Vlákno neexistuje.'], 404));

        Livewire::actingAs($this->spravce())
            ->test(AgentProject::class, ['project' => 'grillnor'])
            ->assertSet('thread', '@1')
            ->callAction('closeThread', arguments: ['thread' => '@1'])
            ->assertNotified('Nepovedlo se')
            ->assertSet('thread', '@1')
            ->assertNotDispatched('agent-thread-selected');
    }

    public function test_klient_s_projektem_ma_odkaz_na_agenta(): void
    {
        Http::fake(['agent.test/api/projekty' => Http::response($this->projekty())]);
        $klient = Client::create(['name' => 'Grillnor', 'slug' => 'grillnor', 'agent_project' => 'grillnor']);

        Livewire::actingAs($this->spravce())
            ->test(EditClient::class, ['record' => $klient->getRouteKey()])
            ->assertActionVisible('agent')
            ->assertFormFieldExists('agent_project');
    }
}
