<?php

namespace Tests\Feature;

use App\Models\Proposal;
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
