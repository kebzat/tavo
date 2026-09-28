<?php

namespace Tests\Feature;

use App\Enums\ChecklistPriority;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\FitVerdict;
use App\Enums\UserRole;
use App\Filament\Tools\Pages\Today;
use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Filament\Tools\Resources\Companies\Pages\EditCompany;
use App\Jobs\CompletePageSpeed;
use App\Jobs\WriteDeepAudit;
use App\Models\Audit;
use App\Models\Crm\Company;
use App\Models\User;
use App\Support\Crm\Ai\ClaudeProspectAi;
use App\Support\Crm\Ai\DeepAuditPrompt;
use App\Support\Crm\Ai\NullProspectAi;
use App\Support\Crm\Ai\ProspectAi;
use App\Support\Crm\AuditFromCompany;
use App\Support\Crm\Scout\ProspectScout;
use App\Support\Crm\Scout\WebScout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CrmScoutTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP_HTML = <<<'HTML'
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Čajovna Zkouška | e-shop s čajem</title>
            <link rel="canonical" href="https://shop.test/">
            <script src="https://cdn.myshoptet.com/prj/abc/main.js"></script>
            <script>!function(f,b,e,v,n,t,s){}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init', '123');</script>
            <script async src="https://www.googletagmanager.com/gtag/js?id=G-ABCDEF123"></script>
        </head>
        <body>
            <h1>Čaje z Cejlonu</h1>
            <a href="/kosik">Košík</a>
            <a href="mailto:info@shop.test">info@shop.test</a>
            <a href="tel:+420 777 123 456">777 123 456</a>
            <footer>© 2019 Čajovna Zkouška</footer>
        </body>
        </html>
        HTML;

    private function fakeShop(array $overrides = []): void
    {
        $sitemap = '<?xml version="1.0"?><urlset>'.str_repeat('<url><loc>https://shop.test/p</loc></url>', 120).'</urlset>';

        Http::fake($overrides + [
            'www.googleapis.com/*' => Http::response(['lighthouseResult' => [
                'categories' => ['performance' => ['score' => 0.38]],
                'audits' => [
                    'largest-contentful-paint' => ['numericValue' => 5230],
                    'cumulative-layout-shift' => ['numericValue' => 0.12],
                    'total-blocking-time' => ['numericValue' => 640],
                ],
            ]]),
            'http://shop.test*' => Http::response('', 301, ['Location' => 'https://shop.test/']),
            'shop.test/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n\nUser-agent: GPTBot\nDisallow: /\n\nSitemap: https://shop.test/sitemap.xml"),
            'shop.test/sitemap.xml' => Http::response($sitemap),
            'shop.test*' => Http::response(self::SHOP_HTML),
        ]);
    }

    private function firma(array $attributes = []): Company
    {
        return Company::create($attributes + [
            'name' => 'Čajovna Zkouška',
            'website' => 'https://shop.test',
            'segment' => CompanySegment::Eshop,
            'source' => CompanySource::Research,
        ]);
    }

    private function obchodnik(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /*
    |--------------------------------------------------------------------------
    | Měření, nálezy, skóre
    |--------------------------------------------------------------------------
    */

    public function test_proklepnuti_zmeri_web_a_spocita_skore(): void
    {
        $this->fakeShop();

        $company = app(ProspectScout::class)->scout($this->firma());
        $m = $company->scout_data['measurements'];

        $this->assertTrue($m['reachable'], json_encode($m));
        $this->assertSame('Shoptet', $m['platform']);
        $this->assertTrue($m['is_eshop']);
        $this->assertTrue($m['tracking']['meta_pixel']);
        $this->assertTrue($m['tracking']['ga4']);
        $this->assertSame(['GPTBot'], $m['robots']['blocked_ai']);
        $this->assertSame(120, $m['sitemap_urls']);
        $this->assertSame(38, $m['pagespeed']['score']);
        $this->assertSame(2019, $m['copyright_year']);

        $keys = array_column($company->scout_data['findings'], 'key');
        $this->assertContains('pagespeed_low', $keys);
        $this->assertContains('ai_blocked', $keys);
        $this->assertContains('no_description', $keys);
        $this->assertNotContains('no_analytics', $keys);
        $this->assertNotContains('no_viewport', $keys);

        // e-shop 25, Shoptet 15, pixel 10, GA 5, katalog 10, dva závažné nálezy 10,
        // patička z roku 2019 −5
        $this->assertSame(70, $company->fit_score);
        $this->assertSame(FitVerdict::Strong, $company->fit_verdict);
        $this->assertSame('Shoptet', $company->platform);
    }

    public function test_proklepnuti_doplni_kontakt_a_bolest_jen_kdyz_chybi(): void
    {
        $this->fakeShop();

        $company = app(ProspectScout::class)->scout($this->firma());
        $contact = $company->contacts()->first();

        $this->assertSame('info@shop.test', $contact->email);
        $this->assertSame('+420777123456', $contact->phone);
        $this->assertTrue($contact->is_primary);
        $this->assertSame('Na mobilu se web načítá pomalu', $company->pain);

        $vlastni = $this->firma(['name' => 'Druhá', 'website' => 'https://shop.test/b', 'pain' => 'Vlastní postřeh']);
        $this->assertSame('Vlastní postřeh', app(ProspectScout::class)->scout($vlastni)->pain);
    }

    public function test_odmitnuty_pagespeed_ulozi_duvod(): void
    {
        $this->fakeShop(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'PageSpeed Insights API has not been used in project']], 403)]);

        $m = app(ProspectScout::class)->scout($this->firma())->scout_data['measurements'];

        $this->assertNull($m['pagespeed']);
        $this->assertSame('403: PageSpeed Insights API has not been used in project', $m['pagespeed_error']);
    }

    public function test_nedostupny_web_dostane_nulu(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $company = app(ProspectScout::class)->scout($this->firma());

        $this->assertSame(0, $company->fit_score);
        $this->assertSame(FitVerdict::Unreachable, $company->fit_verdict);
        $this->assertSame([], $company->scout_data['findings']);
    }

    public function test_agentura_ani_sluzby_se_nehodi_i_s_vysokym_skore(): void
    {
        $this->fakeShop();
        $scout = app(ProspectScout::class);

        $agentura = $scout->scout($this->firma(['segment' => CompanySegment::Agency]));
        $autoskola = $scout->scout($this->firma(['name' => 'Autoškola', 'website' => 'https://shop.test/a', 'segment' => CompanySegment::Local]));

        $this->assertSame(70, $agentura->fit_score);
        $this->assertSame(FitVerdict::Poor, $agentura->fit_verdict);
        $this->assertSame(FitVerdict::Poor, $autoskola->fit_verdict);
        $reasons = $autoskola->scout_data['reasons'];
        $this->assertStringContainsString('hledáme jen e-shopy', end($reasons));
        $this->assertTrue($scout->parkIfRejected($agentura));
    }

    public function test_web_bez_eshopu_se_nehodi(): void
    {
        $this->fakeShop(['shop.test*' => Http::response('<html><head><title>Firma</title></head><body><h1>Služby</h1></body></html>')]);

        $company = app(ProspectScout::class)->scout($this->firma());

        $this->assertSame(FitVerdict::Poor, $company->fit_verdict);
    }

    public function test_usudek_clauda_posune_skore_nejvys_o_dvacet(): void
    {
        $this->fakeShop();
        $this->app->instance(ProspectAi::class, new class extends FakeAi
        {
            public function judge(Company $company, array $scout): ?array
            {
                return ['summary' => 'Prodávají čaj.', 'adjustment' => -80, 'note' => 'Malá živnost.', 'hook' => 'Web je na mobilu pomalý.'];
            }
        });

        $company = app(ProspectScout::class)->scout($this->firma());

        $this->assertSame(50, $company->fit_score);
        $this->assertSame(FitVerdict::Maybe, $company->fit_verdict);
        $this->assertSame('Web je na mobilu pomalý.', $company->pain);
        $this->assertSame(-20, $company->scout_data['ai']['adjustment']);
    }

    /*
    |--------------------------------------------------------------------------
    | Odkládání nevhodných
    |--------------------------------------------------------------------------
    */

    public function test_odlozi_jen_nevhodnou_firmu_z_resere(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host'));
        $scout = app(ProspectScout::class);

        $reserse = $scout->scout($this->firma());
        $poptavka = $scout->scout($this->firma(['name' => 'Z poptávky', 'website' => 'b.test', 'source' => CompanySource::ShoptetDemands]));
        $rozjednana = $scout->scout($this->firma(['name' => 'Oslovená', 'website' => 'c.test', 'status' => CompanyStatus::Contacted]));

        $this->assertTrue($scout->parkIfRejected($reserse));
        $this->assertFalse($scout->parkIfRejected($poptavka));
        $this->assertFalse($scout->parkIfRejected($rozjednana));

        $this->assertSame(CompanyStatus::Parked, $reserse->fresh()->status);
        $this->assertStringStartsWith('Odloženo automaticky', $reserse->activities()->first()->subject);
        $this->assertSame(CompanyStatus::New, $poptavka->fresh()->status);
    }

    public function test_prikaz_proklepne_nove_firmy_a_odlozi_nevhodne(): void
    {
        // Jedno pravidlo pro všechno: pravidla v poli Laravel vyhodnocuje
        // všechna najednou a vyhozená výjimka by shodila i dobrý web.
        Http::fake(fn ($request) => str_contains($request->url(), 'good.test')
            ? Http::response(self::SHOP_HTML)
            : throw new ConnectionException('Could not resolve host'));

        $good = $this->firma(['name' => 'Dobrá', 'website' => 'good.test']);
        $dead = $this->firma(['name' => 'Mrtvá', 'website' => 'dead.test']);

        $this->artisan('crm:scout', ['--new' => true, '--park' => true])->assertSuccessful();

        $this->assertNotNull($good->fresh()->scouted_at);
        $this->assertSame(CompanyStatus::New, $good->fresh()->status);
        $this->assertSame(CompanyStatus::Parked, $dead->fresh()->status);
    }

    public function test_fronta_k_osloveni_zacina_nejvyssim_skore(): void
    {
        $this->obchodnik();
        $this->firma(['name' => 'Slabá', 'website' => 'a.test', 'fit_score' => 30]);
        $this->firma(['name' => 'Silná', 'website' => 'b.test', 'fit_score' => 80]);
        $this->firma(['name' => 'Neproklepnutá', 'website' => 'c.test']);

        $names = (new Today)->untouched()->pluck('name')->all();

        $this->assertSame(['Silná', 'Slabá', 'Neproklepnutá'], $names);
    }

    /*
    |--------------------------------------------------------------------------
    | Audit z firmy
    |--------------------------------------------------------------------------
    */

    private function auditZFirmy(): Audit
    {
        $this->fakeShop();
        $company = app(ProspectScout::class)->scout($this->firma());

        return app(AuditFromCompany::class)->create($company);
    }

    public function test_audit_vznikne_skryty_v_omezenem_rezimu_i_s_checklistem(): void
    {
        $audit = $this->auditZFirmy();
        $company = Company::first();

        $this->assertFalse($audit->is_public);
        $this->assertTrue($audit->is_teaser);
        $this->assertTrue($audit->hasLockMarker());
        $this->assertSame($company->getKey(), $audit->client->crm_company_id);
        $this->assertSame('Audit webu shop.test', $audit->title);
        $this->assertSame('38 / 100', $audit->highlights[1]['value']);
        $this->assertStringNotContainsString('—', $audit->body);

        $checklist = $audit->client->checklists()->first();
        $this->assertFalse($checklist->is_public);
        $this->assertSame(count($company->scout_data['findings']), $checklist->items()->count());
        $this->assertSame(ChecklistPriority::Must, $checklist->items()->where('title', 'Zrychlit načítání na mobilu')->first()->priority);
    }

    public function test_druhy_audit_pouzije_stejneho_klienta(): void
    {
        $prvni = $this->auditZFirmy();
        $druhy = app(AuditFromCompany::class)->create(Company::first());

        $this->assertSame($prvni->client_id, $druhy->client_id);
    }

    public function test_omezeny_rezim_ukaze_jen_zacatek_a_nadpisy_zbytku(): void
    {
        $audit = $this->auditZFirmy();
        $audit->update(['is_public' => true]);

        $this->get(route('audit.show', $audit->public_token))
            ->assertOk()
            ->assertSee('Nejdůležitější nález')
            ->assertSee('Na mobilu se web načítá pomalu')
            ->assertSee('Ve zbytku auditu')
            ->assertSee('Co udělat nejdřív')
            ->assertSee('Projdeme zbytek spolu?')
            // Detail zamčené kapitoly ani checklist v omezeném režimu ne.
            ->assertDontSee('Zkusili jsme web otevřít jako roboti')
            ->assertDontSee('Checklist úkolů')
            ->assertDontSee('::: zámek');
    }

    public function test_neverejny_audit_otevre_jen_prihlaseny_spravce(): void
    {
        $audit = $this->auditZFirmy();
        $this->assertFalse($audit->is_public);

        $this->get($audit->previewUrl())->assertNotFound();

        $this->actingAs($this->obchodnik())
            ->get($audit->previewUrl())
            ->assertOk()
            ->assertSee('Nejdůležitější nález')
            ->assertDontSee('Zkusili jsme web otevřít jako roboti');

        $this->assertSame(0, $audit->fresh()->view_count);
    }

    public function test_plna_verze_ukaze_vse_a_zpristupni_checklist(): void
    {
        $audit = $this->auditZFirmy();
        $audit->update(['is_public' => true, 'is_teaser' => false]);

        $this->assertTrue($audit->client->checklists()->first()->is_public);

        $this->get(route('audit.show', $audit->public_token))
            ->assertOk()
            ->assertSee('Zkusili jsme web otevřít jako roboti')
            ->assertSee('Checklist úkolů')
            ->assertDontSee('Ve zbytku auditu')
            ->assertDontSee('::: zámek');
    }

    /*
    |--------------------------------------------------------------------------
    | Podrobný audit od Clauda
    |--------------------------------------------------------------------------
    */

    public function test_odpoved_clauda_se_rozdeli_na_audit_cisla_a_ukoly(): void
    {
        $text = "Prošel jsem web.\n".DeepAuditPrompt::OUTPUT_START."\n## Shrnutí\n\nText — s pomlčkou.\n\n## Nejdůležitější nález\n\nA.\n\n## Co udělat nejdřív\n\nB.\n\n## Obsah\n\nC.\n\n"
            .'```json'."\n".'{"highlights":[{"value":"12","label":"kategorií bez textu"}],"tasks":[{"area":"Obsah","task":"Napsat texty kategorií","fix":"Úvod a otázky.","priority":"must"},{"area":"Obsah","task":"X","priority":"divná"}]}'."\n```";

        $parsed = DeepAuditPrompt::parse($text);

        $this->assertStringStartsWith('## Shrnutí', $parsed['body']);
        $this->assertStringNotContainsString('—', $parsed['body']);
        $this->assertStringNotContainsString('```', $parsed['body']);
        // Zapomenutý zámek se doplní před třetí kapitolu.
        $this->assertMatchesRegularExpression('/## Nejdůležitější nález\n\nA\.\n\n::: zámek\n\n## Co udělat nejdřív/', $parsed['body']);
        $this->assertSame([['value' => '12', 'label' => 'kategorií bez textu']], $parsed['highlights']);
        $this->assertSame('must', $parsed['tasks'][0]['priority']);
        $this->assertSame('should', $parsed['tasks'][1]['priority']);
        $this->assertNull(DeepAuditPrompt::parse('Nic.'));
    }

    public function test_zadani_obsahuje_vzor_bez_ceniku(): void
    {
        $system = DeepAuditPrompt::system();

        $this->assertStringContainsString('## Indexace a sitemapa', $system);
        $this->assertStringNotContainsString('## Cenová nabídka', $system);
    }

    public function test_claude_prepise_audit_i_checklist(): void
    {
        $audit = $this->auditZFirmy();
        $this->app->instance(ProspectAi::class, new class extends FakeAi
        {
            public function deepAudit(Company $company, array $scout): ?array
            {
                return [
                    'body' => "## Shrnutí\n\nPodrobně.\n\n::: zámek\n\n## Obsah\n\nDetail.",
                    'highlights' => [['value' => '12', 'label' => 'kategorií bez textu']],
                    'tasks' => [
                        ['area' => 'Obsah', 'task' => 'Napsat texty kategorií', 'fix' => 'Úvod a otázky.', 'priority' => 'must'],
                        ['area' => 'Rychlost', 'task' => 'Zmenšit fotky', 'fix' => 'WebP.', 'priority' => 'nice'],
                    ],
                    'cost_usd' => 2.4,
                    'pages' => 9,
                ];
            }
        });

        $this->assertTrue(app(AuditFromCompany::class)->rewriteWithClaude($audit));

        $audit->refresh();
        $this->assertSame('done', $audit->ai_status);
        $this->assertStringContainsString('9 stránek', $audit->ai_note);
        $this->assertStringContainsString('$2.40', $audit->ai_note);
        $this->assertStringContainsString('Podrobně.', $audit->body);
        $this->assertSame('12', $audit->highlights[0]['value']);

        $checklist = $audit->client->checklists()->first();
        $this->assertSame(['Napsat texty kategorií', 'Zmenšit fotky'], $checklist->items()->orderBy('id')->pluck('title')->all());
        $this->assertSame(['Obsah', 'Rychlost'], $checklist->categories()->orderBy('order_column')->pluck('title')->all());
    }

    public function test_nepovedeny_prepis_necha_koncept_a_rekne_proc(): void
    {
        $audit = $this->auditZFirmy();
        $body = $audit->body;
        $this->app->instance(ProspectAi::class, new class extends FakeAi
        {
            public function lastError(): ?string
            {
                return 'Rate limit';
            }
        });

        $this->assertFalse(app(AuditFromCompany::class)->rewriteWithClaude($audit));

        $audit->refresh();
        $this->assertSame('failed', $audit->ai_status);
        $this->assertSame('Rate limit', $audit->ai_note);
        $this->assertSame($body, $audit->body);
    }

    public function test_vytvoreni_auditu_s_claudem_spusti_podrobny_audit_po_odpovedi(): void
    {
        Bus::fake();
        $this->fakeShop();
        $this->actingAs($this->obchodnik());
        $this->app->instance(ProspectAi::class, new FakeAi);
        $company = app(ProspectScout::class)->scout($this->firma());

        Livewire::test(EditCompany::class, ['record' => $company->getKey()])->callAction('createAudit');

        Bus::assertDispatchedAfterResponse(WriteDeepAudit::class);
        $this->assertSame('running', Audit::latest('id')->first()->ai_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Otevření auditu klientem
    |--------------------------------------------------------------------------
    */

    public function test_otevreni_auditu_se_zapise_do_crm_s_follow_upem(): void
    {
        $audit = $this->auditZFirmy();
        $audit->update(['is_public' => true]);
        $company = Company::first();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari/605.1')
            ->get(route('audit.show', $audit->public_token))->assertOk();

        $audit->refresh();
        $this->assertSame(1, $audit->view_count);
        $this->assertNotNull($audit->first_viewed_at);

        $activity = $company->activities()->where('type', ActivityType::Note)->latest('id')->first();
        $this->assertSame('Otevřeli audit poprvé', $activity->subject);
        $this->assertNotNull($company->fresh()->next_action_at);

        // Hned druhé otevření se počítá, ale novou aktivitu nezakládá.
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari/605.1')
            ->get(route('audit.show', $audit->public_token));

        $this->assertSame(2, $audit->fresh()->view_count);
        $this->assertSame(1, $company->activities()->where('subject', 'like', 'Otevřeli%')->count());
    }

    public function test_roboti_a_prihlaseny_spravce_se_nepocitaji(): void
    {
        $audit = $this->auditZFirmy();
        $audit->update(['is_public' => true]);

        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get(route('audit.show', $audit->public_token));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->withHeader('User-Agent', 'Mozilla/5.0 Safari')
            ->get(route('audit.show', $audit->public_token));

        $this->assertSame(0, $audit->fresh()->view_count);
    }

    /*
    |--------------------------------------------------------------------------
    | Administrace a API
    |--------------------------------------------------------------------------
    */

    public function test_karta_firmy_ukaze_posouzeni_a_vytvori_audit(): void
    {
        $this->fakeShop();
        $this->actingAs($this->obchodnik());
        $company = app(ProspectScout::class)->scout($this->firma());
        // Datové migrace zakládají audit Světa Cejlonu, počítáme přírůstek.
        $before = Audit::count();

        Livewire::test(EditCompany::class, ['record' => $company->getKey()])
            ->assertSee('Silný kandidát')
            ->assertSee('Na mobilu se web načítá pomalu')
            ->callAction('createAudit')
            ->assertRedirect();

        $this->assertSame($before + 1, Audit::count());
        $this->assertSame($company->client->getKey(), Audit::latest('id')->first()->client_id);
    }

    public function test_api_zalozi_nove_firmy_a_preskoci_zname(): void
    {
        config(['crm.import_token' => 'tajne']);
        $this->firma(['website' => 'znama.cz']);

        $this->postJson(route('crm.companies.import'), [
            'companies' => [
                ['website' => 'https://www.znama.cz'],
                ['website' => 'nova.cz', 'name' => 'Nová', 'segment' => 'eshop'],
                ['website' => 'nova.cz/kontakt'],
            ],
        ], ['X-Crm-Token' => 'tajne'])
            ->assertOk()
            ->assertJson(['created' => 1, 'skipped' => 2]);

        $nova = Company::where('domain', 'nova.cz')->first();
        $this->assertSame(CompanyStatus::New, $nova->status);
        $this->assertNull($nova->scouted_at);
    }

    public function test_api_prevezme_mereni_a_posudek_bez_dotazu_na_clauda(): void
    {
        config(['crm.import_token' => 'tajne']);
        $this->fakeShop();
        $this->app->instance(ProspectAi::class, new class extends FakeAi
        {
            public function judge(Company $company, array $scout): ?array
            {
                throw new \RuntimeException('Claude se nemá ptát.');
            }
        });

        $measurements = app(WebScout::class)->measure('https://shop.test');
        $measurements['pagespeed'] = null;
        Bus::fake();

        $this->postJson(route('crm.companies.import'), [
            'companies' => [[
                'website' => 'shop.test',
                'name' => 'Čajovna',
                'measurements' => $measurements,
                'assessment' => ['summary' => 'Čaje z Cejlonu.', 'adjustment' => 30, 'note' => 'Hodí se.', 'hook' => 'Máte pomalý mobil — a menu.'],
            ]],
        ], ['X-Crm-Token' => 'tajne'])
            ->assertOk()
            ->assertJson(['created' => 1, 'pagespeed_pending' => 1]);

        $company = Company::where('domain', 'shop.test')->first();
        $this->assertNotNull($company->scouted_at);
        $this->assertSame(20, $company->scout_data['ai']['adjustment']);
        $this->assertSame($company->scout_data['base_score'] + 20, $company->fit_score);
        $this->assertSame('Máte pomalý mobil, a menu.', $company->pain);
        Bus::assertDispatchedAfterResponse(CompletePageSpeed::class);

        (new CompletePageSpeed([$company->getKey()]))->handle(app(WebScout::class), app(ProspectScout::class));

        $company->refresh();
        $this->assertSame(38, $company->scout_data['measurements']['pagespeed']['score']);
        $this->assertSame('Čaje z Cejlonu.', $company->scout_data['ai']['summary']);
    }

    public function test_api_obnovi_smazanou_firmu_a_vrati_skore(): void
    {
        config(['crm.import_token' => 'tajne']);
        $old = $this->firma(['website' => 'stara.cz', 'status' => CompanyStatus::Parked]);
        $old->delete();

        $this->postJson(route('crm.companies.import'), [
            'companies' => [['website' => 'https://stara.cz', 'measurements' => ['reachable' => false, 'error' => 'Nejde načíst.']]],
        ], ['X-Crm-Token' => 'tajne'])
            ->assertOk()
            ->assertJson(['created' => 0, 'restored' => 1]);

        $this->getJson(route('crm.companies.scout', ['website' => 'www.stara.cz']), ['X-Crm-Token' => 'tajne'])
            ->assertOk()
            ->assertJson(['fit_score' => 0, 'fit_verdict' => FitVerdict::Unreachable->value, 'status' => CompanyStatus::Parked->value]);

        $this->getJson(route('crm.companies.scout', ['website' => 'nikde.cz']), ['X-Crm-Token' => 'tajne'])->assertNotFound();
    }

    public function test_audit_z_claude_code_se_nahraje_pres_api(): void
    {
        config(['crm.import_token' => 'tajne']);
        $body = "## Shrnutí\n\n".str_repeat('Konkrétní zjištění — s pomlčkou. ', 10)."\n\n## Nejdůležitější nález\n\nA.\n\n::: zámek\n\n## Obsah\n\nB.";

        $this->postJson(route('crm.audits.import'), [
            'website' => 'https://www.nova-eshop.cz/',
            'name' => 'Nový e-shop',
            'body' => $body,
            'highlights' => [['value' => '34 / 100', 'label' => 'rychlost na mobilu']],
            'tasks' => [['area' => 'Obsah', 'task' => 'Napsat texty kategorií', 'priority' => 'must']],
        ], ['X-Crm-Token' => 'tajne'])
            ->assertOk()
            ->assertJson(['lock_marker' => true]);

        $company = Company::where('domain', 'nova-eshop.cz')->first();
        $audit = $company->client->audits()->first();

        $this->assertSame('Audit e-shopu nova-eshop.cz', $audit->title);
        $this->assertFalse($audit->is_public);
        $this->assertTrue($audit->is_teaser);
        $this->assertStringNotContainsString('—', $audit->body);
        $this->assertStringStartsWith('Napsal Claude Code', $audit->ai_note);
        $this->assertSame(['Napsat texty kategorií'], $company->client->checklists()->first()->items()->pluck('title')->all());
    }

    public function test_api_auditu_bez_tokenu_neprijme_nic(): void
    {
        config(['crm.import_token' => 'tajne']);

        $this->postJson(route('crm.audits.import'), ['website' => 'a.cz', 'body' => str_repeat('x', 300)])->assertUnauthorized();
        $this->assertSame(0, Company::where('domain', 'a.cz')->count());
    }

    public function test_api_vrati_chyby_jako_json_i_bez_hlavicky_accept(): void
    {
        config(['crm.import_token' => 'tajne']);

        $this->post(route('crm.audits.import'), [], ['X-Crm-Token' => 'tajne'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website', 'body']);
    }

    public function test_mereni_z_prikazu_vypise_json(): void
    {
        $this->fakeShop();

        $this->artisan('crm:measure', ['web' => 'shop.test'])
            ->expectsOutputToContain('"platform": "Shoptet"')
            ->assertSuccessful();
    }

    public function test_rozepsany_audit_se_sam_obnovuje(): void
    {
        $audit = $this->auditZFirmy();
        $this->actingAs($this->obchodnik());
        $audit->forceFill(['ai_status' => 'running'])->save();

        Livewire::test(EditAudit::class, ['record' => $audit->getKey()])
            ->assertSeeHtml('wire:poll.15s="checkDeepAudit"')
            ->assertSee('Claude prochází web');

        $audit->forceFill(['ai_status' => 'done', 'ai_note' => 'Hotovo.'])->save();

        Livewire::test(EditAudit::class, ['record' => $audit->getKey()])
            ->call('checkDeepAudit')
            ->assertRedirect();
    }

    public function test_claude_je_bez_vypinace_vypnuty_i_s_klicem(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test', 'services.anthropic.enabled' => false]);
        $this->assertInstanceOf(NullProspectAi::class, app(ProspectAi::class));
        $this->assertFalse(app(ProspectAi::class)->enabled());

        $audit = $this->auditZFirmy();

        Livewire::actingAs($this->obchodnik())
            ->test(EditAudit::class, ['record' => $audit->getKey()])
            ->assertActionHidden('deepAudit');

        config(['services.anthropic.enabled' => true]);
        $this->assertInstanceOf(ClaudeProspectAi::class, app(ProspectAi::class));
    }

    public function test_hledani_bez_klice_nic_nedela(): void
    {
        $this->artisan('crm:discover')
            ->expectsOutputToContain('Chybí ANTHROPIC_API_KEY')
            ->assertSuccessful();
    }

    public function test_hledani_zalozi_a_proklepne_navrzene_firmy(): void
    {
        $this->fakeShop();
        $this->firma(['name' => 'Známá', 'website' => 'znama.cz']);
        $this->app->instance(ProspectAi::class, new class extends FakeAi
        {
            public function enabled(): bool
            {
                return true;
            }

            public function discover(string $brief, int $count, array $knownDomains): array
            {
                return [
                    ['name' => 'Čajovna', 'website' => 'https://shop.test', 'city' => 'Hradec Králové', 'reason' => 'Shoptet a reklama.'],
                    ['name' => 'Známá', 'website' => 'znama.cz', 'city' => null, 'reason' => 'Duplicita.'],
                ];
            }
        });

        $this->artisan('crm:discover')->assertSuccessful();

        $nova = Company::where('domain', 'shop.test')->first();
        $this->assertNotNull($nova->scouted_at);
        $this->assertSame('Hradec Králové', $nova->city);
        $this->assertSame(1, Company::where('domain', 'znama.cz')->count());
    }
}

/** Claude bez sítě. Testy si přepíšou jen metodu, kterou potřebují. */
class FakeAi implements ProspectAi
{
    public function enabled(): bool
    {
        return true;
    }

    public function lastError(): ?string
    {
        return null;
    }

    public function ping(): ?string
    {
        return null;
    }

    public function judge(Company $company, array $scout): ?array
    {
        return null;
    }

    public function auditSummary(Company $company, array $scout): ?string
    {
        return null;
    }

    public function deepAudit(Company $company, array $scout): ?array
    {
        return null;
    }

    public function discover(string $brief, int $count, array $knownDomains): array
    {
        return [];
    }
}
