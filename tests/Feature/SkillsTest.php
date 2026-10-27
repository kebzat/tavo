<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Livewire\ClientBrain;
use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Pages\Agent\AgentProjects;
use App\Filament\Tools\Resources\Skills\Pages\CreateSkill;
use App\Filament\Tools\Resources\Skills\Pages\EditSkill;
use App\Filament\Tools\Resources\Skills\Pages\ListSkills;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\Skill;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

/** Skilly TAVEO: specialisté s know-how v mozku, jejich agenti a Projekty po skupinách (27. 10. 2026). */
class SkillsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'agent-token-agent-token-agent-token-1234';

    private array $projects = [];

    /** Projekty, které služba zakládala. */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.url' => 'http://agent.test', 'services.agent.secret' => 'tajne', 'services.agent.api_token' => self::TOKEN]);
        Sleep::fake();
        Client::query()->delete();

        Http::fake([
            'agent.test/api/projekty' => function (Request $request) {
                if ($request->method() !== 'POST') {
                    return Http::response($this->projects);
                }

                $this->created[] = $request['projekt'];
                $this->projects[] = $this->project($request['projekt']);

                return Http::response(['ok' => true, 'vystup' => 'Projekt je připravený.']);
            },
            'agent.test/*' => Http::response([]),
        ]);
    }

    private function project(string $slug): array
    {
        return ['slug' => $slug, 'typ' => 'prazdny', 'zdroj' => '', 'vlakna' => []];
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']);
    }

    private function skill(string $slug): Skill
    {
        return Skill::query()->where('slug', $slug)->firstOrFail();
    }

    private function entry(array $data): KnowledgeEntry
    {
        return KnowledgeEntry::create(['layer' => KnowledgeEntry::LAYER_COMPANY, 'status' => KnowledgeEntry::STATUS_APPROVED, 'body' => 'Text.', ...$data]);
    }

    public function test_skilly_podle_pavlova_seznamu_ve_skupinach_a_agenti_se_zalozi_po_jednom(): void
    {
        $skills = Skill::query()->orderBy('sort')->get();

        $this->assertSame(
            ['onboarding', 'marketing', 'copywriting', 'socialmediacontent', 'emailing', 'meta-ads', 'google-reklamy', 'sklik-ads', 'web', 'data-analytik', 'obchod'],
            $skills->pluck('slug')->all(),
        );
        $this->assertSame(array_keys(Skill::categories()), $skills->pluck('category')->unique()->values()->all());
        $this->assertSame('google-reklamy', $this->skill('google-reklamy')->agent_project);
        $this->assertSame(['copywriting', 'data-analytik'], $this->skill('meta-ads')->builds_on);

        $this->projects = [$this->project('google-reklamy'), $this->project('marketing')];
        $this->artisan('agent:projekty-klientu')->assertSuccessful();

        // „marketing“ je Pavlův reportovací agent, skill dostane vlastní projekt.
        $this->assertCount(10, $this->created);
        $this->assertContains('marketing-strategie', $this->created);
        $this->assertNotContains('marketing', $this->created);
        $this->assertSame('marketing-strategie', $this->skill('marketing')->agent_project);
        $this->assertNull($this->skill('meta-ads')->agent_project_request);
        Sleep::assertSleptTimes(9);
    }

    public function test_projekty_ukazou_skilly_po_skupinach_zvlast_od_klientu(): void
    {
        $this->projects = [$this->project('google-reklamy'), $this->project('meta-ads'), $this->project('svet-cejlonu'), $this->project('taveo')];
        $this->skill('meta-ads')->forceFill(['agent_project' => 'meta-ads', 'agent_project_request' => null])->save();
        Client::query()->create(['name' => 'Svět Cejlonu', 'slug' => 'svet-cejlonu', 'agent_project' => 'svet-cejlonu']);
        $this->entry(['title' => 'Carousel na čaje', 'skill_id' => $this->skill('meta-ads')->getKey()]);
        $this->entry(['title' => 'Návrh', 'status' => KnowledgeEntry::STATUS_PROPOSED, 'skill_id' => $this->skill('meta-ads')->getKey()]);

        Livewire::actingAs($this->spravce())->test(AgentProjects::class)
            ->assertSeeInOrder(['Skilly', 'Strategie a základ', 'Onboarding', 'Marketing a strategie', 'Obsah', 'Copywriting', 'Reklama', 'Meta Ads', 'Google Ads', 'Sklik', 'Web a konverze', 'Data', 'Agenturní provoz', 'Obchod a provoz', 'Klienti', 'Svět Cejlonu', 'Interní projekty', 'taveo'])
            ->assertSee('/meta-ads')
            ->assertSee('1 poznatek')
            ->assertSee('1 ke schválení')
            ->assertSee('Agent se zakládá…')
            ->assertSee('Zakládají se agenti (9)')
            ->assertSeeHtml('href="'.AgentProject::getUrl(['project' => 'meta-ads']).'"')
            // Projekty skillů nejsou mezi interními.
            ->assertDontSeeHtml('wire:key="grid-interni-meta-ads"')
            ->assertDontSeeHtml('wire:key="grid-interni-google-reklamy"')
            ->set('search', 'reklama')
            ->assertSeeHtml('wire:key="skill-meta-ads"')
            ->assertDontSeeHtml('wire:key="skill-copywriting"')
            ->assertDontSee('Svět Cejlonu')
            ->call('openFirst')
            ->assertRedirect(AgentProject::getUrl(['project' => 'meta-ads']));
    }

    public function test_skill_pro_agenta_dava_pokyny_know_how_a_zkusenosti_jinych_klientu(): void
    {
        $meta = $this->skill('meta-ads');
        $cejlon = Client::query()->create(['name' => 'Svět Cejlonu', 'slug' => 'svet-cejlonu']);
        $mirnix = Client::query()->create(['name' => 'MIRNIX Hockey', 'slug' => 'mirnix-hockey']);
        $this->entry(['title' => 'Testujeme 3 kreativy na sadu', 'skill_id' => $meta->getKey()]);
        $this->entry(['title' => 'U MIRNIXu fungovala videa z tréninku', 'layer' => 'klient', 'client_id' => $mirnix->getKey(), 'skill_id' => $meta->getKey()]);
        $this->entry(['title' => 'Svět Cejlonu chce jen dárek', 'layer' => 'klient', 'client_id' => $cejlon->getKey(), 'skill_id' => $meta->getKey()]);
        $this->entry(['title' => 'Copy bez pomlček', 'skill_id' => $this->skill('copywriting')->getKey()]);
        $this->entry(['title' => 'Neschválený', 'status' => KnowledgeEntry::STATUS_PROPOSED, 'skill_id' => $meta->getKey()]);
        KnowledgeSource::create(['title' => 'Výsledky 30 klientů v Meta Ads', 'skill_id' => $meta->getKey(), 'status' => KnowledgeSource::STATUS_STORED, 'summary' => 'Tabulka CPA a ROAS.']);

        $this->get(route('agent.api.skill', 'meta-ads'))->assertUnauthorized();

        $text = $this->withToken(self::TOKEN)->get(route('agent.api.skill', ['skill' => 'meta-ads', 'klient' => 'svet-cejlonu']))->assertOk()->content();

        $this->assertStringContainsString('# Skill Meta Ads (TAVEO, Reklama)', $text);
        $this->assertStringContainsString('## Jak pracuješ', $text);
        $this->assertStringContainsString('Problém měření', $text);
        $this->assertStringContainsString('`taveo skill copywriting svet-cejlonu`', $text);
        $this->assertStringContainsString('Testujeme 3 kreativy na sadu', $text);
        $this->assertStringContainsString("## U klienta Svět Cejlonu (ke skillu)\n- #", $text);
        $this->assertStringContainsString('[MIRNIX Hockey] U MIRNIXu fungovala videa z tréninku', $text);
        $this->assertStringContainsString('Výsledky 30 klientů v Meta Ads', $text);
        $this->assertStringNotContainsString('Copy bez pomlček', $text);
        $this->assertStringNotContainsString('Neschválený', $text);
        $this->assertStringNotContainsString('[Svět Cejlonu]', $text);

        // Hledání se skillem najde i zkušenosti jiných klientů, bez skillu jen to, co platí pro klienta.
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek', ['q' => 'videa', 'klient' => 'svet-cejlonu', 'skill' => 'meta-ads']))
            ->assertJsonPath('poznatky.0.klient', 'mirnix-hockey')
            ->assertJsonPath('poznatky.0.skill', 'meta-ads');
        $this->withToken(self::TOKEN)->getJson(route('agent.api.mozek', ['q' => 'videa', 'klient' => 'svet-cejlonu']))->assertJsonCount(0, 'poznatky');

        $this->withToken(self::TOKEN)->getJson(route('agent.api.skilly'))
            ->assertJsonCount(11)
            ->assertJsonPath('5.slug', 'meta-ads')
            ->assertJsonPath('5.skupina', 'Reklama')
            ->assertJsonPath('6.projekt', 'google-reklamy');
        $file = $this->withToken(self::TOKEN)->getJson(route('agent.api.skilly'))->json('5.soubor');
        $this->assertStringStartsWith("---\nname: meta-ads\ndescription: Reklama na Meta", $file);
        $this->assertStringContainsString(Skill::GENERATED_MARK, $file);
        $this->assertStringContainsString('taveo skill meta-ads <slug-klienta>', $file);
    }

    public function test_navrhy_a_archiv_skillu_od_agenta(): void
    {
        Storage::fake('local');
        Client::query()->create(['name' => 'Svět Cejlonu', 'slug' => 'svet-cejlonu']);

        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), ['poznatky' => [
            ['vrstva' => 'klient', 'klient' => 'svet-cejlonu', 'skill' => 'meta-ads', 'nadpis' => 'Carousel s čaji měl CPA 120 Kč', 'text' => 'Říjen 2026.'],
        ]])->assertOk()->assertJsonPath('zalozeno', 1);

        $entry = KnowledgeEntry::query()->where('title', 'Carousel s čaji měl CPA 120 Kč')->firstOrFail();
        $this->assertSame('meta-ads', $entry->skill->slug);
        $this->assertSame('svet-cejlonu', $entry->client->slug);
        $this->assertTrue($entry->isProposed());

        $this->withToken(self::TOKEN)->post(route('agent.api.skill.archiv.nahrat', 'web'), [
            'nazev' => 'Vzorové e-shopy',
            'druh' => 'jine',
            'popis' => 'Pět e-shopů, které se nám líbí.',
            'soubory' => [UploadedFile::fake()->createWithContent('vzory.md', '# Vzory')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $source = KnowledgeSource::query()->where('title', 'Vzorové e-shopy')->firstOrFail();
        $this->assertSame('web', $source->skill->slug);
        $this->assertNull($source->client_id);

        // Dokument klienta označený skillem zůstane v archivu klienta a skill ho najde taky.
        $this->withToken(self::TOKEN)->post(route('agent.api.archiv.nahrat', 'svet-cejlonu'), [
            'nazev' => 'Meta Ads říjen', 'skill' => 'meta-ads', 'text' => 'Čísla.',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->withToken(self::TOKEN)->getJson(route('agent.api.skill.archiv', 'meta-ads'))
            ->assertJsonCount(1, 'dokumenty')
            ->assertJsonPath('dokumenty.0.klient', 'svet-cejlonu');
        $this->withToken(self::TOKEN)->getJson(route('agent.api.archiv', 'svet-cejlonu'))->assertJsonPath('dokumenty.0.skill', 'meta-ads');

        // Knihovník u podkladů skillu: návrhy zdědí skill.
        $this->withToken(self::TOKEN)->postJson(route('agent.api.mozek.navrhy'), ['zdroj' => $source->getKey(), 'poznatky' => [
            ['vrstva' => 'firma', 'nadpis' => 'Košík na jedné stránce', 'text' => 'Ze vzorů.'],
        ]])->assertOk();
        $this->assertSame('web', KnowledgeEntry::query()->where('title', 'Košík na jedné stránce')->firstOrFail()->skill->slug);
    }

    public function test_konverzace_v_projektu_skillu_zacne_skillem_a_u_klienta_zna_skilly(): void
    {
        $meta = $this->skill('meta-ads');
        $meta->forceFill(['agent_project' => 'meta-ads', 'agent_project_request' => null])->save();
        $this->entry(['title' => 'Testujeme 3 kreativy na sadu', 'skill_id' => $meta->getKey()]);
        Client::query()->create(['name' => 'Svět Cejlonu', 'slug' => 'svet-cejlonu', 'agent_project' => 'svet-cejlonu']);

        $text = $this->withToken(self::TOKEN)->get(route('agent.api.mozek.uvod', ['projekt' => 'meta-ads']))->assertOk()->content();

        $this->assertStringContainsString('Projekt `meta-ads` je **skill Meta Ads**', $text);
        $this->assertStringContainsString('Testujeme 3 kreativy na sadu', $text);
        $this->assertStringContainsString('taveo skill-nahrat meta-ads', $text);
        $this->assertLessThan(10000, mb_strlen($text));

        $client = $this->withToken(self::TOKEN)->get(route('agent.api.mozek.uvod', ['projekt' => 'svet-cejlonu']))->assertOk()->content();
        $this->assertStringContainsString('/meta-ads', $client);
        $this->assertStringContainsString('`taveo skill <skill> svet-cejlonu`', $client);
    }

    public function test_uvod_skillu_se_vejde_i_s_plnym_mozkem(): void
    {
        $meta = $this->skill('meta-ads');
        $meta->forceFill(['agent_project' => 'meta-ads'])->save();
        $client = Client::query()->create(['name' => 'Svět Cejlonu', 'slug' => 'svet-cejlonu']);

        foreach (range(1, 40) as $i) {
            $this->entry(['title' => "Know-how {$i}", 'body' => str_repeat('Dlouhý text. ', 30), 'skill_id' => $meta->getKey(), 'always' => $i <= 10]);
            $this->entry(['title' => "Zkušenost {$i}", 'layer' => 'klient', 'client_id' => $client->getKey(), 'body' => str_repeat('Dlouhý text. ', 30), 'skill_id' => $meta->getKey()]);
            KnowledgeSource::create(['title' => "Case study {$i}", 'skill_id' => $meta->getKey(), 'status' => KnowledgeSource::STATUS_STORED, 'summary' => str_repeat('Popis. ', 60)]);
        }

        $text = $this->withToken(self::TOKEN)->get(route('agent.api.mozek.uvod', ['projekt' => 'meta-ads']))->assertOk()->content();

        $this->assertLessThan(10000, mb_strlen($text));
        $this->assertStringContainsString('## Jak se skill učí', $text);
    }

    public function test_sprava_skillu_a_mozek_skillu_u_konverzace(): void
    {
        Storage::fake('local');
        $user = $this->spravce();
        $meta = $this->skill('meta-ads');
        $mirnix = Client::query()->create(['name' => 'MIRNIX Hockey', 'slug' => 'mirnix-hockey']);
        $this->entry(['title' => 'Testujeme 3 kreativy na sadu', 'skill_id' => $meta->getKey()]);
        $this->entry(['title' => 'U MIRNIXu fungovala videa', 'layer' => 'klient', 'client_id' => $mirnix->getKey(), 'skill_id' => $meta->getKey()]);
        $proposed = $this->entry(['title' => 'Nový návrh skillu', 'status' => KnowledgeEntry::STATUS_PROPOSED, 'skill_id' => $meta->getKey()]);

        Livewire::actingAs($user)->test(ListSkills::class)
            ->assertSeeInOrder(['Strategie a základ', 'Onboarding', 'Reklama', 'Meta Ads', '/meta-ads', 'Agenturní provoz', 'Obchod a provoz']);

        Livewire::actingAs($user)->test(EditSkill::class, ['record' => $meta->getKey()])
            ->assertSee('Reklama · /meta-ads · agent: zakládá se')
            ->fillForm(['instructions' => '## Co děláš'."\n".'Nové pokyny.'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertStringContainsString('Nové pokyny.', $meta->refresh()->instructions);

        Livewire::actingAs($user)->test(CreateSkill::class)
            ->fillForm(['name' => 'TikTok Ads', 'slug' => 'tiktok-ads', 'category' => 'reklama', 'description' => 'Reklama na TikToku: kampaně a kreativy. Použij u TikToku.', 'sort' => 65])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame('queued', $this->skill('tiktok-ads')->agent_project_request['status']);
        $this->assertSame('tiktok-ads', $this->skill('tiktok-ads')->agent_project_request['project']);

        Livewire::actingAs($user)->test(ClientBrain::class, ['skillId' => $meta->getKey()])
            ->assertSee('Mozek skillu Meta Ads')
            ->assertSeeInOrder(['Ke schválení (1)', 'Nový návrh skillu', 'Know-how skillu (1)', 'Testujeme 3 kreativy na sadu', 'Zkušenosti z klientů (1)', 'U MIRNIXu fungovala videa', 'MIRNIX Hockey', 'Archiv skillu (0)'])
            ->call('approve', $proposed->getKey())
            ->callAction('upload', ['title' => 'Výsledky 30 klientů', 'body' => 'Tabulka.', 'learn' => false, 'client_id' => $mirnix->getKey()])
            ->assertHasNoActionErrors();

        $this->assertSame(KnowledgeEntry::STATUS_APPROVED, $proposed->refresh()->status);
        $source = KnowledgeSource::query()->where('title', 'Výsledky 30 klientů')->firstOrFail();
        $this->assertSame([$meta->getKey(), $mirnix->getKey()], [$source->skill_id, $source->client_id]);
    }
}
