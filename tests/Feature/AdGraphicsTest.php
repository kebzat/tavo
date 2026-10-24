<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Pages\Ads\ManageAdGraphics;
use App\Filament\Tools\Resources\AdGraphics\Pages\CreateAdGraphic;
use App\Filament\Tools\Resources\AdGraphics\Pages\ListAdGraphics;
use App\Filament\Tools\Resources\AdGraphics\Pages\ViewAdGraphic;
use App\Models\AdGraphic;
use App\Models\AgentJob;
use App\Models\Client;
use App\Models\User;
use App\Settings\AdGraphicsSettings;
use App\Support\AdGraphics\Formats;
use App\Support\AdGraphics\GraphicsAgent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdGraphicsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'agent-token-agent-token-agent-token-1234';

    /** Úloha, kterou dostala služba agenta (poslední). */
    private ?array $task = null;

    /** Využití 5h relace Claude ze služby agenta. */
    private int $usage = 20;

    /** Odpověď Gemini na generování: null = obrázek, jinak chybová odpověď [status, zpráva]. */
    private ?array $geminiError = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajne',
            'services.agent.api_token' => self::TOKEN,
            'services.clickup.max_usage_percent' => 90,
        ]);

        Http::fake([
            'agent.test/api/projekty/*/ulohy' => function (Request $request) {
                $this->task = $request->data();

                return Http::response(['zarazeno' => true], 202);
            },
            'agent.test/api/predplatne' => fn () => Http::response(['plan' => 'max', 'limity' => [
                ['kind' => 'session', 'percent' => $this->usage, 'resets_at' => now()->addHours(2)->toIso8601String()],
            ]]),
            'agent.test/api/projekty' => Http::response([['slug' => 'svet-cejlonu', 'typ' => 'prazdny', 'zdroj' => '', 'vlakna' => []]]),
            'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent' => fn () => $this->geminiError
                ? Http::response(['error' => ['code' => $this->geminiError[0], 'message' => $this->geminiError[1]]], $this->geminiError[0])
                : Http::response(['candidates' => [[
                    'content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('PNGDATA')]]]],
                ]]]),
            'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image' => Http::response(['displayName' => 'Nano Banana']),
        ]);
    }

    private function klient(): Client
    {
        return Client::query()->updateOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu', 'agent_project' => 'svet-cejlonu', 'website_url' => 'https://www.svetcejlonu.cz']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function zadani(array $attributes = []): AdGraphic
    {
        return AdGraphic::create([
            'client_id' => $this->klient()->getKey(),
            'project' => 'svet-cejlonu',
            'title' => 'Modrý čaj',
            'brief' => 'Modrý čaj, po citronu zfialoví. Od 99 Kč.',
            'formats' => Formats::resolve(['ig_post', 'story'], []),
            'variants' => 5,
            'website_url' => 'https://www.svetcejlonu.cz',
            'author' => 'Tom',
            ...$attributes,
        ]);
    }

    private function klic(?string $key = 'AIza-test'): void
    {
        $settings = app(AdGraphicsSettings::class);
        $settings->gemini_api_key = $key;
        $settings->save();
    }

    private function prihlasit(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tom']));
        Filament::setCurrentPanel('tools');
    }

    /** Token, který dostal agent v zadání. */
    private function tokenZeZadani(): string
    {
        preg_match('/X-Grafika-Token: ([A-Za-z0-9]+)/', $this->task['zadani'], $match);

        return $match[1];
    }

    private function vysledek(AgentJob $job, array $data): void
    {
        $this->withToken(self::TOKEN)->postJson("/nastroje/api/agent/ulohy/{$job->getKey()}/vysledek", $data)->assertOk();
    }

    public function test_zadani_z_formulare_posle_agentovi_ulohu_s_formaty_stylem_a_podklady(): void
    {
        Storage::fake('local');
        $this->prihlasit();
        $client = $this->klient();

        Livewire::test(CreateAdGraphic::class)
            ->fillForm([
                'client_id' => $client->getKey(),
                'project' => 'svet-cejlonu',
                'title' => 'Modrý čaj na podzim',
                'variants' => 3,
                'brief' => 'Modrý čaj z motýlího květu, po citronu zfialoví.',
                'presets' => ['ig_post'],
                'custom_formats' => [['label' => 'Newsletter', 'width' => 600, 'height' => 300]],
                'fonts' => 'Lora',
                // Jednoduchý repeater drží uvnitř položky jako ['value' => …], ven dává plochý seznam.
                'colors' => [['value' => '#667c32']],
                'asset_paths' => [UploadedFile::fake()->image('logo firmy.png')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $graphic = AdGraphic::query()->sole();
        $this->assertSame(AdGraphic::STATUS_RUNNING, $graphic->status);
        $this->assertSame([['label' => 'Příspěvek IG / FB na výšku', 'width' => 1080, 'height' => 1350], ['label' => 'Newsletter', 'width' => 600, 'height' => 300]], $graphic->formats);
        $this->assertSame('logo firmy.png', $graphic->assets[0]['name']);
        $this->assertFalse($graphic->ai_images, 'bez klíče se obrázky negenerují');

        $job = $graphic->agentJob;
        $this->assertSame(GraphicsAgent::SOURCE, $job->source);
        $this->assertSame($job->getKey(), $this->task['id']);
        $this->assertNull($job->clickup_task_id);

        $prompt = $this->task['zadani'];
        $this->assertStringContainsString('/projekty/svet-cejlonu/predani/grafika-'.$graphic->getKey(), $prompt);
        $this->assertStringContainsString('Newsletter 600×300', $prompt);
        $this->assertStringContainsString('Udělej 3 různých návrhů', $prompt);
        $this->assertStringContainsString('Barvy: #667c32', $prompt);
        $this->assertStringContainsString('/nastroje/api/grafika/'.$graphic->getKey().'/podklady/0', $prompt);
        $this->assertStringContainsString('01-logo-firmy.png', $prompt);
        $this->assertStringContainsString('Generování obrázků je vypnuté', $prompt);
        $this->assertStringContainsString('create_new_file', $prompt);
        $this->assertTrue($graphic->tokenMatches($this->tokenZeZadani()));
        $this->assertTrue($this->task['terminal'], 'úloha běží v okně, ať jde sledovat');
    }

    public function test_bezici_ulohu_jde_sledovat_v_terminalu_jen_ke_cteni(): void
    {
        $this->prihlasit();
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);
        $session = $graphic->refresh()->agentJob->session;

        $okna = [];
        Http::fake(['agent.test/api/projekty/svet-cejlonu/konverzace' => function () use (&$okna) {
            return Http::response($okna);
        }]);

        // Úloha ještě čeká ve frontě, okno nemá.
        $page = Livewire::test(ViewAdGraphic::class, ['record' => $graphic->getKey()])->assertOk()->assertSee('Co agent dělá');
        $this->assertNull($page->instance()->terminalUrl());

        $okna = [
            ['session' => 'cizi-konverzace', 'okno' => '@2', 'uloha' => false],
            ['session' => $session, 'okno' => '@7', 'uloha' => true],
        ];
        preg_match('/\?t=([^.]+)\./', (string) $page->instance()->terminalUrl(), $match);
        $ticket = json_decode(base64_decode(strtr($match[1], '-_', '+/')), true);
        $this->assertSame(['p' => 'svet-cejlonu', 'v' => '@7', 'r' => 1], array_intersect_key($ticket, array_flip(['p', 'v', 'r'])));

        // Zasáhnout: vstupenka, se kterou jde psát.
        preg_match('/\?t=([^.]+)\./', (string) $page->instance()->terminalUrl(write: true), $match);
        $this->assertArrayNotHasKey('r', json_decode(base64_decode(strtr($match[1], '-_', '+/')), true));

        // Po doběhnutí okno zůstává: pokračovat v konverzaci s kontextem.
        $graphic->update(['status' => AdGraphic::STATUS_DONE, 'finished_at' => now()]);
        $page = Livewire::test(ViewAdGraphic::class, ['record' => $graphic->getKey()])->assertDontSee('Co agent dělá')->assertSee('Pokračovat s agentem');
        preg_match('/\?t=([^.]+)\./', (string) $page->instance()->terminalUrl(write: true), $match);
        $this->assertSame('@7', json_decode(base64_decode(strtr($match[1], '-_', '+/')), true)['v']);
    }

    public function test_uspanou_konverzaci_jde_z_detailu_probudit(): void
    {
        $this->prihlasit();
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);
        $session = $graphic->refresh()->agentJob->session;
        $graphic->update(['status' => AdGraphic::STATUS_DONE, 'finished_at' => now()]);

        Http::fake(['agent.test/api/projekty/svet-cejlonu/konverzace' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response(['okno' => '@9'], 201)
            : Http::response([['session' => $session, 'okno' => null, 'uloha' => false]])]);

        $page = Livewire::test(ViewAdGraphic::class, ['record' => $graphic->getKey()]);
        $this->assertNull($page->instance()->terminalUrl(write: true), 'spí, okno nemá');

        $page->call('wake');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/konverzace')
            && $request['session'] === $session
            && $request['uloha'] === ['id' => $graphic->agent_job_id, 'konec' => '[/grafika]']);
    }

    public function test_bez_formatu_se_zadani_neulozi(): void
    {
        $this->prihlasit();

        Livewire::test(CreateAdGraphic::class)
            ->fillForm(['project' => 'svet-cejlonu', 'title' => 'X', 'variants' => 5, 'brief' => 'Y', 'presets' => []])
            ->call('create')
            ->assertHasFormErrors(['presets']);

        $this->assertSame(0, AdGraphic::query()->count());
    }

    public function test_vysledek_agenta_ulozi_navrhy_a_figmu_a_token_plati_jeste_pro_pokracovani(): void
    {
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);
        $job = $graphic->refresh()->agentJob;

        $this->vysledek($job, ['ok' => true, 'session' => $job->session, 'vysledek' => <<<'TXT'
            Pět návrhů, dva ve Figmě. Zkontrolujte ceny.
            [grafika]
            {"figma": "https://www.figma.com/design/17isApmzgHZ0S8lM2kGUZh", "varianty": [
              {"soubor": "01-kapnete-citron-1080x1350.png", "nazev": "Kápněte citron", "format": "IG 1080×1350", "text": "Pár kapek citronu."},
              {"soubor": "../tajne.png", "nazev": "zlý", "format": "", "text": ""}
            ]}
            [/grafika]
            TXT]);

        $graphic->refresh();
        $this->assertSame(AdGraphic::STATUS_DONE, $graphic->status);
        $this->assertSame('Pět návrhů, dva ve Figmě. Zkontrolujte ceny.', $graphic->result);
        $this->assertSame('https://www.figma.com/design/17isApmzgHZ0S8lM2kGUZh', $graphic->figma_url);
        $this->assertCount(1, $graphic->outputs, 'soubor mimo složku se zahodí');
        $this->assertNotNull($graphic->token_hash, 'konverzace zůstává otevřená, agent může ještě generovat');
        $this->assertSame(AgentJob::STATUS_DONE, $job->refresh()->status);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'clickup'));
    }

    public function test_dalsi_vysledek_z_pokracovani_konverzace_obnovi_galerii(): void
    {
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);
        $job = $graphic->refresh()->agentJob;

        // Agent shodil sám sebe těsně před koncem, úloha skončila chybou.
        $this->vysledek($job, ['ok' => false, 'chyba' => 'Claude skončil bez výsledku.']);
        $this->assertSame(AdGraphic::STATUS_FAILED, $graphic->refresh()->status);

        // Člověk v konverzaci napsal, agent odpověděl závěrem: kolo je hotové.
        $blok = '[grafika]{"figma": "https://www.figma.com/design/ABC", "varianty": [{"soubor": "01-a-1080x1350.png", "nazev": "A", "format": "IG", "text": ""}]}[/grafika]';
        $this->vysledek($job, ['ok' => true, 'vysledek' => "Hotovo.\n".$blok]);
        $graphic->refresh();
        $this->assertSame(AdGraphic::STATUS_DONE, $graphic->status);
        $this->assertNull($graphic->error);
        $this->assertSame('01-a-1080x1350.png', $graphic->outputs[0]['soubor']);

        // Další změna vymění návrhy, odpověď bez bloku galerii nechá.
        $this->vysledek($job, ['ok' => true, 'vysledek' => str_replace('01-a-', '02-b-', $blok)]);
        $this->vysledek($job, ['ok' => true, 'vysledek' => 'Jen odpověď na otázku.']);
        $graphic->refresh();
        $this->assertSame(AdGraphic::STATUS_DONE, $graphic->status);
        $this->assertSame('02-b-1080x1350.png', $graphic->outputs[0]['soubor']);
    }

    public function test_vysledek_bez_navrhu_je_chyba(): void
    {
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);

        $this->vysledek($graphic->refresh()->agentJob, ['ok' => true, 'vysledek' => 'Nestihl jsem to.']);

        $this->assertSame(AdGraphic::STATUS_FAILED, $graphic->refresh()->status);
        $this->assertStringContainsString('nevrátil žádný návrh', $graphic->error);
    }

    public function test_agent_si_stahne_podklad_jen_se_spravnym_tokenem_a_jen_behem_prace_a_den_po_ni(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reklamni-grafika/abc.png', 'LOGO');
        $graphic = $this->zadani(['assets' => [['path' => 'reklamni-grafika/abc.png', 'name' => 'logo.png']]]);
        app(GraphicsAgent::class)->start($graphic);
        $token = $this->tokenZeZadani();
        $url = "/nastroje/api/grafika/{$graphic->getKey()}/podklady/0";

        $this->get($url, ['X-Grafika-Token' => 'spatny'])->assertUnauthorized();
        $this->get($url, ['X-Grafika-Token' => $token])->assertOk()->assertDownload('logo.png');
        $this->get("/nastroje/api/grafika/{$graphic->getKey()}/podklady/5", ['X-Grafika-Token' => $token])->assertNotFound();

        // Po doběhnutí ještě den (pokračování v konverzaci), pak už ne.
        $this->vysledek($graphic->refresh()->agentJob, ['ok' => false, 'chyba' => 'konec']);
        $this->get($url, ['X-Grafika-Token' => $token])->assertOk();
        $this->travel(2)->days();
        $this->get($url, ['X-Grafika-Token' => $token])->assertStatus(409);
    }

    public function test_obrazek_z_gemini_s_klicem_na_webu_a_limitem(): void
    {
        $this->klic();
        app(AdGraphicsSettings::class)->fill(['max_images' => 2])->save();
        $graphic = $this->zadani(['ai_images' => true]);
        app(GraphicsAgent::class)->start($graphic);
        $token = $this->tokenZeZadani();
        $this->assertStringContainsString('Smíš vygenerovat nejvýš 2 obrázků', $this->task['zadani']);
        $this->assertStringNotContainsString('AIza-test', $this->task['zadani'], 'klíč agent nevidí');

        $url = "/nastroje/api/grafika/{$graphic->getKey()}/obrazek";
        $response = $this->post($url, ['popis' => 'Blue tea cup, soft light', 'pomer' => '1080:1350', 'predloha' => [UploadedFile::fake()->image('caj.jpg')]], ['X-Grafika-Token' => $token]);

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame('PNGDATA', $response->getContent());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), ':generateContent')
            && $request->header('x-goog-api-key') === ['AIza-test']
            && $request['generationConfig']['imageConfig']['aspectRatio'] === '4:5'
            && count($request['contents'][0]['parts']) === 2);

        $this->post($url, ['popis' => 'Druhý'], ['X-Grafika-Token' => $token])->assertOk();
        $this->post($url, ['popis' => 'Třetí'], ['X-Grafika-Token' => $token])->assertStatus(429);
        $this->assertSame(2, $graphic->refresh()->ai_images_used);
    }

    public function test_bez_povoleni_nebo_klice_obrazek_nejde(): void
    {
        $graphic = $this->zadani(['ai_images' => true]);
        app(GraphicsAgent::class)->start($graphic);

        $this->assertStringContainsString('Generování obrázků je vypnuté', $this->task['zadani']);
        $token = $graphic->refresh()->issueToken();

        $this->post("/nastroje/api/grafika/{$graphic->getKey()}/obrazek", ['popis' => 'X'], ['X-Grafika-Token' => $token])->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'generativelanguage'));
    }

    public function test_uprava_navaze_na_konverzaci_a_posledni_navrhy(): void
    {
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);
        $job = $graphic->refresh()->agentJob;
        $this->vysledek($job, ['ok' => true, 'session' => $job->session, 'vysledek' => 'Hotovo. [grafika]{"figma": "https://www.figma.com/design/ABC123", "varianty": [{"soubor": "01-a.png", "nazev": "A", "format": "IG", "text": ""}]}[/grafika]']);

        $revision = app(GraphicsAgent::class)->revise($graphic->refresh(), 'U jedničky větší cenu.', 'Pavel');

        $this->assertSame($graphic->getKey(), $revision->parent_id);
        $this->assertSame(AdGraphic::STATUS_RUNNING, $revision->status);
        $this->assertSame($job->session, $revision->agentJob->session);
        $this->assertSame($job->session, $this->task['session']);
        $this->assertStringContainsString('U jedničky větší cenu.', $this->task['zadani']);
        $this->assertStringContainsString('predani/grafika-'.$graphic->getKey().'/', $this->task['zadani']);
        $this->assertStringContainsString('01-a.png (A, IG)', $this->task['zadani']);
        $this->assertStringContainsString('existujícího souboru https://www.figma.com/design/ABC123', $this->task['zadani']);

        // Druhá úprava vychází z první úpravy, pořád pod prvním zadáním.
        $this->vysledek($revision->agentJob, ['ok' => true, 'vysledek' => '[grafika]{"varianty": [{"soubor": "01-a-v2.png", "nazev": "A2", "format": "IG", "text": ""}]}[/grafika]']);
        $second = app(GraphicsAgent::class)->revise($graphic->refresh(), 'Ještě menší logo.', 'Pavel');
        $this->assertSame($graphic->getKey(), $second->parent_id);
        $this->assertStringContainsString('predani/grafika-'.$revision->getKey().'/', $this->task['zadani']);
        $this->assertSame(3, $graphic->rounds()->count());
    }

    public function test_vycerpane_predplatne_agenta_nespusti(): void
    {
        $this->usage = 95;
        $graphic = $this->zadani();

        app(GraphicsAgent::class)->start($graphic);

        $this->assertSame(AdGraphic::STATUS_FAILED, $graphic->refresh()->status);
        $this->assertStringContainsString('vyčerpané z 95 %', $graphic->error);
        $this->assertNull($this->task);
    }

    public function test_ztracena_uloha_grafiky_se_uzavre_bez_clickupu(): void
    {
        $graphic = $this->zadani();
        app(GraphicsAgent::class)->start($graphic);
        $graphic->agentJob->update(['started_at' => now()->subHours(3)]);

        $this->artisan('clickup:hlidat')->assertSuccessful();

        $this->assertSame(AdGraphic::STATUS_FAILED, $graphic->refresh()->status);
        $this->assertStringContainsString('nedoběhla', $graphic->error);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'clickup'));
    }

    public function test_stranky_v_nastrojich(): void
    {
        $this->prihlasit();
        $graphic = $this->zadani(['status' => AdGraphic::STATUS_DONE, 'figma_url' => 'https://www.figma.com/design/ABC', 'outputs' => [
            ['soubor' => '01-a-1080x1350.png', 'nazev' => 'Kápněte citron', 'format' => 'IG 1080×1350', 'text' => 'Pár kapek citronu.'],
        ]]);

        Livewire::test(ListAdGraphics::class)->assertOk()->assertSee('Modrý čaj');
        Livewire::test(ViewAdGraphic::class, ['record' => $graphic->getKey()])
            ->assertOk()
            ->assertSee('Kápněte citron')
            ->assertSee('Pár kapek citronu.')
            ->assertSee('/nastroje/agent/svet-cejlonu/snimek/grafika-'.$graphic->getKey().'/01-a-1080x1350.png', false)
            ->assertActionVisible('revise')
            ->assertActionVisible('figma');
        Livewire::test(ManageAdGraphics::class)->assertOk()->assertSee('Vypnuté, chybí klíč');
    }

    public function test_klic_jde_overit_a_uklada_se_sifrovane(): void
    {
        $this->prihlasit();
        $this->klic('AIza-tajny');

        Livewire::test(ManageAdGraphics::class)->callAction('check')->assertNotified('Klíč platí');
        Livewire::test(ManageAdGraphics::class)->callAction('try')->assertNotified('Obrázky fungují');

        $stored = DB::table('settings')->where('group', 'grafika')->where('name', 'gemini_api_key')->value('payload');
        $this->assertStringNotContainsString('AIza-tajny', (string) $stored);
    }

    public function test_bezplatny_tarif_googlu_vysvetli_a_ukaze_v_detailu_i_nastaveni(): void
    {
        $this->prihlasit();
        $this->klic();
        $this->geminiError = [429, 'You exceeded your current quota. * Quota exceeded for metric: generativelanguage.googleapis.com/generate_content_free_tier_requests, limit: 0, model: gemini-2.5-flash-preview-image'];
        $graphic = $this->zadani(['ai_images' => true]);
        app(GraphicsAgent::class)->start($graphic);

        $this->post("/nastroje/api/grafika/{$graphic->getKey()}/obrazek", ['popis' => 'Tea'], ['X-Grafika-Token' => $this->tokenZeZadani()])
            ->assertStatus(502)
            ->assertJsonPath('chyba', fn (string $chyba): bool => str_contains($chyba, 'bezplatném tarifu'));
        $this->assertStringContainsString('Billing', (string) $graphic->refresh()->image_error);
        $this->assertStringContainsString("-w '%{http_code}\\n'", $this->task['zadani'], 'agent chybu uvidí');

        Livewire::test(ViewAdGraphic::class, ['record' => $graphic->getKey()])->assertSee('Obrázky z Nano Banana se nevygenerovaly');
        Livewire::test(ManageAdGraphics::class)
            ->assertSee('Poslední chyba při generování')
            ->callAction('try')
            ->assertNotified('Obrázek se nevygeneroval');
    }

    public function test_pomer_stran_pro_gemini(): void
    {
        $this->assertSame('4:5', Formats::aspectRatio(1080, 1350));
        $this->assertSame('9:16', Formats::aspectRatio(1080, 1920));
        $this->assertSame('16:9', Formats::aspectRatio(1200, 628));
        $this->assertSame('5:4', Formats::aspectRatio(300, 250));
        $this->assertSame('1:1', Formats::aspectRatio(1080, 1080));
    }
}
