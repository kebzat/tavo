<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Resources\Audits\Pages\CreateAudit;
use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Filament\Tools\Resources\Audits\Pages\ListAudits;
use App\Models\Audit;
use App\Models\Checklist;
use App\Models\Client;
use App\Models\User;
use App\Support\AuditMarkdown;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private function klient(): Client
    {
        return Client::create(['name' => 'Čajovna Zkouška', 'slug' => 'cajovna-zkouska']);
    }

    private function audit(array $attributes = []): Audit
    {
        return Audit::create([
            'client_id' => $this->klient()->getKey(),
            'title' => 'SEO audit e-shopu',
            'audited_at' => '2026-09-24',
            'intro' => 'Prověrka viditelnosti.',
            'highlights' => [['value' => '1 974', 'label' => 'adres v sitemapě']],
            'body' => "## Shrnutí\n\nText.\n\n### [[kritické]] Filtry v indexu\n\n| Typ | Stav |\n|---|---|\n| Product | [[částečně]] |\n",
            'is_public' => true,
            ...$attributes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Převod Markdownu
    |--------------------------------------------------------------------------
    */

    public function test_stitek_dostane_ton_podle_slova(): void
    {
        $html = AuditMarkdown::render('[[kritické]] [[částečně]] [[v pořádku]] [[neznámé]]')['html'];

        $this->assertStringContainsString('<span class="audit-tag audit-tag--bad">kritické</span>', $html);
        $this->assertStringContainsString('<span class="audit-tag audit-tag--warn">částečně</span>', $html);
        $this->assertStringContainsString('<span class="audit-tag audit-tag--good">v pořádku</span>', $html);
        $this->assertStringContainsString('<span class="audit-tag audit-tag--neutral">neznámé</span>', $html);
    }

    public function test_stitek_se_v_kodu_nerozbali(): void
    {
        $html = AuditMarkdown::render("`[[kritické]]`\n\n```\n[[ne]]\n```")['html'];

        $this->assertStringNotContainsString('audit-tag', $html);
    }

    public function test_nadpisy_druhe_urovne_tvori_obsah_s_unikatnimi_kotvami(): void
    {
        $rendered = AuditMarkdown::render("## Shrnutí\n\n## Obrázky\n\n## Shrnutí\n\n### Nález");

        $this->assertSame([
            ['id' => 'shrnuti', 'title' => 'Shrnutí'],
            ['id' => 'obrazky', 'title' => 'Obrázky'],
            ['id' => 'shrnuti-2', 'title' => 'Shrnutí'],
        ], $rendered['toc']);
        $this->assertStringContainsString('<h2 id="shrnuti-2">', $rendered['html']);
    }

    public function test_tabulka_dostane_obal_a_syrove_html_se_escapuje(): void
    {
        $html = AuditMarkdown::render("| A |\n|---|\n| 1 |\n\n<script>alert(1)</script>")['html'];

        $this->assertStringContainsString('<div class="audit-table"><table>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /*
    |--------------------------------------------------------------------------
    | Sdílená stránka
    |--------------------------------------------------------------------------
    */

    public function test_sdileny_audit_se_zobrazi_a_nesmi_se_indexovat(): void
    {
        $audit = $this->audit();

        $this->get($audit->publicUrl())
            ->assertOk()
            ->assertSee('SEO audit e-shopu')
            ->assertSee('24. 9. 2026')
            ->assertSee('1 974')
            ->assertSee('audit-tag--bad', false)
            ->assertSee('href="#shrnuti"', false)
            ->assertSee('noindex, nofollow', false);
    }

    public function test_nezverejneny_audit_vraci_404(): void
    {
        $audit = $this->audit(['is_public' => false]);

        $this->get(route('audit.show', $audit->public_token))->assertNotFound();
        $this->get(route('audit.show', 'takovy-token-neexistuje'))->assertNotFound();
    }

    public function test_audit_a_checklist_klienta_na_sebe_odkazuji(): void
    {
        $audit = $this->audit();
        $checklist = Checklist::create([
            'client_id' => $audit->client_id,
            'name' => 'Checklist klienta',
            'is_public' => true,
        ]);

        $this->get($audit->publicUrl())
            ->assertSee($checklist->publicUrl(), false)
            ->assertSee('Checklist úkolů');

        $this->get($checklist->publicUrl())
            ->assertSee($audit->publicUrl(), false)
            ->assertSee('Přečíst audit z 24. 9. 2026');
    }

    public function test_nesdileny_audit_se_z_checklistu_neodkazuje(): void
    {
        $audit = $this->audit(['is_public' => false]);
        $checklist = Checklist::create([
            'client_id' => $audit->client_id,
            'name' => 'Checklist klienta',
            'is_public' => true,
        ]);

        $this->get($checklist->publicUrl())
            ->assertOk()
            ->assertDontSee($audit->public_token);
    }

    public function test_robots_zakazuje_audity(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /audit');
    }

    /*
    |--------------------------------------------------------------------------
    | Administrace
    |--------------------------------------------------------------------------
    */

    public function test_spravce_zalozi_audit_v_panelu_nastroju(): void
    {
        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $client = $this->klient();

        Livewire::test(CreateAudit::class)
            ->fillForm([
                'title' => 'Nový audit',
                'client_id' => $client->getKey(),
                'body' => "## Shrnutí\n\nText.",
                'is_public' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $audit = Audit::firstWhere('title', 'Nový audit');

        $this->assertNotNull($audit->publicUrl());
        $this->assertSame($client->getKey(), $audit->client_id);
    }

    public function test_vypis_a_uprava_auditu_se_nactou(): void
    {
        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $audit = $this->audit();

        Livewire::test(ListAudits::class)->assertCanSeeTableRecords([$audit]);
        Livewire::test(EditAudit::class, ['record' => $audit->getRouteKey()])->assertOk();
    }
}
