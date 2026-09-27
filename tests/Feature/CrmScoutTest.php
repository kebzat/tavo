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
use App\Filament\Tools\Resources\Companies\Pages\EditCompany;
use App\Models\Audit;
use App\Models\Crm\Company;
use App\Models\User;
use App\Support\Crm\Ai\ProspectAi;
use App\Support\Crm\AuditFromCompany;
use App\Support\Crm\Scout\ProspectScout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

    public function test_agentura_je_partner_bez_ohledu_na_skore(): void
    {
        $this->fakeShop();

        $company = app(ProspectScout::class)->scout($this->firma(['segment' => CompanySegment::Agency]));

        $this->assertSame(FitVerdict::Partner, $company->fit_verdict);
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

    public function discover(string $brief, int $count, array $knownDomains): array
    {
        return [];
    }
}
