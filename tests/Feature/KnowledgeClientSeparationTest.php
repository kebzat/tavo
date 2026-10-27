<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WorkArea;
use App\Filament\Tools\Livewire\ClientBrain;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\CreateKnowledgeEntry;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\EditKnowledgeEntry;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use App\Models\AgentJob;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Support\Knowledge\ClientMentions;
use App\Support\Knowledge\KnowledgeBase;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Klienti se v mozku nemíchají: co ví mozek o Snowbearu (objednávky,
 * čísla), nedostane agent Světa Cejlonu ani firemní mozek. Viz docs/MOZEK.md,
 * Oddělení klientů.
 */
class KnowledgeClientSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'agent-token-agent-token-agent-token-1234';

    /** Text poznatku o objednávkách Snowbearu, který nikdo jiný dostat nesmí. */
    private const ORDERS = 'E-shop 1 117 objednávek, Pokladna 2 445';

    private Client $snowbear;

    private Client $cejlon;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.api_token' => self::TOKEN]);

        $this->snowbear = Client::query()->updateOrCreate(['slug' => 'snowbear'], ['name' => 'Snowbear', 'agent_project' => 'snowbear']);
        $this->cejlon = Client::query()->updateOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu', 'agent_project' => 'svet-cejlonu']);

        foreach ([['develon', 'Develon', 'develon'], ['pizza-di-gomma', 'Pizza di Gomma', 'pizzadigomma'], ['ceska-louka', 'Česká louka', 'ceskalouka'], ['jmp', 'JMP', 'jmp'], ['jmp-stroje', 'JMP stroje', 'jmp-stroje'], ['grill-nor', 'Grill Nor', 'grillnor']] as [$slug, $name, $project]) {
            Client::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'agent_project' => $project]);
        }

        $this->entry(['layer' => 'klient', 'client_id' => $this->snowbear->getKey(), 'title' => 'Snowbear: export objednávek obsahuje e-shop i prodejnu', 'body' => self::ORDERS.', před rozborem oddělit.', 'tags' => ['objednavky']]);
        $this->entry(['layer' => 'klient', 'client_id' => $this->cejlon->getKey(), 'title' => 'Svět Cejlonu: objednávky nese modrý čaj', 'body' => 'Polovina objednávek je modrý čaj.', 'tags' => ['objednavky']]);
        $this->entry(['layer' => 'postup', 'title' => 'Rozbor exportu objednávek ze Shoptetu', 'body' => 'Jeden řádek je položka, objednávky seskupit podle kódu.', 'tags' => ['objednavky']]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function entry(array $attributes): KnowledgeEntry
    {
        return KnowledgeEntry::create(['layer' => 'firma', 'status' => KnowledgeEntry::STATUS_APPROVED, ...$attributes]);
    }

    /** @return array<string, string> volání z konverzace v projektu (taveo posílá projekt podle složky) */
    private function fromProject(string $project): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN, 'X-Taveo-Projekt' => $project, 'Accept' => 'application/json'];
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']));
        Filament::setCurrentPanel('tools');
    }

    public function test_jmena_klientu_pozna_i_v_jinem_pade_a_neplete_si_je_s_beznymi_slovy(): void
    {
        $mentions = app(ClientMentions::class);
        $found = fn (string $text, ?Client $own = null): array => $mentions->in($text, $own)->pluck('slug')->sort()->values()->all();

        $this->assertSame(['snowbear'], $found('Vzor: archiv Snowbearu, id 42'));
        $this->assertSame(['snowbear'], $found('projekt snowbear, kod/google-ads/export-historie.js'));
        $this->assertSame(['svet-cejlonu'], $found('U Světa Cejlonu by další slidy udělaly karusel.'));
        $this->assertSame(['pizza-di-gomma'], $found('Ověřeno na pizzadigomma.eu'));
        $this->assertSame(['grill-nor'], $found('nadpis úvodní stránky Grill Noru'));
        $this->assertSame(['ceska-louka'], $found('Pro České louky platí jiné ceny.'));
        $this->assertSame(['jmp', 'jmp-stroje'], $found('Agent JMP stroje'));

        // Obecný text bez klienta: developer není Develon, pizza není Pizza di Gomma.
        $this->assertSame([], $found('Developer pečl pizzu. Česká republika, stroje, louka, Shoptet, Upgates, Meta a Google Ads.'));
        // Poznatek klienta JMP stroje, který se jmenuje „JMP stroje“, nezmiňuje klienta JMP.
        $this->assertSame([], $found('JMP stroje chtějí report v PDF.', Client::query()->where('slug', 'jmp-stroje')->first()));
    }

    public function test_agent_jineho_klienta_poznatky_snowbearu_nenajde_ani_nedostane(): void
    {
        $knowledge = app(KnowledgeBase::class);

        // Hledání pro Svět Cejlonu: jeho a firemní, Snowbear nikdy.
        $forCejlon = $knowledge->search('objednávky export', $this->cejlon)->pluck('title')->all();
        $this->assertContains('Svět Cejlonu: objednávky nese modrý čaj', $forCejlon);
        $this->assertContains('Rozbor exportu objednávek ze Shoptetu', $forCejlon);
        $this->assertNotContains('Snowbear: export objednávek obsahuje e-shop i prodejnu', $forCejlon);

        // Bez klienta (firemní mozek, projekt taveo) jen firemní a postupy.
        $this->assertSame(['Rozbor exportu objednávek ze Shoptetu'], $knowledge->search('objednávky export')->pluck('title')->all());

        // Zadání úlohy z ClickUpu a úvod konverzace v projektu.
        foreach ([$knowledge->briefing($this->cejlon), $knowledge->opening('svet-cejlonu', $this->cejlon), $knowledge->opening('taveo', null), $knowledge->briefing(null)] as $text) {
            $this->assertStringNotContainsString(self::ORDERS, $text);
            $this->assertStringNotContainsString('jiní klienti', $text);
        }

        $this->assertStringContainsString('Klienti se nemíchají', $knowledge->opening('svet-cejlonu', $this->cejlon));
        $this->assertStringContainsString('&klient=svet-cejlonu', $knowledge->briefing($this->cejlon));
        // Snowbear sám svoje poznatky dostane.
        $this->assertStringContainsString(self::ORDERS, $knowledge->opening('snowbear', $this->snowbear));

        // API bez klienta: firemní, s klientem jen jeho.
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek', ['q' => 'objednávky']))
            ->assertOk()->assertJsonCount(1, 'poznatky')->assertJsonMissing(['klient' => 'snowbear']);
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek', ['q' => 'objednávky', 'klient' => 'svet-cejlonu']))
            ->assertOk()->assertJsonCount(2, 'poznatky')->assertJsonMissing(['klient' => 'snowbear']);
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek.seznam', ['klient' => 'svet-cejlonu']))
            ->assertOk()->assertJsonMissing(['klient' => 'snowbear'])->assertJsonFragment(['klient' => 'svet-cejlonu']);
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek.seznam'))
            ->assertOk()->assertJsonCount(1)->assertJsonMissing(['vrstva' => 'klient']);
    }

    public function test_vzdy_v_hlave_klienta_nedostanou_ostatni_klienti(): void
    {
        $this->entry(['layer' => 'klient', 'client_id' => $this->snowbear->getKey(), 'title' => 'Snowbear: marže lyží 32 %', 'body' => 'K 9/2026.', 'always' => true]);
        $this->entry(['title' => 'Klientům neslibujeme ROAS', 'body' => 'Slibujeme postup.', 'always' => true]);
        $knowledge = app(KnowledgeBase::class);

        foreach ([$knowledge->briefing($this->cejlon), $knowledge->opening('svet-cejlonu', $this->cejlon), $knowledge->opening('taveo', null)] as $text) {
            $this->assertStringContainsString('Klientům neslibujeme ROAS', $text);
            $this->assertStringNotContainsString('marže lyží', $text);
        }

        $this->assertStringContainsString('marže lyží', $knowledge->briefing($this->snowbear));
    }

    public function test_firemni_poznatek_se_jmenem_klienta_dostane_jen_ten_klient(): void
    {
        // Jako #97 a #98 v mozku k 27. 10. 2026: firemní, ale se Snowbearem a jeho archivem.
        $this->entry(['title' => 'Plán pro Toma: jeden seznam úkolů', 'body' => 'Tom (plán Google Ads pro Snowbear) chce plány laicky. Vzor: archiv Snowbear id 42.']);
        $this->entry(['layer' => 'postup', 'title' => 'Rozbor objednávek v xlsx', 'body' => 'Objednávky seskupit.', 'origin' => 'Rozbor exportu objednávek Snowbear (01-orders.xlsx)']);
        $knowledge = app(KnowledgeBase::class);

        foreach ([$knowledge->opening('taveo', null), $knowledge->opening('svet-cejlonu', $this->cejlon), $knowledge->briefing($this->cejlon)] as $text) {
            $this->assertStringNotContainsString('Snowbear', $text);
        }

        $this->assertNotContains('Plán pro Toma: jeden seznam úkolů', $knowledge->search('plán Toma')->pluck('title'));
        $this->assertNotContains('Rozbor objednávek v xlsx', $knowledge->search('objednávky xlsx', $this->cejlon)->pluck('title'));
        $this->assertContains('Plán pro Toma: jeden seznam úkolů', $knowledge->search('plán Toma', $this->snowbear)->pluck('title'));
    }

    public function test_navrh_ktery_micha_klienty_web_odmitne_a_rekne_proc(): void
    {
        $archive = KnowledgeSource::create(['title' => 'Export objednávek', 'client_id' => $this->snowbear->getKey(), 'body' => 'x']);

        $response = $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), [
            'zdroj' => $archive->getKey(),
            'poznatky' => [
                ['vrstva' => 'firma', 'nadpis' => 'E-shopy prodávají hlavně v zimě', 'text' => 'Snowbear má v prosinci 40 % objednávek.'],
                ['vrstva' => 'postup', 'nadpis' => 'Export ze Shoptetu má řádek na položku', 'text' => 'Seskupit podle kódu.', 'puvod' => 'Export objednávek Snowbearu'],
                ['vrstva' => 'klient', 'klient' => 'svet-cejlonu', 'nadpis' => 'Cejlon prodává v zimě', 'text' => 'Čaj v zimě.'],
                ['vrstva' => 'klient', 'nadpis' => 'Snowbear: prosinec má 40 % objednávek', 'text' => 'Podle exportu za 2023–2026, i se Světem Cejlonu.'],
                ['vrstva' => 'klient', 'nadpis' => 'Snowbear: prosinec je nejsilnější', 'text' => 'Podle exportu za 2023–2026 má prosinec 40 % objednávek e-shopu.'],
                ['vrstva' => 'postup', 'nadpis' => 'Export ze Shoptetu: řádek je položka', 'text' => 'Objednávky seskupit podle kódu, dopravu a platbu vynechat.', 'puvod' => 'Export objednávek klienta na Shoptetu, 10/2026'],
            ],
        ])->assertOk()->assertJson(['zalozeno' => 2]);

        $reasons = collect($response->json('proc'))->pluck('proc', 'nadpis');
        $this->assertStringContainsString('Zmiňuje klienta (Snowbear)', $reasons['E-shopy prodávají hlavně v zimě']);
        $this->assertStringContainsString('Zmiňuje klienta (Snowbear)', $reasons['Export ze Shoptetu má řádek na položku']);
        $this->assertStringContainsString('jiného klienta (svet-cejlonu)', $reasons['Cejlon prodává v zimě']);
        $this->assertStringContainsString('Zmiňuje jiného klienta (Svět Cejlonu)', $reasons['Snowbear: prosinec má 40 % objednávek']);

        // Poznatek klienta bez slugu z podkladů klienta patří tomu klientovi.
        $this->assertSame($this->snowbear->getKey(), KnowledgeEntry::query()->where('title', 'Snowbear: prosinec je nejsilnější')->value('client_id'));
        $this->assertNull(KnowledgeEntry::query()->where('title', 'Export ze Shoptetu: řádek je položka')->value('client_id'));
    }

    public function test_poznatek_ze_zaveru_ulohy_jde_jen_k_jejimu_klientovi(): void
    {
        $job = AgentJob::create(['source' => 'clickup', 'client_id' => $this->cejlon->getKey(), 'project' => 'svet-cejlonu', 'task_name' => 'Svět Cejlonu: banner na Vánoce', 'instruction' => 'Banner', 'status' => AgentJob::STATUS_RUNNING]);

        [, $created] = app(KnowledgeBase::class)->capture($job, implode("\n", [
            'Hotovo.',
            '[poznatek klient snowbear]', 'Snowbear chce bannery modře', 'Viděl jsem v jeho složce.', '[/poznatek]',
            '[poznatek firma]', 'Bannery na Upgates nahrávat ve WebP', 'Rychlejší načtení.', '[/poznatek]',
            '[poznatek]', 'Svět Cejlonu chce na banneru dárek', 'Ne slevu.', '[/poznatek]',
        ]), 'clickup');

        $this->assertSame(2, $created);
        $this->assertFalse(KnowledgeEntry::query()->where('title', 'Snowbear chce bannery modře')->exists());
        // Firemní poznatek z úlohy klienta nemá v původu název úlohy se jménem klienta.
        $firm = KnowledgeEntry::query()->where('title', 'Bannery na Upgates nahrávat ve WebP')->sole();
        $this->assertNull($firm->client_id);
        $this->assertStringNotContainsString('Cejlon', (string) $firm->origin);
        $this->assertSame($this->cejlon->getKey(), KnowledgeEntry::query()->where('title', 'Svět Cejlonu chce na banneru dárek')->value('client_id'));
    }

    public function test_konverzace_v_projektu_klienta_k_jinemu_klientovi_nepusti(): void
    {
        $theirs = KnowledgeSource::create(['title' => 'Export objednávek Snowbear', 'client_id' => $this->snowbear->getKey(), 'body' => self::ORDERS]);
        $firm = KnowledgeSource::create(['title' => 'Ceník TAVEO', 'body' => 'Ceny.']);
        $task = $this->snowbear->tasks()->create(['title' => 'Nastavit Merchant Center', 'area' => WorkArea::Web, 'status' => TaskStatus::InProgress]);
        $cejlon = $this->fromProject('svet-cejlonu');

        foreach ([
            ['GET', route('agent.api.client', 'snowbear')],
            ['GET', route('agent.api.archiv', 'snowbear')],
            ['GET', route('agent.api.mozek', ['q' => 'objednávky', 'klient' => 'snowbear'])],
            ['GET', route('agent.api.mozek.zdroj', $theirs)],
            ['GET', route('agent.api.mozek.zdroj.popis', $theirs)],
            ['POST', route('agent.api.archiv.nahrat', 'snowbear')],
            ['POST', route('agent.api.tasks.status', $task)],
        ] as [$method, $url]) {
            $this->json($method, $url, [], $cejlon)->assertForbidden()
                ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'klienti se nemíchají'));
        }

        // Svůj klient a firemní věci jdou.
        $this->getJson(route('agent.api.archiv', 'svet-cejlonu'), $cejlon)->assertOk();
        $this->getJson(route('agent.api.archiv', 'firma'), $cejlon)->assertOk();
        $this->get(route('agent.api.mozek.zdroj', $firm), $cejlon)->assertOk()->assertSee('Ceny.');
        // Hledání bez slugu v projektu klienta hledá u něj.
        $this->getJson(route('agent.api.mozek', ['q' => 'objednávky']), $cejlon)->assertOk()->assertJsonCount(2, 'poznatky')->assertJsonFragment(['klient' => 'svet-cejlonu']);
        // Návrh pro jiného klienta z projektu klienta neprojde.
        $this->postJson(route('agent.api.mozek.navrhy'), ['poznatky' => [['vrstva' => 'klient', 'klient' => 'snowbear', 'nadpis' => 'Snowbear x', 'text' => 'y']]], $cejlon)
            ->assertOk()->assertJson(['zalozeno' => 0])->assertJsonPath('proc.0.proc', fn (string $why): bool => str_contains($why, 'Pracuješ pro klienta Svět Cejlonu'));
        $this->postJson(route('agent.api.mozek.navrhy'), ['poznatky' => [['vrstva' => 'klient', 'nadpis' => 'Cejlon chce report v PDF', 'text' => 'Mailem.']]], $cejlon)
            ->assertOk()->assertJson(['zalozeno' => 1]);
        $this->assertSame($this->cejlon->getKey(), KnowledgeEntry::query()->where('title', 'Cejlon chce report v PDF')->value('client_id'));

        // Projekt bez klienta (taveo, google-reklamy) omezený není.
        $this->getJson(route('agent.api.archiv', 'snowbear'), $this->fromProject('google-reklamy'))->assertOk()->assertJsonPath('dokumenty.0.nazev', 'Export objednávek Snowbear');
    }

    public function test_v_nastrojich_poznatek_ktery_micha_klienty_nejde_schvalit_ani_ulozit(): void
    {
        $this->signIn();
        $mixed = $this->entry(['title' => 'Plány pro Toma bez zaškrtávání', 'body' => 'Vzor: archiv Snowbear id 43.', 'status' => KnowledgeEntry::STATUS_PROPOSED]);
        $clean = $this->entry(['title' => 'Plány pro Toma obecně', 'body' => 'Co, proč a kdo.', 'status' => KnowledgeEntry::STATUS_PROPOSED]);

        Livewire::test(ListKnowledgeEntries::class)
            ->assertSee('Míchá klienty')
            ->callTableAction('approve', $mixed)
            ->assertNotified('Neschváleno: poznatek míchá klienty')
            ->callTableBulkAction('approveSelected', [$mixed, $clean])
            ->assertNotified('Neschváleno: 1 míchá klienty');

        $this->assertSame(KnowledgeEntry::STATUS_PROPOSED, $mixed->refresh()->status);
        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $clean->refresh()->status);

        Livewire::test(ClientBrain::class, ['clientId' => null])->call('approve', $mixed->getKey());
        $this->assertSame(KnowledgeEntry::STATUS_PROPOSED, $mixed->refresh()->status);

        // Ručně psaný ani upravený firemní poznatek jméno klienta nepřijme, v poznatku klienta to jde.
        Livewire::test(CreateKnowledgeEntry::class)
            ->fillForm(['title' => 'Objednávky v zimě', 'body' => 'Snowbear má v prosinci 40 % objednávek.', 'layer' => 'firma'])
            ->call('create')
            ->assertHasFormErrors(['body']);
        Livewire::test(CreateKnowledgeEntry::class)
            ->fillForm(['title' => 'Objednávky v zimě', 'body' => 'Snowbear má v prosinci 40 % objednávek.', 'layer' => 'klient', 'client_id' => $this->snowbear->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(EditKnowledgeEntry::class, ['record' => $mixed->getKey()])
            ->assertSee('Agenti tenhle poznatek nedostanou a neschválí se.')
            ->fillForm(['body' => 'Co, proč a kdo, bez zaškrtávání.'])
            ->callAction('approve')
            ->assertHasNoFormErrors();
        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $mixed->refresh()->status);
    }
}
