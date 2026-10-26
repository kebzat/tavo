<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Livewire\ClientBrain;
use App\Filament\Tools\Pages\Brain;
use App\Filament\Tools\Resources\Clients\Pages\EditClient;
use App\Filament\Tools\Resources\Clients\RelationManagers\ArchiveRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\KnowledgeRelationManager;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\EditKnowledgeEntry;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use App\Filament\Tools\Resources\KnowledgeSources\Pages\CreateKnowledgeSource;
use App\Filament\Tools\Resources\KnowledgeSources\Pages\ViewKnowledgeSource;
use App\Models\AdGraphic;
use App\Models\AgentJob;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Support\AdGraphics\Formats;
use App\Support\AdGraphics\GraphicsAgent;
use App\Support\Knowledge\BrainAgent;
use App\Support\Knowledge\KnowledgeBase;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'agent-token-agent-token-agent-token-1234';

    /** Úlohy, které dostala služba agenta, s projektem. */
    private array $tasks = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajne',
            'services.agent.api_token' => self::TOKEN,
            'services.agent.brain_project' => 'mozek',
            'services.clickup.max_usage_percent' => 90,
        ]);

        Http::fake([
            'agent.test/api/projekty/*/ulohy' => function (Request $request) {
                $this->tasks[] = ['projekt' => explode('/', parse_url($request->url(), PHP_URL_PATH))[3], ...$request->data()];

                return Http::response(['zarazeno' => true], 202);
            },
            'agent.test/api/predplatne' => Http::response(['plan' => 'max', 'limity' => [
                ['kind' => 'session', 'percent' => 20, 'resets_at' => now()->addHours(2)->toIso8601String()],
            ]]),
            'agent.test/api/projekty' => Http::response([['slug' => 'mozek', 'typ' => 'prazdny', 'zdroj' => '', 'vlakna' => []]]),
            'agent.test/api/projekty/mozek/konverzace' => Http::response([]),
        ]);
    }

    private function klient(): Client
    {
        return Client::query()->updateOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu', 'agent_project' => 'svet-cejlonu', 'website_url' => 'https://www.svetcejlonu.cz']);
    }

    private function prihlasit(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']));
        Filament::setCurrentPanel('tools');
    }

    /** @param  array<string, mixed>  $attributes */
    private function poznatek(array $attributes = []): KnowledgeEntry
    {
        return KnowledgeEntry::create([
            'layer' => KnowledgeEntry::LAYER_COMPANY,
            'title' => 'Klientům nikdy neslibujeme konkrétní ROAS',
            'body' => 'Výsledek reklam závisí i na e-shopu a sezóně. Slibujeme postup a měření.',
            'status' => KnowledgeEntry::STATUS_APPROVED,
            ...$attributes,
        ]);
    }

    private function vysledek(AgentJob $job, array $data): void
    {
        $this->withToken(self::TOKEN)->postJson("/nastroje/api/agent/ulohy/{$job->getKey()}/vysledek", $data)->assertOk();
    }

    public function test_podklady_z_formulare_posle_knihovnikovi_do_projektu_mozku(): void
    {
        Storage::fake('local');
        $this->prihlasit();

        Livewire::test(CreateKnowledgeSource::class)
            ->fillForm([
                'title' => 'Pavlovy poznámky k reklamám',
                'client_id' => $this->klient()->getKey(),
                'body' => 'Svět Cejlonu nechce slevy, jen dárek k objednávce.',
                'file_paths' => [UploadedFile::fake()->create('schuzka 3.10.pdf', 20, 'application/pdf')],
                'note' => 'Ceny z roku 2024 neplatí.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $source = KnowledgeSource::query()->sole();
        $this->assertSame(KnowledgeSource::STATUS_RUNNING, $source->status);
        $this->assertSame('schuzka 3.10.pdf', $source->files[0]['name']);
        $this->assertSame('Pavel', $source->author);

        $task = $this->tasks[0];
        $this->assertSame('mozek', $task['projekt']);
        $this->assertTrue($task['terminal']);
        $this->assertSame('[/mozek]', $task['konec']);
        $this->assertSame($source->agent_job_id, $task['id']);
        $this->assertStringContainsString(route('agent.api.mozek.zdroj', $source), $task['zadani']);
        $this->assertStringContainsString(route('agent.api.mozek.soubor', [$source, 0]), $task['zadani']);
        $this->assertStringContainsString('01-schuzka-310.pdf', $task['zadani']);
        $this->assertStringContainsString('Ceny z roku 2024 neplatí.', $task['zadani']);
        $this->assertStringContainsString('svet-cejlonu (Svět Cejlonu)', $task['zadani']);
        $this->assertSame(BrainAgent::SOURCE, $source->agentJob->source);
    }

    public function test_agent_cte_podklady_jen_s_tokenem(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('mozek/a.txt', 'obsah souboru');
        $source = KnowledgeSource::create(['title' => 'Poznámky', 'body' => 'Dlouhý text', 'files' => [['path' => 'mozek/a.txt', 'name' => 'a.txt']]]);

        $this->get(route('agent.api.mozek.zdroj', $source))->assertUnauthorized();
        $this->withToken(self::TOKEN)->get(route('agent.api.mozek.zdroj', $source))->assertOk()->assertSee('Dlouhý text');
        $this->assertSame('obsah souboru', $this->withToken(self::TOKEN)->get(route('agent.api.mozek.soubor', [$source, 0]))->assertOk()->streamedContent());
        $this->withToken(self::TOKEN)->get(route('agent.api.mozek.soubor', [$source, 1]))->assertNotFound();
    }

    public function test_navrhy_od_agenta_cekaji_na_schvaleni_a_nezdvojuji_se(): void
    {
        $client = $this->klient();
        $this->poznatek();
        $source = KnowledgeSource::create(['title' => 'Poznámky', 'body' => 'x', 'author' => 'Pavel']);

        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), [
            'zdroj' => $source->getKey(),
            'poznatky' => [
                ['vrstva' => 'klient', 'klient' => 'svet-cejlonu', 'nadpis' => 'Svět Cejlonu nechce slevy', 'text' => 'Jen dárek k objednávce.', 'stitky' => ['Reklamy', ' '], 'puvod' => 'Pavel, 3. 10.'],
                ['vrstva' => 'firma', 'nadpis' => 'klientům nikdy neslibujeme konkrétní ROAS', 'text' => 'Totéž znovu.'],
                ['vrstva' => 'klient', 'klient' => 'neexistuje', 'nadpis' => 'Neznámý klient', 'text' => 'Obecně platné.'],
            ],
        ])->assertOk()->assertJson(['zalozeno' => 2, 'preskoceno' => ['klientům nikdy neslibujeme konkrétní ROAS']]);

        $entry = KnowledgeEntry::query()->where('title', 'Svět Cejlonu nechce slevy')->sole();
        $this->assertSame(KnowledgeEntry::STATUS_PROPOSED, $entry->status);
        $this->assertSame($client->getKey(), $entry->client_id);
        $this->assertSame(['reklamy'], $entry->tags);
        $this->assertSame('podklad', $entry->origin_type);
        $this->assertSame($source->getKey(), $entry->knowledge_source_id);
        $this->assertFalse($entry->always);
        $this->assertSame(KnowledgeEntry::LAYER_COMPANY, KnowledgeEntry::query()->where('title', 'Neznámý klient')->value('layer'));

        // Neschválené agenti nedostanou.
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek', ['q' => 'slevy']))->assertJsonCount(0, 'poznatky');
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek', ['q' => 'slevy', 'navrhy' => 1]))->assertJsonPath('poznatky.0.stav', 'proposed');
    }

    public function test_z_jednech_podkladu_nejvys_strop_navrhu(): void
    {
        $source = KnowledgeSource::create(['title' => 'Hodně textu', 'body' => 'x']);

        foreach (range(1, BrainAgent::MAX_PER_SOURCE - 1) as $i) {
            KnowledgeEntry::create(['layer' => 'firma', 'title' => "Starý {$i}", 'body' => 'x', 'knowledge_source_id' => $source->getKey()]);
        }

        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), [
            'zdroj' => $source->getKey(),
            'poznatky' => [['vrstva' => 'firma', 'nadpis' => 'Nový 1', 'text' => 'x'], ['vrstva' => 'firma', 'nadpis' => 'Nový 2', 'text' => 'x']],
        ])->assertOk()->assertJson(['zalozeno' => 1, 'preskoceno' => ['Nový 2'], 'zbyva' => 0]);
    }

    public function test_dobehnuti_knihovnika_uzavre_podklady(): void
    {
        $source = KnowledgeSource::create(['title' => 'Poznámky', 'body' => 'x']);
        app(BrainAgent::class)->process($source);

        $this->vysledek($source->refresh()->agentJob, ['ok' => true, 'vysledek' => "Navrhl jsem 12 poznatků.\n[/mozek]"]);

        $source->refresh();
        $this->assertSame(KnowledgeSource::STATUS_DONE, $source->status);
        $this->assertSame('Navrhl jsem 12 poznatků.', $source->result);
        $this->assertSame(AgentJob::STATUS_DONE, $source->agentJob->status);
    }

    public function test_schvaleni_noveho_zneni_archivuje_stary_poznatek(): void
    {
        $this->prihlasit();
        $old = $this->poznatek(['always' => true]);
        $new = KnowledgeEntry::create(['layer' => 'firma', 'title' => 'Nikdy neslibujeme ROAS ani počet objednávek', 'body' => 'Nové znění.', 'replaces_id' => $old->getKey()]);

        Livewire::test(EditKnowledgeEntry::class, ['record' => $new->getKey()])
            ->assertSee('Nové znění poznatku #'.$old->getKey())
            ->callAction('approve')
            ->assertRedirect();

        $this->assertSame(KnowledgeEntry::STATUS_ARCHIVED, $old->refresh()->status);
        $new->refresh();
        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $new->status);
        $this->assertTrue($new->always);
        $this->assertSame('Pavel', $new->reviewed_by);
    }

    public function test_navrhy_jdou_schvalit_hromadne_a_karta_ukazuje_nejdriv_ke_schvaleni(): void
    {
        $this->prihlasit();
        $a = KnowledgeEntry::create(['layer' => 'firma', 'title' => 'A', 'body' => 'x']);
        $b = KnowledgeEntry::create(['layer' => 'firma', 'title' => 'B', 'body' => 'x']);
        $approved = $this->poznatek();

        Livewire::test(ListKnowledgeEntries::class)
            ->assertSet('activeTab', 'navrhy')
            ->assertCanSeeTableRecords([$a, $b])
            ->assertCanNotSeeTableRecords([$approved])
            ->callTableBulkAction('approveSelected', [$a, $b]);

        $this->assertSame(3, KnowledgeEntry::query()->approved()->count());
    }

    public function test_do_zadani_grafiky_jde_zaklad_mozku_a_poznatky_klienta(): void
    {
        $client = $this->klient();
        $this->poznatek(['always' => true]);
        $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Svět Cejlonu chce fotky s citronem', 'body' => 'Modrý čaj vždy s citronem.']);
        $this->poznatek(['layer' => 'postup', 'title' => 'Měsíční report', 'body' => 'Neschválený a mimo základ se do zadání nedostane.', 'status' => KnowledgeEntry::STATUS_PROPOSED]);

        $graphic = AdGraphic::create([
            'client_id' => $client->getKey(), 'project' => 'svet-cejlonu', 'title' => 'Modrý čaj', 'brief' => 'Modrý čaj.',
            'formats' => Formats::resolve(['ig_post'], []), 'variants' => 1, 'author' => 'Tom',
        ]);
        app(GraphicsAgent::class)->start($graphic);

        $prompt = $this->tasks[0]['zadani'];
        $this->assertStringContainsString('## Mozek TAVEO', $prompt);
        $this->assertStringContainsString('Klientům nikdy neslibujeme konkrétní ROAS', $prompt);
        $this->assertStringContainsString('Ke klientovi Svět Cejlonu:', $prompt);
        $this->assertStringContainsString('Svět Cejlonu chce fotky s citronem', $prompt);
        $this->assertStringNotContainsString('Měsíční report', $prompt);
        $this->assertStringContainsString(route('agent.api.mozek'), $prompt);
    }

    public function test_poznatek_ze_zaveru_grafiky_jde_do_mozku_ke_schvaleni(): void
    {
        $client = $this->klient();
        $graphic = AdGraphic::create([
            'client_id' => $client->getKey(), 'project' => 'svet-cejlonu', 'title' => 'Modrý čaj', 'brief' => 'Modrý čaj.',
            'formats' => Formats::resolve(['ig_post'], []), 'variants' => 1, 'author' => 'Tom',
        ]);
        app(GraphicsAgent::class)->start($graphic);

        $this->vysledek($graphic->refresh()->agentJob, ['ok' => true, 'vysledek' => implode("\n", [
            'Hotovo, 1 návrh.',
            '[grafika]',
            '{"varianty": [{"soubor": "01-caj-1080x1350.png", "nazev": "Čaj", "format": "IG", "text": "x"}]}',
            '[/grafika]',
            '[poznatek klient]',
            'Web Světa Cejlonu má fotky jen v 800 px',
            'Na velké formáty je potřeba si fotky vyžádat od klienta.',
            '[/poznatek]',
        ])]);

        $this->assertSame('Hotovo, 1 návrh.', $graphic->refresh()->result);
        $entry = KnowledgeEntry::query()->sole();
        $this->assertSame('Web Světa Cejlonu má fotky jen v 800 px', $entry->title);
        $this->assertSame('Na velké formáty je potřeba si fotky vyžádat od klienta.', $entry->body);
        $this->assertSame($client->getKey(), $entry->client_id);
        $this->assertSame('grafika', $entry->origin_type);
        $this->assertSame(KnowledgeEntry::STATUS_PROPOSED, $entry->status);
    }

    public function test_otazky_na_mozek_navazuji_na_jednu_konverzaci(): void
    {
        $this->prihlasit();

        Livewire::test(Brain::class)
            ->set('question', 'Co víme o Světě Cejlonu?')
            ->call('ask')
            ->assertHasNoErrors()
            ->assertSet('question', '');

        $first = AgentJob::query()->sole();
        $this->assertSame(BrainAgent::SOURCE_QUESTION, $first->source);
        $this->assertSame('mozek', $this->tasks[0]['projekt']);
        $this->assertSame('[/odpoved]', $this->tasks[0]['konec']);
        $this->assertStringContainsString('Co víme o Světě Cejlonu?', $this->tasks[0]['zadani']);

        $this->vysledek($first, ['ok' => true, 'session' => $first->session, 'vysledek' => implode("\n", [
            'Chce dárek místo slevy (#3).',
            '[poznatek firma]',
            'Odpovědi z mozku vždy s číslem poznatku',
            'Tom to tak chce.',
            '[/poznatek]',
            '[/odpoved]',
        ])]);
        $this->assertStringStartsWith('Chce dárek místo slevy (#3).', $first->refresh()->result);
        $this->assertSame('dotaz', KnowledgeEntry::query()->sole()->origin_type);

        Livewire::test(Brain::class)->set('question', 'A co reklamy?')->call('ask')
            ->assertSee('Chce dárek místo slevy');
        $this->assertSame($first->session, $this->tasks[1]['session']);

        $this->vysledek(AgentJob::query()->latest('id')->first(), ['ok' => true, 'vysledek' => 'Nevím.']);

        Livewire::test(Brain::class)->set('question', 'Znovu od začátku')->set('fresh', true)->call('ask');
        $this->assertNotSame($first->session, $this->tasks[2]['session']);
    }

    public function test_dalsi_otazku_nepusti_driv_nez_mozek_odpovi(): void
    {
        $this->prihlasit();

        Livewire::test(Brain::class)->set('question', 'První otázka')->call('ask');
        Livewire::test(Brain::class)->set('question', 'Druhá otázka')->call('ask')->assertNotified('Mozek ještě odpovídá na předchozí otázku');

        $this->assertCount(1, $this->tasks);
    }

    public function test_hledani_najde_i_jiny_tvar_slova_a_klienta_dava_nahoru(): void
    {
        $client = $this->klient();
        $this->poznatek(['title' => 'Reporty posíláme do 5. dne v měsíci', 'body' => 'Pavel je kontroluje.']);
        $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Svět Cejlonu chce report v PDF', 'body' => 'Mailem jednatelce.']);
        $this->poznatek(['title' => 'Nesouvisející', 'body' => 'Nic.']);

        $found = app(KnowledgeBase::class)->search('reportu', $client);

        $this->assertSame(['Svět Cejlonu chce report v PDF', 'Reporty posíláme do 5. dne v měsíci'], $found->pluck('title')->all());
    }

    public function test_stranky_mozku_se_zobrazi(): void
    {
        $this->prihlasit();
        $source = KnowledgeSource::create(['title' => 'Poznámky', 'body' => 'Text', 'status' => KnowledgeSource::STATUS_DONE, 'result' => 'Navrhl jsem 1 poznatek.']);
        KnowledgeEntry::create(['layer' => 'firma', 'title' => 'Návrh z podkladů', 'body' => 'x', 'knowledge_source_id' => $source->getKey()]);

        $this->get(Brain::getUrl())->assertOk()->assertSee('Zeptat se mozku');
        $this->get(ListKnowledgeEntries::getUrl())->assertOk()->assertSee('Návrh z podkladů');

        Livewire::test(ViewKnowledgeSource::class, ['record' => $source->getKey()])
            ->assertSee('Navrhl jsem 1 poznatek.')
            ->assertSee('Návrh z podkladů')
            ->call('approve', KnowledgeEntry::query()->value('id'));

        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, KnowledgeEntry::query()->value('status'));
    }

    public function test_dokument_jen_do_archivu_agenta_nespusti_a_agent_ho_najde(): void
    {
        Storage::fake('local');
        $this->prihlasit();
        $client = $this->klient();

        Livewire::withQueryParams(['klient' => $client->getKey()])
            ->test(CreateKnowledgeSource::class)
            ->assertSchemaStateSet(['client_id' => $client->getKey()])
            ->fillForm([
                'title' => 'Marže produktů 2026',
                'category' => 'obchod',
                'document_date' => '2026-09-30',
                'file_paths' => [UploadedFile::fake()->create('marze.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
                'learn' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $source = KnowledgeSource::query()->sole();
        $this->assertSame(KnowledgeSource::STATUS_STORED, $source->status);
        $this->assertSame($client->getKey(), $source->client_id);
        $this->assertSame([], $this->tasks);

        $this->getJson(route('agent.api.archiv', 'svet-cejlonu'))->assertUnauthorized();
        $this->withToken(self::TOKEN)->getJson(route('agent.api.archiv', 'svet-cejlonu'))
            ->assertOk()
            ->assertJsonPath('dokumenty.0.nazev', 'Marže produktů 2026')
            ->assertJsonPath('dokumenty.0.druh', 'Obchodní data a marže')
            ->assertJsonPath('dokumenty.0.k_datu', '2026-09-30')
            ->assertJsonPath('dokumenty.0.soubory.0.jmeno', 'marze.xlsx')
            ->assertJsonPath('dokumenty.0.soubory.0.adresa', route('agent.api.mozek.soubor', [$source, 0]));
        $this->withToken(self::TOKEN)->getJson(route('agent.api.archiv', ['slug' => 'svet-cejlonu', 'druh' => 'strategie']))->assertJsonCount(0, 'dokumenty');
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek.zdroj.popis', $source))->assertOk()->assertJsonPath('id', $source->getKey());

        // Přece jen poznatky: knihovník dostane druh a datum.
        app(BrainAgent::class)->process($source);
        $this->assertTrue($source->refresh()->learn);
        $this->assertStringContainsString('Druh: Obchodní data a marže, platí k 30. 9. 2026.', $this->tasks[0]['zadani']);
        $this->assertStringContainsString('"popis"', $this->tasks[0]['zadani']);
    }

    public function test_knihovnik_posle_popis_dokumentu_do_archivu(): void
    {
        $source = KnowledgeSource::create(['title' => 'Strategie 2027', 'client_id' => $this->klient()->getKey(), 'body' => 'x']);

        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), ['zdroj' => $source->getKey(), 'popis' => 'Vize a cíle na rok 2027.'])
            ->assertOk()->assertJson(['zalozeno' => 0, 'popis' => 'uložen']);
        $this->assertSame('Vize a cíle na rok 2027.', $source->refresh()->summary);

        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), ['popis' => 'Bez zdroje.'])->assertStatus(422);
        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), ['zdroj' => $source->getKey()])->assertJsonValidationErrors(['popis', 'poznatky']);
    }

    public function test_konverzace_v_projektu_klienta_zacne_mozkem_klienta_a_archivem(): void
    {
        $client = $this->klient();
        $this->poznatek(['always' => true]);
        $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Svět Cejlonu nechce slevy', 'body' => 'Jen dárek k objednávce.']);
        $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Ještě neschválený', 'body' => 'x', 'status' => KnowledgeEntry::STATUS_PROPOSED]);
        KnowledgeSource::create(['title' => 'Výsledky Q3', 'client_id' => $client->getKey(), 'category' => 'vysledky', 'summary' => 'Tržby a ROAS po měsících.', 'body' => 'x', 'status' => KnowledgeSource::STATUS_DONE]);

        $this->get(route('agent.api.mozek.uvod', ['projekt' => 'svet-cejlonu']))->assertUnauthorized();

        $text = $this->withToken(self::TOKEN)->get(route('agent.api.mozek.uvod', ['projekt' => 'svet-cejlonu']))->assertOk()->content();
        $this->assertStringContainsString('klient **Svět Cejlonu** (slug `svet-cejlonu`)', $text);
        $this->assertStringContainsString('Klientům nikdy neslibujeme konkrétní ROAS', $text);
        $this->assertStringContainsString('Svět Cejlonu nechce slevy: Jen dárek k objednávce.', $text);
        $this->assertStringNotContainsString('Ještě neschválený', $text);
        $this->assertStringContainsString('Výsledky Q3 (Výsledky a reporty, 1 znaků): Tržby a ROAS po měsících.', $text);
        $this->assertStringContainsString('taveo mozek-navrh klient "Nadpis jednou větou" svet-cejlonu', $text);
        $this->assertLessThan(10000, mb_strlen($text));

        $other = $this->withToken(self::TOKEN)->get(route('agent.api.mozek.uvod', ['projekt' => 'taveo']))->assertOk()->content();
        $this->assertStringContainsString('není klient, je to práce pro TAVEO samo', $other);
        $this->assertStringNotContainsString('Svět Cejlonu nechce slevy', $other);

        // Úlohy z ClickUpu a grafiky dostanou archiv v zadání.
        $briefing = app(KnowledgeBase::class)->briefing($client);
        $this->assertStringContainsString('Výsledky Q3', $briefing);
        $this->assertStringContainsString(route('agent.api.archiv', 'svet-cejlonu'), $briefing);
    }

    public function test_smazani_dokumentu_z_archivu_smaze_soubory_a_poznatky_necha(): void
    {
        Storage::fake('local');
        $this->prihlasit();
        Storage::disk('local')->put('mozek/marze.xlsx', 'tajne');
        $client = $this->klient();
        $source = KnowledgeSource::create(['title' => 'Marže', 'client_id' => $client->getKey(), 'files' => [['path' => 'mozek/marze.xlsx', 'name' => 'marze.xlsx']], 'status' => KnowledgeSource::STATUS_DONE]);
        $entry = $this->poznatek(['knowledge_source_id' => $source->getKey()]);

        Livewire::test(ArchiveRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertCanSeeTableRecords([$source])
            ->callTableAction('delete', $source);

        $this->assertModelMissing($source);
        Storage::disk('local')->assertMissing('mozek/marze.xlsx');
        $this->assertNull($entry->refresh()->knowledge_source_id);
    }

    public function test_karta_klienta_ukaze_archiv_a_mozek_klienta(): void
    {
        Storage::fake('local');
        $this->prihlasit();
        Storage::disk('local')->put('mozek/vize.pdf', 'pdf');
        $client = $this->klient();
        $source = KnowledgeSource::create(['title' => 'Vize 2027', 'client_id' => $client->getKey(), 'category' => 'strategie', 'document_date' => '2027-01-01', 'summary' => 'Cíle a směr na rok 2027.', 'files' => [['path' => 'mozek/vize.pdf', 'name' => 'vize.pdf']], 'status' => KnowledgeSource::STATUS_STORED]);
        $proposed = $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Klient chce víc B2B', 'status' => KnowledgeEntry::STATUS_PROPOSED]);
        $rejected = $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Zamítnutý', 'status' => KnowledgeEntry::STATUS_REJECTED]);

        $this->get(EditClient::getUrl(['record' => $client]))->assertOk()->assertSee('Archiv')->assertSee('Mozek klienta');

        Livewire::test(ArchiveRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertSee('Vize 2027')
            ->assertSee('Vize a strategie')
            ->assertSee('Cíle a směr na rok 2027.')
            ->assertSee('k 1. 1. 2027')
            ->assertTableActionHidden('page', $source);

        Livewire::test(KnowledgeRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertCanSeeTableRecords([$proposed])
            ->assertCanNotSeeTableRecords([$rejected])
            ->callTableAction('approve', $proposed);
        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $proposed->refresh()->status);

        Livewire::test(ViewKnowledgeSource::class, ['record' => $source->getKey()])
            ->assertSee('Cíle a směr na rok 2027.')
            ->assertSee('vize.pdf')
            ->assertSee('Udělat z nich poznatky')
            ->call('download', 0)
            ->assertFileDownloaded('vize.pdf');
    }

    public function test_agent_ulozi_dokument_z_konverzace_do_archivu(): void
    {
        Storage::fake('local');
        $client = $this->klient();
        $this->postJson(route('agent.api.archiv.nahrat', 'svet-cejlonu'), ['nazev' => 'x', 'text' => 'x'])->assertUnauthorized();

        $this->withToken(self::TOKEN)->post(route('agent.api.archiv.nahrat', 'svet-cejlonu'), [
            'nazev' => 'Marže od klienta, říjen 2026',
            'druh' => 'obchod',
            'k_datu' => '2026-10-31',
            'popis' => 'Marže 48 produktů podle kategorií.',
            'soubory' => [UploadedFile::fake()->create('C:\\Users\\jana\\marze.xlsx', 10)],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('stav', 'Uloženo');

        $source = KnowledgeSource::query()->sole();
        $this->assertSame($client->getKey(), $source->client_id);
        $this->assertSame('obchod', $source->category);
        $this->assertSame('2026-10-31', $source->document_date->toDateString());
        $this->assertSame('Marže 48 produktů podle kategorií.', $source->summary);
        $this->assertSame('marze.xlsx', $source->files[0]['name']);
        $this->assertSame('agent', $source->author);
        Storage::disk('local')->assertExists($source->files[0]['path']);
        $this->assertSame([], $this->tasks);

        // Zápis ze schůzky jako text, s knihovníkem.
        $this->withToken(self::TOKEN)->postJson(route('agent.api.archiv.nahrat', 'svet-cejlonu'), [
            'nazev' => 'Zápis ze schůzky 26. 10.',
            'druh' => 'schuzky',
            'text' => 'Klient chce v roce 2027 víc B2B.',
            'uceni' => true,
        ])->assertCreated()->assertJsonPath('stav', 'Agent čte');
        $this->assertSame('mozek', $this->tasks[0]['projekt']);
        $this->assertStringContainsString('Zápis ze schůzky 26. 10.', $this->tasks[0]['zadani']);

        $this->withToken(self::TOKEN)->postJson(route('agent.api.archiv.nahrat', 'svet-cejlonu'), ['nazev' => 'Prázdné'])->assertJsonValidationErrors(['text']);
        $this->withToken(self::TOKEN)->post(route('agent.api.archiv.nahrat', 'svet-cejlonu'), ['nazev' => 'Skript', 'soubory' => [UploadedFile::fake()->create('x.php', 1)]], ['Accept' => 'application/json'])->assertJsonValidationErrors(['soubory.0']);
        $this->withToken(self::TOKEN)->postJson(route('agent.api.archiv.nahrat', 'svet-cejlonu'), ['nazev' => 'Druh', 'text' => 'x', 'druh' => 'hesla'])->assertJsonValidationErrors(['druh']);
        $this->withToken(self::TOKEN)->postJson(route('agent.api.archiv.nahrat', 'neexistuje'), ['nazev' => 'x', 'text' => 'x'])->assertNotFound();
        $this->assertSame(2, KnowledgeSource::query()->count());
    }

    public function test_html_z_archivu_je_stala_stranka_i_bez_prihlaseni(): void
    {
        Storage::fake('local');
        $client = $this->klient();

        $url = $this->withToken(self::TOKEN)->post(route('agent.api.archiv.nahrat', 'svet-cejlonu'), [
            'nazev' => 'Google Ads: rozbor a plán',
            'druh' => 'vysledky',
            'soubory' => [UploadedFile::fake()->createWithContent('plan.html', '<title>Plán</title><h1>Rozbor kampaní</h1>')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('stranka');

        $source = KnowledgeSource::query()->sole();
        $this->assertSame(route('archiv.stranka', $source->page_key), $url);
        $this->assertStringContainsString('/stranka/svet-cejlonu-google-ads-rozbor-a-plan-', $url);
        $this->assertSame($url, $source->pageUrl(), 'adresa se nemění');

        $response = $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('<!doctype html>', false)
            ->assertSee('<h1>Rozbor kampaní</h1>', false);
        $this->assertStringStartsWith('sandbox allow-scripts', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));

        $this->prihlasit();
        Livewire::test(ArchiveRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertTableActionVisible('page', $source);
        Livewire::test(ViewKnowledgeSource::class, ['record' => $source->getKey()])
            ->assertSee('Stránka ke čtení')
            ->assertSee($url);

        // Dokument bez HTML stránku nemá, smazaný dokument ji ztratí.
        $text = KnowledgeSource::create(['title' => 'Zápis', 'client_id' => $client->getKey(), 'body' => 'x', 'status' => KnowledgeSource::STATUS_STORED]);
        $this->assertNull($text->pageUrl());
        Livewire::test(ViewKnowledgeSource::class, ['record' => $text->getKey()])->assertDontSee('Stránka ke čtení');

        $source->delete();
        $this->get($url)->assertNotFound();
        $this->get('/stranka/svet-cejlonu-neexistuje')->assertNotFound();
    }

    public function test_uvod_konverzace_se_vejde_i_s_plnym_mozkem_a_archivem(): void
    {
        $client = $this->klient();

        foreach (range(1, 30) as $i) {
            $this->poznatek(['title' => "Obecný poznatek {$i} ".str_repeat('x', 80), 'body' => str_repeat('Dlouhé vysvětlení. ', 40), 'always' => true]);
            $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => "Poznatek klienta {$i} ".str_repeat('y', 80), 'body' => str_repeat('Dlouhé vysvětlení. ', 40)]);
            KnowledgeSource::create(['title' => "Dokument {$i} ".str_repeat('z', 150), 'client_id' => $client->getKey(), 'category' => 'data', 'summary' => str_repeat('Popis obsahu. ', 100), 'body' => 'x']);
        }

        $text = app(KnowledgeBase::class)->opening('svet-cejlonu', $client);

        $this->assertLessThan(10000, mb_strlen($text));
        $this->assertStringContainsString('… a 22 starších', $text);
        $this->assertStringContainsString('taveo archiv-nahrat svet-cejlonu', $text);
        $this->assertStringContainsString('obchod (Obchodní data a marže)', $text);
    }

    public function test_mozek_klienta_u_konverzace_schvali_a_nahraje_bez_odchodu(): void
    {
        Storage::fake('local');
        $this->prihlasit();
        $client = $this->klient();
        $proposed = $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Klient chce víc B2B', 'status' => KnowledgeEntry::STATUS_PROPOSED]);
        $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Do reklam nepsat zdravotní tvrzení']);
        $this->poznatek(['title' => 'Cizí obecný poznatek']);
        Storage::disk('local')->put('mozek/vize.pdf', 'pdf');
        $source = KnowledgeSource::create(['title' => 'Vize 2027', 'client_id' => $client->getKey(), 'summary' => 'Cíle na rok 2027.', 'files' => [['path' => 'mozek/vize.pdf', 'name' => 'vize.pdf']], 'status' => KnowledgeSource::STATUS_STORED]);

        Livewire::test(ClientBrain::class, ['clientId' => $client->getKey()])
            ->assertSee('Mozek klienta Svět Cejlonu')
            ->assertSee('Ke schválení (1)')
            ->assertSee('Do reklam nepsat zdravotní tvrzení')
            ->assertDontSee('Cizí obecný poznatek')
            ->assertSee('Cíle na rok 2027.')
            ->call('approve', $proposed->getKey())
            ->assertSee('Co agent o klientovi ví (2)')
            ->call('download', $source->getKey(), 0)
            ->assertFileDownloaded('vize.pdf')
            ->callAction('upload', [
                'title' => 'Poznámky k Black Friday',
                'category' => 'strategie',
                'body' => 'Jen dárkové sady se slevou 15 %.',
                'learn' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Uloženo do archivu');

        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $proposed->refresh()->status);
        $new = KnowledgeSource::query()->where('title', 'Poznámky k Black Friday')->sole();
        $this->assertSame($client->getKey(), $new->client_id);
        $this->assertSame('Pavel', $new->author);
        $this->assertSame([], $this->tasks);
    }

    public function test_projekt_bez_klienta_ma_mozek_taveo_a_archiv_firmy(): void
    {
        Storage::fake('local');
        $this->prihlasit();
        $client = $this->klient();
        $this->poznatek(['title' => 'Web TAVEO: texty jen přes text() helper']);
        $this->poznatek(['layer' => 'postup', 'title' => 'Jak zveřejnit úpravu webu']);
        $this->poznatek(['layer' => 'klient', 'client_id' => $client->getKey(), 'title' => 'Svět Cejlonu nechce slevy']);
        $firmProposal = $this->poznatek(['title' => 'Ceník služeb platí od 1. 11.', 'status' => KnowledgeEntry::STATUS_PROPOSED]);
        KnowledgeSource::create(['title' => 'Ceník TAVEO 2027', 'category' => 'obchod', 'summary' => 'Ceny balíčků.', 'body' => 'x', 'status' => KnowledgeSource::STATUS_STORED]);
        KnowledgeSource::create(['title' => 'Marže Světa Cejlonu', 'client_id' => $client->getKey(), 'body' => 'x', 'status' => KnowledgeSource::STATUS_STORED]);

        // Úvod konverzace v projektu taveo: firemní poznatky, postupy a archiv firmy, nic o klientech.
        $text = app(KnowledgeBase::class)->opening('taveo', null);
        $this->assertStringContainsString('## Firemní poznatky a postupy', $text);
        $this->assertStringContainsString('Web TAVEO: texty jen přes text() helper', $text);
        $this->assertStringContainsString('Jak zveřejnit úpravu webu', $text);
        $this->assertStringContainsString('## Archiv firmy', $text);
        $this->assertStringContainsString('Ceník TAVEO 2027', $text);
        $this->assertStringContainsString('taveo archiv-nahrat firma', $text);
        $this->assertStringNotContainsString('Svět Cejlonu', $text);

        // API: archiv firmy pod slovem „firma“.
        $this->withToken(self::TOKEN)->getJson(route('agent.api.archiv', 'firma'))
            ->assertOk()->assertJsonPath('klient', 'firma')->assertJsonCount(1, 'dokumenty')->assertJsonPath('dokumenty.0.nazev', 'Ceník TAVEO 2027');
        $this->withToken(self::TOKEN)->postJson(route('agent.api.archiv.nahrat', 'firma'), ['nazev' => 'Zápis z porady TAVEO', 'text' => 'Od listopadu nový ceník.'])->assertCreated();
        $this->assertNull(KnowledgeSource::query()->where('title', 'Zápis z porady TAVEO')->value('client_id'));

        // Panel u konverzace bez klienta.
        Livewire::test(ClientBrain::class, ['clientId' => null])
            ->assertSee('Mozek TAVEO')
            ->assertSee('Firemní poznatky a postupy')
            ->assertSee('Jak zveřejnit úpravu webu')
            ->assertSee('Ceník TAVEO 2027')
            ->assertDontSee('Svět Cejlonu nechce slevy')
            ->assertDontSee('Marže Světa Cejlonu')
            ->assertDontSee('Karta klienta')
            ->call('approve', $firmProposal->getKey())
            ->callAction('upload', ['title' => 'Strategie TAVEO 2027', 'body' => 'Víc e-shopů.', 'learn' => false])
            ->assertHasNoActionErrors();

        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $firmProposal->refresh()->status);
        $this->assertNull(KnowledgeSource::query()->where('title', 'Strategie TAVEO 2027')->value('client_id'));
    }
}
