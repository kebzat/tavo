<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\User;
use App\Support\ProposalAdditions;
use App\Support\ProposalDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProposalDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_koncepty_maji_platny_obsah(): void
    {
        foreach (ProposalDraft::files() as $path) {
            $name = basename($path);
            $data = ProposalDraft::read($path);

            $this->assertSame(basename($path, '.json'), $data['slug'], $name);
            $this->assertNotEmpty($data['company_name'], $name);
            $this->assertNotEmpty($data['findings'], $name);
            $this->assertStringNotContainsString('—', File::get($path), "$name obsahuje dlouhou pomlčku");

            foreach ($data['findings'] as $finding) {
                $this->assertArrayHasKey($finding['priority'] ?? '', Proposal::FINDING_PRIORITIES, "$name: {$finding['title']}");
                $this->assertArrayHasKey($finding['tone'] ?? '', Proposal::FINDING_TONES, "$name: {$finding['title']}");
            }

            array_walk_recursive($data, function ($value, $key) use ($name): void {
                if ($key === 'image' && is_string($value)) {
                    $this->assertFileExists(database_path('seeders/assets/'.$value), $name);
                }
            });
        }
    }

    public function test_koncepty_se_zalozi_nesdilene(): void
    {
        foreach (ProposalDraft::files() as $path) {
            $proposal = Proposal::firstWhere('slug', ProposalDraft::read($path)['slug']);

            $this->assertNotNull($proposal, basename($path));
            $this->assertFalse($proposal->is_public);
            $this->assertNotEmpty($proposal->findingGroups());
            $this->assertNotEmpty($proposal->experienceItems());
        }
    }

    public function test_grillnor_ma_i_mesicni_spolupraci(): void
    {
        $proposal = Proposal::firstWhere('slug', 'grillnor');

        $this->assertSame(['Údržba', 'Rozvoj'], array_column($proposal->packageItems(), 'title'));
        $this->assertTrue($proposal->packageItems()[1]['recommended']);
        $this->assertNotEmpty($proposal->packages_intro);
        $this->assertSame('Plán po měsících', $proposal->steps_title);

        $example = $proposal->examplesByPlacement()['after_recommendations'][0];
        $this->assertSame(['Úvodní stránka na mobilu', 'Registrace'], array_column($example['phones'], 'title'));
        $this->assertSame(['Můj účet', 'Bannery'], array_column($example['items'], 'title'));
    }

    public function test_tt_reality_ma_mobil_navrh_detailu_a_videa(): void
    {
        $proposal = Proposal::firstWhere('slug', 'tt-reality');
        $examples = $proposal->examplesByPlacement();

        $mobile = collect($examples['after_findings'])->firstWhere('title', 'Takhle web vidí většina návštěvníků');
        $this->assertSame(['Úvodní stránka', 'Detail bytu 4+kk'], array_column($mobile['phones'], 'title'));
        $this->assertSame(['Starý web na www.ttreal.cz'], array_column($mobile['items'], 'title'));

        $detail = $examples['after_recommendations'][0];
        $this->assertSame(['Na telefonu'], array_column($detail['phones'], 'title'));
        $this->assertNotNull($detail['items'][0]['image']);

        $videos = $examples['after_steps'][0];
        $this->assertSame('video', $videos['items_layout']);
        $this->assertCount(4, $videos['items']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get('/potencialni-spoluprace/tt-reality')
            ->assertOk()
            ->assertSee('Na staré adrese ttreal.cz běží starý web se skrytým spamem')
            ->assertSee('Videa, která Pavel připravoval pro jinou realitku');
    }

    public function test_doplneni_vlozi_za_nadpis_a_nezdvoji(): void
    {
        $current = [['title' => 'A'], ['title' => 'B']];
        $items = [['title' => 'X', '_after' => 'A'], ['title' => 'B'], ['title' => 'Y', '_after' => 'neexistuje']];

        $merged = ProposalAdditions::merge($current, $items);

        $this->assertSame(['A', 'X', 'B', 'Y'], array_column($merged, 'title'));
        $this->assertArrayNotHasKey('_after', $merged[1]);
        $this->assertSame($merged, ProposalAdditions::merge($merged, $items));
    }

    public function test_import_neprepise_existujici_stranku(): void
    {
        $path = storage_path('framework/testing/draft.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'company_name' => 'Zkušební', 'slug' => 'zkusebni', 'title' => 'Nadpis',
            'findings' => [['priority' => 'urgent', 'tone' => 'problem', 'title' => 'Nález']],
            'examples' => [['placement' => 'after_principles', 'title' => 'Tipy', 'link_url' => ProposalDraft::TIPS_PAGE]],
        ]));

        $proposal = ProposalDraft::import($path);
        $this->assertFalse($proposal->is_public);
        $this->assertSame(route('pages.show', 'pro-klienty'), $proposal->examples[0]['link_url']);

        $proposal->update(['title' => 'Upraveno v nástrojích']);
        $this->assertNull(ProposalDraft::import($path));
        $this->assertSame('Upraveno v nástrojích', $proposal->fresh()->title);

        File::delete($path);
    }
}
