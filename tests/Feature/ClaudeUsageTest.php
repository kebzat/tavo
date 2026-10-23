<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Livewire\ClaudeUsageMeter;
use App\Filament\Tools\Pages\Agent\AgentProjects;
use App\Models\User;
use App\Support\Agent\ClaudeUsage;
use App\Support\Agent\UsageLimit;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ClaudeUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.agent.url' => 'http://agent.test',
            'services.agent.secret' => 'tajemstvi-pro-testy-dlouhe-aspon-32-znaku',
        ]);

        CarbonImmutable::setTestNow('2026-10-23 01:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** Odpověď služby agenta tak, jak ji posílá Anthropic (zkrácená). */
    private function predplatne(int $relace = 19, int $tyden = 61): array
    {
        return [
            'plan' => 'max',
            'limity' => [
                ['kind' => 'session', 'percent' => $relace, 'resets_at' => '2026-10-23T03:40:00.371604+00:00'],
                ['kind' => 'weekly_all', 'percent' => $tyden, 'resets_at' => '2026-10-25T17:00:00.371625+00:00'],
                ['kind' => 'weekly_scoped', 'percent' => 0, 'resets_at' => '2026-10-25T17:00:00+00:00', 'scope' => ['model' => ['display_name' => 'Fable']]],
                ['kind' => 'neznamy_limit', 'percent' => 5],
            ],
            'five_hour' => ['utilization' => $relace, 'resets_at' => '2026-10-23T03:40:00+00:00'],
            'seven_day' => ['utilization' => $tyden, 'resets_at' => '2026-10-25T17:00:00+00:00'],
        ];
    }

    private function falesnyAgent(array $predplatne): void
    {
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/api/predplatne')
            ? Http::response($predplatne)
            : Http::response([]));
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_limity_z_anthropicu_se_prectou_vcetne_obnovy(): void
    {
        $usage = ClaudeUsage::fromApi($this->predplatne());

        $this->assertSame('Claude Max', $usage->planLabel());
        $this->assertSame(['session', 'weekly_all', 'weekly_scoped'], array_map(fn (UsageLimit $l) => $l->kind, $usage->limits));
        $this->assertSame(['5 h', '7 dní'], array_map(fn (UsageLimit $l) => $l->shortLabel(), $usage->headline()));

        [$relace, $tyden, $fable] = $usage->limits;
        $this->assertSame('za 2 h 40 min', $relace->resetLabel());
        $this->assertSame('v neděli 18:00', $tyden->resetLabel());
        $this->assertSame('Týden · Fable', $fable->label());

        // Z pětihodinového okna zbývají 2 h 40 min, uběhlo tedy 47 %.
        $this->assertSame(47, $relace->elapsed());
    }

    public function test_starsi_tvar_odpovedi_bez_seznamu_limitu(): void
    {
        $data = $this->predplatne();
        unset($data['limity']);

        $usage = ClaudeUsage::fromApi($data);

        $this->assertSame([19, 61], array_map(fn (UsageLimit $l) => $l->percent, $usage->limits));
    }

    public function test_barva_pruhu_podle_vycerpani(): void
    {
        $limit = fn (int $percent) => new UsageLimit('weekly_all', $percent, null);

        $this->assertSame('ok', $limit(74)->level());
        $this->assertSame('warning', $limit(75)->level());
        $this->assertSame('danger', $limit(90)->level());
        $this->assertSame(100, $limit(130)->bar());
    }

    public function test_hlavicka_nastroju_ukazuje_relaci_a_tyden(): void
    {
        $this->falesnyAgent($this->predplatne(relace: 19, tyden: 92));

        $this->actingAs($this->spravce())
            ->get(AgentProjects::getUrl(panel: 'tools'))
            ->assertOk()
            ->assertSeeInOrder(['5 h', '19 %', '7 dní', '92 %', 'Claude Max', 'Obnoví se za 2 h 40 min']);
    }

    public function test_data_se_drzi_v_cache_a_anthropic_se_nepta_kazda_zalozka(): void
    {
        $this->falesnyAgent($this->predplatne());

        Livewire::actingAs($this->spravce())->test(ClaudeUsageMeter::class)->assertSee('61 %');
        Livewire::actingAs($this->spravce())->test(ClaudeUsageMeter::class)->assertSee('61 %');

        Http::assertSentCount(1);
    }

    public function test_nedostupny_agent_hlavicku_neshodi(): void
    {
        Http::fake(['*' => Http::response(['chyba' => 'Přihlášení Clauda vypršelo, obnoví se v příští konverzaci.'], 503)]);

        Livewire::actingAs($this->spravce())
            ->test(ClaudeUsageMeter::class)
            ->assertOk()
            ->assertSee('Claude –')
            ->assertSee('Přihlášení Clauda vypršelo');
    }

    public function test_bez_sluzby_agenta_se_ukazatel_neukaze(): void
    {
        config(['services.agent.secret' => null]);
        Http::fake();

        $this->actingAs($this->spravce())
            ->get(AgentProjects::getUrl(panel: 'tools'))
            ->assertDontSee('claude-usage');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'predplatne'));
    }
}
