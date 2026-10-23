<?php

namespace Tests\Feature;

use App\Enums\Ads\AdEventType;
use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\AlertStatus;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\Ads\ReportStatus;
use App\Enums\Ads\ReportType;
use App\Enums\UserRole;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdEvent;
use App\Models\Ads\AdGroupReach;
use App\Models\Ads\AdTopAd;
use App\Models\Client;
use App\Models\User;
use App\Support\Ads\AccountSync;
use App\Support\Ads\AdsScope;
use App\Support\Ads\AlertEngine;
use App\Support\Ads\BusinessResults;
use App\Support\Ads\CampaignGroups;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\GroupReach;
use App\Support\Ads\MorningSummary;
use App\Support\Ads\PerformanceView;
use App\Support\Ads\Period;
use App\Support\Ads\PeriodReach;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\ReportBuilder;
use App\Support\Ads\TopAds;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pracovní postup nad reklamami (zpětná vazba Pavla, body 2 a 4 až 9):
 * skupiny kampaní, upozornění jako pracovní seznam, kalendář termínů,
 * reporty se schválením, historie zásahů, obchodní výsledky a reklamy.
 */
class AdsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 09:00:00');
        config(['ads.meta.token' => 'test-token', 'ads.meta.app_secret' => null]);
    }

    protected function tearDown(): void
    {
        ApiGuard::close();

        parent::tearDown();
    }

    /**
     * Klient, jehož účet míchá prodej, boosting a nábor.
     *
     * @param  array<string, mixed>  $settings
     */
    private function klient(array $settings = []): Client
    {
        $client = Client::create(['name' => 'Hokej Puk', 'slug' => 'hokej-puk']);
        $client->adSettings()->create($settings + ['primary_goal' => PrimaryGoal::Purchases]);

        $account = $client->adAccounts()->create([
            'platform' => AdPlatform::Meta,
            'external_id' => '123456',
            'name' => 'Puk (Meta)',
            'status' => 'active',
            'last_synced_at' => now()->subDay(),
            'data_through' => '2026-09-29',
        ]);
        $account->campaigns()->create(['external_id' => '1', 'name' => '[Sales] ASC+']);
        $account->campaigns()->create(['external_id' => '2', 'name' => 'Boost zápas']);
        $account->campaigns()->create(['external_id' => '3', 'name' => '[HR] Nábor prodavač']);

        return $client->load(['adAccounts', 'adSettings']);
    }

    /** @param  array<string, mixed>  $values */
    private function dny(Client $client, string $campaign, string $from, string $to, array $values): void
    {
        $account = $client->adAccounts->first();
        $id = $account->campaigns()->where('external_id', $campaign)->value('id');

        foreach (Period::between($from, $to)->dates() as $date) {
            AdDailyStat::query()->insert(['ad_account_id' => $account->id, 'ad_campaign_id' => $id, 'date' => $date, 'created_at' => now(), 'updated_at' => now()] + $values);
        }
    }

    private function prodej(Client $client, array $values = []): AdCampaignGroup
    {
        $group = $client->adCampaignGroups()->create($values + ['name' => 'Prodej', 'goal' => PrimaryGoal::Purchases, 'name_rule' => 'sales']);
        CampaignGroups::applyRules($client);

        return $group;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');
    }

    /*
    |--------------------------------------------------------------------------
    | Skupiny kampaní
    |--------------------------------------------------------------------------
    */

    public function test_skupina_pocita_jen_sve_kampane_a_celek_zustava(): void
    {
        $client = $this->klient();
        $this->dny($client, '1', '2026-09-23', '2026-09-29', ['spend' => 300, 'impressions' => 3000, 'link_clicks' => 30, 'purchases' => 2, 'purchase_value' => 1200]);
        $this->dny($client, '2', '2026-09-23', '2026-09-29', ['spend' => 100, 'impressions' => 10000, 'engagements' => 400]);
        $group = $this->prodej($client, ['target_cpa' => 200]);

        $week = Period::preset('7d');
        $this->assertEqualsWithDelta(2100, (new AdsScope($client, $group))->totals($week)->spend(), 0.01);
        $this->assertEqualsWithDelta(2800, (new AdsScope($client))->totals($week)->spend(), 0.01);
        $this->assertEqualsWithDelta(700, AdsScope::for($client, AdsScope::OUTSIDE)->totals($week)->spend(), 0.01);

        // Cena za nákup skupiny se počítá bez boostingu: 2 100 / 14 = 150 Kč, celý účet by dal 200 Kč.
        $view = new PerformanceView(ClientPerformance::build($client, $week, new AdsScope($client, $group)));
        $this->assertSame('Prodej', $view->scopeLabel());
        $this->assertTrue($view->targets()[0]['ok']);
    }

    public function test_nove_kampane_se_zaradi_podle_nazvu_nebo_cekaji(): void
    {
        $client = $this->klient();
        $group = $this->prodej($client);

        $this->assertSame($group->id, AdCampaign::query()->where('external_id', '1')->value('ad_campaign_group_id'));
        $this->dny($client, '2', '2026-09-28', '2026-09-29', ['spend' => 50]);
        $this->assertSame(['Boost zápas'], CampaignGroups::waiting($client)->pluck('name')->all());

        // Ruční „nechat mimo skupiny“: už nečeká a pravidlo ji zpátky nepřidá.
        CampaignGroups::assign($client, AdCampaign::query()->where('external_id', '2')->value('id'), null);
        $group->update(['name_rule' => 'sales, boost']);
        CampaignGroups::applyRules($client);
        $this->assertNull(AdCampaign::query()->where('external_id', '2')->value('ad_campaign_group_id'));
        $this->assertTrue(CampaignGroups::waiting($client)->isEmpty());
    }

    public function test_skupinu_jde_zalozit_zaskrtnutim_kampani(): void
    {
        $client = $this->klient();
        $this->admin();
        $hr = AdCampaign::query()->where('external_id', '3')->value('id');

        Livewire::test(AdsClient::class, ['client' => $client])
            ->callAction('createGroup', ['name' => 'Nábor', 'goal' => PrimaryGoal::Recruitment->value, 'campaigns' => [$hr], 'target_cpa' => 150, 'monthly_budget' => 3000])
            ->assertHasNoActionErrors();

        $group = $client->adCampaignGroups()->sole();
        $this->assertSame(PrimaryGoal::Recruitment, $group->goal);
        $this->assertSame([$hr], $group->campaignIds());
        $this->assertSame('Uchazeči', $group->goal->conversionLabel());
    }

    public function test_upozorneni_skupiny_maji_vlastni_cil_a_otisk(): void
    {
        $client = $this->klient(['target_cpa' => 1000]);
        $this->dny($client, '1', '2026-09-23', '2026-09-29', ['spend' => 1000, 'impressions' => 10000, 'link_clicks' => 100, 'purchases' => 2, 'purchase_value' => 3000]);
        $group = $this->prodej($client, ['target_cpa' => 300]);

        app(AlertEngine::class)->run();

        // Cena za nákup 500 Kč: nad cílem skupiny (300), pod cílem klienta (1000).
        $alert = $client->adAlerts()->where('rule', 'cost_above_target')->sole();
        $this->assertSame($group->id, $alert->ad_campaign_group_id);
        $this->assertStringStartsWith('Prodej: ', $alert->title);
        $this->assertStringEndsWith(':g'.$group->id, $alert->fingerprint);
        $this->assertStringContainsString('proti cíli', $alert->facts);
        $this->assertStringContainsString('útrata', $alert->basis);
    }

    public function test_dosah_skupiny_jednim_dotazem_jen_za_jeji_kampane(): void
    {
        ApiGuard::openForSync();
        $client = $this->klient();
        $group = $this->prodej($client);
        Http::fake(['graph.facebook.com/*/insights*' => Http::response(['data' => []]), 'graph.facebook.com/*' => Http::response(['account_id' => '123456', 'name' => 'Puk', 'currency' => 'CZK', 'account_status' => 1, 'data' => []])]);

        app(AccountSync::class)->sync($client->adAccounts->first(), Period::preset('7d'), withReach: true);

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), '"field":"campaign.id","operator":"IN","value":["1"]'));
        $this->assertNotNull(GroupReach::total($group, Period::preset('7d')));

        // Po změně složení skupiny starý dosah neplatí.
        CampaignGroups::sync($group, AdCampaign::query()->whereIn('external_id', ['1', '2'])->pluck('id')->all());
        $this->assertNull(GroupReach::total($group->fresh(), Period::preset('7d')));
        $this->assertSame(count(PeriodReach::ranges()), AdGroupReach::query()->where('ad_campaign_group_id', $group->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Upozornění
    |--------------------------------------------------------------------------
    */

    public function test_cekam_na_klienta_odlozeni_a_navrat_do_normalu(): void
    {
        $client = $this->klient(['monthly_budget' => 30000]);
        $this->dny($client, '1', '2026-09-01', '2026-09-29', ['spend' => 100, 'impressions' => 1000]);
        app(AlertEngine::class)->run();
        $alert = $client->adAlerts()->where('rule', 'budget_pacing')->sole();

        $alert->moveTo(AlertStatus::Waiting, 'Pavel', 'schválení rozpočtu');
        app(AlertEngine::class)->run();
        $this->assertSame(AlertStatus::Waiting, $alert->refresh()->status);
        $this->assertSame('schválení rozpočtu', $alert->waiting_for);

        $alert->snooze(today()->addDays(3), 'Pavel');
        $this->assertFalse(AdAlert::query()->visible()->whereKey($alert->id)->exists());
        $this->assertTrue(AdAlert::query()->visible(today()->addDays(3))->whereKey($alert->id)->exists());

        // Rozpočet se srovnal: upozornění se samo zavře jako „vrátilo se do normálu“.
        $client->adSettings->update(['monthly_budget' => 3000]);
        app(AlertEngine::class)->run();
        $alert->refresh();
        $this->assertSame(AlertStatus::Resolved, $alert->status);
        $this->assertSame('system', $alert->resolved_by);
        $this->assertSame('2026-09-30', $alert->recovered_on->toDateString());
        $this->assertStringContainsString('Vrátilo se do normálu', implode(' ', $alert->history));

        // Problém se vrátil do dvou týdnů: totéž upozornění, druhý výskyt.
        $client->adSettings->update(['monthly_budget' => 30000]);
        app(AlertEngine::class)->run();
        $this->assertSame(1, $client->adAlerts()->where('rule', 'budget_pacing')->count());
        $this->assertSame(2, $alert->refresh()->occurrences);
        $this->assertSame(AlertStatus::Open, $alert->status);
    }

    public function test_upozorneni_jde_odlozit_a_cekat_z_detailu_klienta(): void
    {
        $client = $this->klient(['monthly_budget' => 30000]);
        $this->dny($client, '1', '2026-09-01', '2026-09-29', ['spend' => 100, 'impressions' => 1000]);
        app(AlertEngine::class)->run();
        $alert = $client->adAlerts()->sole();
        $this->admin();

        Livewire::test(AdsClient::class, ['client' => $client])
            ->callAction('waitAlert', ['waiting_for' => 'fotky produktů'], ['alert' => $alert->id])
            ->callAction('snoozeAlert', ['days' => 7], ['alert' => $alert->id])
            ->assertSee('čeká se na: fotky produktů')
            ->assertSee('odloženo do 7. 10.');

        $this->assertSame(AlertStatus::Waiting, $alert->refresh()->status);
        $this->assertSame('2026-10-07', $alert->snoozed_until->toDateString());
    }

    public function test_pokles_ctr_neni_problem_kdyz_skupina_plni_cil(): void
    {
        $client = $this->klient(['target_cpa' => 500]);
        $this->dny($client, '1', '2026-09-16', '2026-09-22', ['spend' => 1000, 'impressions' => 10000, 'link_clicks' => 300, 'reach' => 9000, 'purchases' => 4]);
        $this->dny($client, '1', '2026-09-23', '2026-09-29', ['spend' => 1000, 'impressions' => 10000, 'link_clicks' => 150, 'reach' => 9000, 'purchases' => 4]);

        app(AlertEngine::class)->run();
        // CTR spadlo na polovinu, ale cena za nákup 250 Kč plní cíl 500 Kč.
        $this->assertFalse(AdAlert::query()->where('rule', 'creative_fatigue')->exists());

        $client->adSettings->update(['target_cpa' => 100]);
        app(AlertEngine::class)->run();
        $this->assertTrue(AdAlert::query()->where('rule', 'creative_fatigue')->exists());
    }

    public function test_male_utraty_vykonova_pravidla_nehodnoti(): void
    {
        $client = $this->klient(['target_cpa' => 10]);
        $this->dny($client, '1', '2026-09-23', '2026-09-29', ['spend' => 50, 'impressions' => 1000, 'purchases' => 2]);

        app(AlertEngine::class)->run();
        $this->assertFalse(AdAlert::query()->where('rule', 'cost_above_target')->exists());

        $client->adSettings->update(['alert_overrides' => ['min_spend' => 100, 'min_conversions' => 1]]);
        app(AlertEngine::class)->run();
        $this->assertTrue(AdAlert::query()->where('rule', 'cost_above_target')->exists());
    }

    /*
    |--------------------------------------------------------------------------
    | Kalendář a ranní souhrn
    |--------------------------------------------------------------------------
    */

    public function test_termin_upozorni_predem_a_visi_az_do_vyreseni(): void
    {
        $client = $this->klient();
        $license = $client->adEvents()->create(['type' => AdEventType::LicenseEnd, 'title' => 'Licence UGC', 'due_on' => '2026-10-05', 'remind_days' => 7]);
        $later = $client->adEvents()->create(['type' => AdEventType::Preparation, 'title' => 'Black Friday', 'due_on' => '2026-11-10', 'remind_days' => 14]);

        $this->assertSame('2026-09-28', $license->remind_on->toDateString());
        $this->assertSame([$license->id], AdEvent::query()->due()->pluck('id')->all());
        $this->assertSame('za 5 dní', $license->whenLabel());

        // Po termínu pořád visí, dokud ho někdo nevyřeší.
        $this->assertTrue(AdEvent::query()->due('2026-10-20')->whereKey($license->id)->exists());
        $this->assertTrue($license->isOverdue('2026-10-20'));
        $license->update(['resolved_at' => now()]);
        $this->assertFalse(AdEvent::query()->due('2026-10-20')->whereKey($license->id)->exists());
        $this->assertTrue(AdEvent::query()->due('2026-10-27')->whereKey($later->id)->exists());
    }

    public function test_ranni_souhrn_secte_data_vykon_terminy_a_reporty(): void
    {
        $client = $this->klient(['monthly_budget' => 30000]);
        $this->dny($client, '1', '2026-09-01', '2026-09-29', ['spend' => 100, 'impressions' => 1000]);
        app(AlertEngine::class)->run();
        $client->adEvents()->create(['type' => AdEventType::LicenseEnd, 'title' => 'Licence', 'due_on' => '2026-10-02']);
        app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));

        $this->assertSame('1 výkonová odchylka, 1 končící licence, 1 report ke kontrole', MorningSummary::for()->sentence());
    }

    /*
    |--------------------------------------------------------------------------
    | Reporty a zásahy
    |--------------------------------------------------------------------------
    */

    public function test_report_za_skupinu_se_zasahy_a_schvalenim(): void
    {
        $client = $this->klient();
        $this->dny($client, '1', '2026-09-21', '2026-09-27', ['spend' => 100, 'impressions' => 1000, 'purchases' => 1, 'purchase_value' => 500]);
        $this->dny($client, '2', '2026-09-21', '2026-09-27', ['spend' => 900, 'impressions' => 9000]);
        $group = $this->prodej($client);
        $client->adChanges()->create(['type' => 'budget', 'changed_on' => '2026-09-24', 'description' => 'Rozpočet ASC+ na 120 Kč', 'ad_campaign_group_id' => $group->id]);
        $builder = app(ReportBuilder::class);

        $report = $builder->create($client, ReportType::Weekly, Period::week(now()->subWeek()), new AdsScope($client, $group));
        $this->assertStringEndsWith('· Prodej', $report->title);
        $this->assertSame(700.0, (float) $report->snapshot['totals']['spend']);
        $this->assertSame('Rozpočet ASC+ na 120 Kč', $report->snapshot['changes'][0]['description']);

        // Schválený report se po dopočtu z Mety tiše nezmění, jen to víme.
        $builder->approve($report);
        AdDailyStat::query()->where('date', '2026-09-27')->update(['purchases' => 3]);
        $builder->refresh($report->refresh());
        $this->assertSame(7.0, (float) $report->refresh()->snapshot['totals']['purchases']);
        $this->assertSame(ReportStatus::Approved, $report->status);
        $this->assertSame('Nákupy', $builder->drift($report)[0]['label']);

        $report->update(['is_public' => true, 'worked' => 'Remarketing táhne.', 'status' => ReportStatus::Sent]);
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/report/'.$report->slug)
            ->assertOk()
            ->assertSee('Kampaně: Prodej')
            ->assertSee('Co fungovalo')
            ->assertSee('Co jsme změnili')
            ->assertSee('Rozpočet ASC+ na 120 Kč');
    }

    /*
    |--------------------------------------------------------------------------
    | Obchodní výsledky a reklamy
    |--------------------------------------------------------------------------
    */

    public function test_trzby_eshopu_vedle_atribuce_reklam(): void
    {
        $client = $this->klient();
        $this->dny($client, '1', '2026-08-01', '2026-08-31', ['spend' => 100, 'purchases' => 1, 'purchase_value' => 1000]);
        $client->adBusinessResults()->create(['period_from' => '2026-08-01', 'period_to' => '2026-08-31', 'orders' => 120, 'revenue' => 62000]);

        $row = BusinessResults::shop($client)[0];

        // Útrata 3 100 Kč z tržeb 62 000 Kč = PNO 5 %. Reklamy si připsaly 31 000 Kč, tedy 50 % tržeb.
        $this->assertSame("5,0\u{00A0}%", $row['cos']);
        $this->assertStringContainsString("(50\u{00A0}% tržeb)", $row['attributed']);
    }

    public function test_cena_za_relevantni_lead_a_obchod(): void
    {
        $client = $this->klient(['primary_goal' => PrimaryGoal::Leads]);
        $this->dny($client, '3', '2026-09-01', '2026-09-10', ['spend' => 100, 'leads' => 2]);
        $client->leadFeedback()->create(['period_from' => '2026-09-01', 'period_to' => '2026-09-10', 'leads' => 20, 'qualified' => 5, 'deals' => 2]);

        $row = BusinessResults::leads($client)[0];

        $this->assertSame("200\u{00A0}Kč", $row['cost_qualified']);
        $this->assertSame("500\u{00A0}Kč", $row['cost_deal']);
    }

    public function test_nejlepsi_reklamy_skupiny(): void
    {
        $client = $this->klient();
        $group = $this->prodej($client);
        $account = $client->adAccounts->first();
        $base = ['ad_account_id' => $account->id, 'date_from' => '2026-08-31', 'date_to' => '2026-09-29', 'impressions' => 1000, 'link_clicks' => 10];
        AdTopAd::create($base + ['external_id' => 'a', 'name' => 'Video UGC', 'campaign_external_id' => '1', 'spend' => 500, 'purchases' => 5]);
        AdTopAd::create($base + ['external_id' => 'b', 'name' => 'Banner', 'campaign_external_id' => '1', 'spend' => 900, 'purchases' => 0]);
        AdTopAd::create($base + ['external_id' => 'c', 'name' => 'Boost', 'campaign_external_id' => '2', 'spend' => 300]);

        $ads = collect(TopAds::for(new AdsScope($client, $group)))->keyBy('name');

        $this->assertCount(2, $ads);
        $this->assertSame('nejlevnější konverze', $ads['Video UGC']['tag']);
        $this->assertSame('utrácí bez konverze', $ads['Banner']['tag']);
    }

    public function test_nove_stranky_reklam_se_nactou(): void
    {
        $client = $this->klient(['monthly_budget' => 30000, 'target_cpa' => 300]);
        $this->dny($client, '1', '2026-09-01', '2026-09-29', ['spend' => 150, 'impressions' => 2000, 'link_clicks' => 30, 'purchases' => 1, 'purchase_value' => 700]);
        $group = $this->prodej($client);
        $client->adEvents()->create(['type' => AdEventType::LicenseEnd, 'title' => 'Licence na video', 'due_on' => '2026-10-03']);
        $client->adChanges()->create(['type' => 'creative', 'changed_on' => '2026-09-20', 'description' => 'Nové UGC video']);
        app(AlertEngine::class)->run();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get('/nastroje/reklamy')->assertOk()->assertSee('Dnes ráno:')->assertSee('Skupiny kampaní');
        $this->get('/nastroje/reklamy/klient/'.$client->id.'?skupina='.$group->id)->assertOk()
            ->assertSee('Licence na video')
            ->assertSee('Nové UGC video')
            ->assertSee('Mimo skupiny');
        $this->get('/nastroje/reklamy/klient/'.$client->id.'?skupina=mimo')->assertOk();
        $this->get('/nastroje/reklamy/terminy')->assertOk()->assertSee('Licence na video');
        $this->get('/nastroje/reklamy/upozorneni?kategorie=data')->assertOk();
        $this->get('/nastroje/reklamy/nastaveni')->assertOk()->assertSee('Stav dat po účtech')->assertSee('Šablony čísel');
        $this->get('/nastroje/ranni-prehled')->assertOk()->assertSee('Licence na video');
    }
}
