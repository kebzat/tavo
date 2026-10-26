<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Resources\Clients\Pages\EditClient;
use App\Filament\Tools\Resources\Clients\Pages\ListClients;
use App\Filament\Tools\Resources\Clients\RelationManagers\ArchiveRelationManager;
use App\Models\Client;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Support\ClickUp\ClickUp;
use App\Support\ClickUp\ClickUpAgent;
use App\Support\ClickUp\ClientImport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

class ClickUpClientImportTest extends TestCase
{
    use RefreshDatabase;

    /** Projekty, které služba agenta založila. */
    private array $added = [];

    /** Popis úkolu v ClickUpu, druhé načtení ho mění. */
    private string $description = 'Klient chce **nové fotky** produktů.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clickup.token' => 'pk_test',
            'services.clickup.team_id' => '900',
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajne',
        ]);

        Storage::fake('local');
        Sleep::fake();
        $this->fakeClickUp();
    }

    private function fakeClickUp(): void
    {
        Http::fake([
            'api.clickup.com/api/v2/team/900/space*' => Http::response(['spaces' => [
                ['id' => 'SP-PAVEL', 'name' => 'Projekty PAVELVCELIS.cz'],
                ['id' => 'SP-TAVEO', 'name' => 'Projekty TAVEO.cz'],
            ]]),
            'api.clickup.com/api/v2/space/SP-PAVEL/folder*' => Http::response(['folders' => [
                ['id' => 'F1', 'name' => 'Květinářství Bláha', 'lists' => [['id' => 'L1', 'name' => 'Web'], ['id' => 'L2', 'name' => 'Reklamy']]],
            ]]),
            'api.clickup.com/api/v2/space/SP-TAVEO/folder*' => Http::response(['folders' => [
                ['id' => 'F2', 'name' => 'Svět Cejlonu', 'lists' => [['id' => 'L3', 'name' => 'Úkoly']]],
            ]]),
            'api.clickup.com/api/v2/folder/F1/list*' => Http::response(['lists' => [['id' => 'L9', 'name' => 'Starý archiv']]]),
            'api.clickup.com/api/v2/folder/F1/view' => Http::response(['views' => [
                ['id' => 'list-view', 'type' => 'list', 'name' => 'Seznam'],
                ['id' => '8c-DOC1', 'type' => 'doc', 'name' => 'Informace o klientovi'],
            ]]),
            'api.clickup.com/api/v2/folder/F1' => Http::response(['id' => 'F1', 'name' => 'Květinářství Bláha', 'lists' => [['id' => 'L1', 'name' => 'Web'], ['id' => 'L2', 'name' => 'Reklamy']]]),
            'api.clickup.com/api/v2/list/L1/task*' => Http::response(['last_page' => true, 'tasks' => [
                ['id' => 'T1', 'name' => 'Nové fotky', 'status' => ['status' => 'in progress', 'type' => 'custom'], 'due_date' => '1793000000000', 'assignees' => [['username' => 'Pavel']], 'tags' => [['name' => 'web']], 'url' => 'https://app.clickup.com/t/T1', 'parent' => null],
                ['id' => 'T2', 'name' => 'Vybrat fotografa', 'status' => ['status' => 'complete', 'type' => 'closed'], 'parent' => 'T1'],
            ]]),
            'api.clickup.com/api/v2/list/L2/task*' => Http::response(['last_page' => true, 'tasks' => []]),
            'api.clickup.com/api/v2/list/L9/task*' => Http::response(['last_page' => true, 'tasks' => []]),
            'api.clickup.com/api/v2/task/T1/comment*' => Http::response(['comments' => [
                ['id' => 'C2', 'comment_text' => 'Fotograf má volno v listopadu.', 'user' => ['username' => 'Tom'], 'date' => '1792100000000'],
                ['id' => 'C1', 'comment_text' => 'Posílám ukázky.', 'user' => ['username' => 'Pavel'], 'date' => '1792000000000'],
            ]]),
            'api.clickup.com/api/v2/task/T2/comment*' => Http::response(['comments' => []]),
            'api.clickup.com/api/v2/task/T1*' => fn () => Http::response([
                'id' => 'T1', 'name' => 'Nové fotky', 'markdown_description' => $this->description,
                'checklists' => [['name' => 'Postup', 'items' => [['name' => 'Seznam produktů', 'resolved' => true], ['name' => 'Focení', 'resolved' => false]]]],
                'attachments' => [
                    ['title' => 'brief.pdf', 'extension' => 'pdf', 'size' => 12, 'url' => 'https://t900.p.clickup-attachments.com/t900/brief.pdf'],
                    ['title' => 'video.mp4', 'extension' => 'mp4', 'size' => 200 * 1024 * 1024, 'url' => 'https://t900.p.clickup-attachments.com/t900/video.mp4'],
                ],
                'custom_fields' => [
                    ['name' => 'Rozpočet', 'type' => 'currency', 'value' => '15000'],
                    ['name' => 'Kanál', 'type' => 'drop_down', 'value' => 1, 'type_config' => ['options' => [['id' => 'a', 'name' => 'Meta', 'orderindex' => 0], ['id' => 'b', 'name' => 'Google', 'orderindex' => 1]]]],
                    ['name' => 'Prázdné', 'type' => 'text'],
                ],
            ]),
            'api.clickup.com/api/v2/task/T2*' => Http::response(['id' => 'T2', 'name' => 'Vybrat fotografa', 'text_content' => '']),
            't900.p.clickup-attachments.com/*' => Http::response('%PDF-1.4 obsah'),
            'api.clickup.com/api/v3/workspaces/900/docs/8c-DOC1/pages*' => Http::response([
                ['name' => 'O firmě', 'content' => 'Rodinné květinářství v Brně, **marže 40 %**.', 'pages' => [
                    ['name' => 'Kontakty', 'content' => 'Objednávky řeší majitelka.', 'pages' => []],
                ]],
            ]),
            'api.clickup.com/api/v3/workspaces/900/docs/8c-EMPTY/pages*' => Http::response([['name' => 'Bez textu', 'content' => '', 'pages' => []]]),
            'api.clickup.com/api/v3/workspaces/900/docs*' => fn (Request $request) => Http::response(['docs' => match ($request->data()['parent_id'] ?? null) {
                'F1' => [['id' => '8c-DOC1', 'name' => 'Informace o klientovi', 'parent' => ['id' => 'F1', 'type' => 5]], ['id' => '8c-OTHER', 'name' => 'Cizí', 'parent' => ['id' => 'F7', 'type' => 5]]],
                'L2' => [['id' => '8c-EMPTY', 'name' => 'Poznámky k reklamám', 'parent' => ['id' => 'L2', 'type' => 6]]],
                default => [],
            }, 'next_cursor' => null]),
            'agent.test/api/projekty' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['ok' => true, 'vystup' => 'Projekt '.($this->added[] = $request->data()['projekt']).' je připravený.'])
                : Http::response([['slug' => 'svet-cejlonu', 'typ' => 'prazdny', 'zdroj' => '', 'vlakna' => []]]),
        ]);
    }

    private function prihlasit(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']));
        Filament::setCurrentPanel('tools');
    }

    public function test_slozka_z_clickupu_je_klient_s_projektem_agenta(): void
    {
        $this->prihlasit();
        $existing = Client::query()->updateOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu', 'agent_project' => 'svet-cejlonu', 'clickup_list_id' => 'L3']);
        $before = Client::query()->count();

        Livewire::test(ListClients::class)
            ->mountAction('clickup')
            ->assertMountedActionModalSee(['Projekty PAVELVCELIS.cz › Květinářství Bláha', '2 seznamy · nový klient', 'klient „Svět Cejlonu“ už je'])
            ->setActionData(['folders' => ['F1', 'F2']])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Založeno klientů: 2');

        $client = Client::query()->where('clickup_folder_id', 'F1')->sole();
        $this->assertSame(['Květinářství Bláha', 'kvetinarstvi-blaha', null], [$client->name, $client->slug, $client->agent_project]);
        $this->assertSame(ClientImport::STATUS_QUEUED, $client->clickup_import['status']);
        $this->assertSame('Pavel', $client->clickup_import['author']);

        // Projekt agenta se zakládá na pozadí, v kliknutí ne (fronta na serveru).
        $this->assertSame([], $this->added);
        $this->assertSame('queued', $client->agent_project_request['status']);
        $this->artisan('agent:projekty-klientu')->assertSuccessful();
        $this->assertSame('kvetinarstvi-blaha', $client->refresh()->agent_project);
        $this->assertContains('kvetinarstvi-blaha', $this->added);

        // Stávající klient se stejným názvem se jen napojí, projekt mu zůstane.
        $this->assertSame('F2', $existing->refresh()->clickup_folder_id);
        $this->assertNotContains('svet-cejlonu', $this->added);
        $this->assertSame($before + 1, Client::query()->count());

        // Napojené složky už ve výběru nejsou.
        cache()->flush();
        Livewire::test(ListClients::class)->mountAction('clickup')->assertMountedActionModalDontSee('Květinářství Bláha')->assertMountedActionModalSee('Složky, které už klienta mají');
    }

    public function test_do_archivu_se_nacte_vse_ze_slozky(): void
    {
        $client = Client::query()->create(['name' => 'Květinářství Bláha', 'slug' => 'kvetinarstvi-blaha', 'clickup_folder_id' => 'F1']);
        app(ClientImport::class)->queue($client, 'Pavel');

        $this->artisan('clickup:nacist')->assertSuccessful();

        $sources = KnowledgeSource::query()->where('client_id', $client->getKey())->orderBy('id')->get();
        // Prázdné seznamy (Reklamy, archivovaný Starý archiv) a prázdný Doc nemají dokument, cizí Doc se nevezme.
        $this->assertSame(['ClickUp: Web', 'ClickUp Doc: Informace o klientovi'], $sources->pluck('title')->all());

        $list = $sources[0];
        $this->assertSame(KnowledgeSource::STATUS_STORED, $list->status);
        $this->assertFalse($list->learn);
        $this->assertSame('ClickUp', $list->author);
        $this->assertSame('clickup:list:L1', $list->external_ref);
        $this->assertStringContainsString('## Nové fotky', $list->body);
        $this->assertStringContainsString('Stav: in progress', $list->body);
        $this->assertStringContainsString('2 úkoly, z toho hotové: 1', $list->body);
        $this->assertStringContainsString('Řeší: Pavel · Štítky: web', $list->body);
        $this->assertStringContainsString('Klient chce **nové fotky** produktů.', $list->body);
        $this->assertStringContainsString('- [x] Seznam produktů', $list->body);
        $this->assertStringContainsString('- Rozpočet: 15000', $list->body);
        $this->assertStringContainsString('- Kanál: Google', $list->body);
        $this->assertStringNotContainsString('Prázdné', $list->body);
        $this->assertMatchesRegularExpression('/Pavel, .+?: Posílám ukázky\.\n- Tom, .+?: Fotograf má volno/', $list->body);
        $this->assertStringContainsString('### Vybrat fotografa', $list->body);
        $this->assertStringContainsString('video.mp4 (velká, jen v ClickUpu', $list->body);
        $this->assertSame([['name' => 'brief.pdf']], collect($list->files)->map(fn (array $file): array => ['name' => $file['name']])->all());
        Storage::disk('local')->assertExists($list->files[0]['path']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'clickup-attachments.com') && $request->hasHeader('Authorization', 'pk_test'));
        $this->assertNull($list->pageFile());

        $doc = $sources[1];
        $this->assertStringContainsString("## O firmě\n\nRodinné květinářství v Brně", $doc->body);
        $this->assertStringContainsString("### Kontakty\n\nObjednávky řeší majitelka.", $doc->body);

        $client->refresh();
        $this->assertSame(ClientImport::STATUS_DONE, $client->clickup_import['status']);
        $this->assertSame([1, 2, 1, 1], [$client->clickup_import['lists'], $client->clickup_import['tasks'], $client->clickup_import['docs'], $client->clickup_import['files']]);
        $this->assertStringContainsString('1 seznam, 2 úkoly, 1 Doc, 1 příloha', ClientImport::summary($client));

        // Načíst znovu: dokumenty se přepíšou, nepřibudou, stará příloha zmizí.
        $oldFile = $list->files[0]['path'];
        $this->description = 'Fotky jsou hotové.';
        app(ClientImport::class)->queue($client, 'Tom');
        $this->artisan('clickup:nacist')->assertSuccessful();

        $this->assertSame(2, KnowledgeSource::query()->where('client_id', $client->getKey())->count());
        $this->assertStringContainsString('Fotky jsou hotové.', $list->refresh()->body);
        Storage::disk('local')->assertMissing($oldFile);
        Storage::disk('local')->assertExists($list->files[0]['path']);

        // Bez žádosti příkaz nic nevolá.
        Http::fake();
        $this->artisan('clickup:nacist')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_archiv_klienta_ukaze_prubeh_a_jde_nacist_znovu(): void
    {
        $this->prihlasit();
        $client = Client::query()->create(['name' => 'Květinářství Bláha', 'slug' => 'kvetinarstvi-blaha', 'clickup_folder_id' => 'F1']);
        app(ClientImport::class)->queue($client, 'Pavel');

        Livewire::test(ArchiveRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertSee('Z ClickUpu: čeká na načtení');

        app(ClientImport::class)->run($client);
        $this->assertSame(ClientImport::STATUS_DONE, $client->refresh()->clickup_import['status']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->callAction('clickup')
            ->assertNotified('Načítá se na pozadí');

        $this->assertSame(ClientImport::STATUS_QUEUED, $client->refresh()->clickup_import['status']);
    }

    public function test_chyba_clickupu_se_zapise_k_archivu(): void
    {
        $client = Client::query()->create(['name' => 'Květinářství Bláha', 'slug' => 'kvetinarstvi-blaha', 'clickup_folder_id' => 'F404']);
        Http::fake(['api.clickup.com/*' => Http::response(['err' => 'Folder not found'], 404)]);
        app(ClientImport::class)->queue($client, 'Pavel');

        $this->artisan('clickup:nacist')->assertSuccessful();

        $this->assertSame(ClientImport::STATUS_FAILED, $client->refresh()->clickup_import['status']);
        $this->assertStringContainsString('Z ClickUpu se načíst nepovedlo', ClientImport::summary($client));
    }

    public function test_pri_limitu_dotazu_clickup_pocka(): void
    {
        $calls = 0;
        Http::fake(['api.clickup.com/api/v2/folder/F5/view' => function () use (&$calls) {
            return ++$calls === 1
                ? Http::response(['err' => 'Rate limit'], 429, ['X-RateLimit-Reset' => (string) (time() + 20)])
                : Http::response(['views' => []]);
        }]);

        $this->assertSame([], app(ClickUp::class)->docViews('F5'));
        $this->assertSame(2, $calls);
        Sleep::assertSleptTimes(1);
    }

    public function test_agent_z_clickupu_najde_klienta_podle_slozky(): void
    {
        $client = Client::query()->create(['name' => 'Květinářství Bláha', 'slug' => 'kvetinarstvi-blaha', 'clickup_folder_id' => 'F1', 'agent_project' => 'kvetinarstvi-blaha']);
        $list = Client::query()->create(['name' => 'Jiný', 'slug' => 'jiny', 'clickup_list_id' => 'L2', 'agent_project' => 'jiny']);
        $agent = app(ClickUpAgent::class);

        [$found, $project] = $agent->resolve(['list_id' => 'L1', 'folder_id' => 'F1', 'space_id' => 'SP-PAVEL']);
        $this->assertTrue($found->is($client));
        $this->assertSame('kvetinarstvi-blaha', $project);

        // Klient s přiřazeným seznamem má přednost před složkou.
        $this->assertTrue($agent->resolve(['list_id' => 'L2', 'folder_id' => 'F1', 'space_id' => 'SP-PAVEL'])[0]->is($list));
    }
}
