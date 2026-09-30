<?php

namespace Tests\Feature;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\AlertStatus;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\Ads\ReportStatus;
use App\Enums\Ads\ReportType;
use App\Enums\UserRole;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Mail\AdReportMail;
use App\Mail\AdsDailyDigest;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdReport;
use App\Models\Ads\AdSyncRun;
use App\Models\Ads\AnalyticsDailyStat;
use App\Models\Client;
use App\Models\User;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\AccountSync;
use App\Support\Ads\AlertEngine;
use App\Support\Ads\Billing;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\Metrics;
use App\Support\Ads\PerformanceView;
use App\Support\Ads\Period;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\Platforms\DemoAds;
use App\Support\Ads\Platforms\DemoCatalog;
use App\Support\Ads\Platforms\GoogleAds;
use App\Support\Ads\Platforms\MetaAds;
use App\Support\Ads\ReportBuilder;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 09:00:00');
        config(['ads.meta.token' => 'test-token', 'ads.meta.app_secret' => null]);
    }

    /**
     * Klient s jedním účtem a jednou kampaní.
     *
     * @param  array<string, mixed>  $settings
     */
    private function klient(array $settings = [], string $name = 'Čajovna Zkouška'): Client
    {
        $client = Client::create(['name' => $name, 'slug' => str($name)->slug(), 'contact_email' => 'majitel@cajovna.test']);
        $client->adSettings()->create($settings + ['primary_goal' => PrimaryGoal::Purchases]);

        $account = $client->adAccounts()->create([
            'platform' => AdPlatform::Meta,
            'external_id' => (string) random_int(100000, 999999),
            'name' => $name.' (Meta)',
            'status' => 'active',
            'last_synced_at' => now(),
        ]);
        $account->campaigns()->create(['external_id' => '1', 'name' => 'Prospecting']);

        return $client->load(['adAccounts', 'adSettings']);
    }

    /** Stejná čísla pro každý den od–do. */
    private function dny(Client $client, string $from, string $to, array $values): void
    {
        $account = $client->adAccounts->first();
        $campaign = $account->campaigns()->first();

        foreach (Period::between($from, $to)->dates() as $date) {
            AdDailyStat::query()->insert([
                'ad_account_id' => $account->id,
                'ad_campaign_id' => $campaign->id,
                'date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ] + $values);
        }
    }

    /** @return array<string, mixed> Řádek insights z Graph API. */
    private function insight(string $date, string $campaignId = '111', float $spend = 100, int $purchases = 2): array
    {
        return [
            'campaign_id' => $campaignId,
            'campaign_name' => 'Kampaň '.$campaignId,
            'objective' => 'OUTCOME_SALES',
            'spend' => (string) $spend,
            'impressions' => '1000',
            'reach' => '800',
            'clicks' => '30',
            'inline_link_clicks' => '20',
            'actions' => [
                ['action_type' => 'purchase', 'value' => (string) ($purchases + 5)],
                ['action_type' => 'omni_purchase', 'value' => (string) $purchases],
                ['action_type' => 'omni_add_to_cart', 'value' => '6'],
            ],
            'action_values' => [
                ['action_type' => 'omni_purchase', 'value' => (string) ($purchases * 500)],
            ],
            'date_start' => $date,
            'date_stop' => $date,
        ];
    }

    private function fakeMeta(array $insights, int $status = 1): void
    {
        Http::fake([
            'graph.facebook.com/*/insights*' => Http::response(['data' => $insights]),
            'graph.facebook.com/*/act_*' => Http::response([
                'account_id' => '123', 'name' => 'Čajovna (Meta)', 'currency' => 'CZK',
                'timezone_name' => 'Europe/Prague', 'account_status' => $status,
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Meta a synchronizace
    |--------------------------------------------------------------------------
    */

    public function test_nakupy_se_berou_ze_souhrnne_akce_jako_ve_spravci_reklam(): void
    {
        $stat = app(MetaAds::class)->dailyStat($this->insight('2026-09-28', purchases: 3));

        $this->assertSame(3.0, $stat->purchases);
        $this->assertSame(1500.0, $stat->purchaseValue);
        $this->assertSame(6.0, $stat->addToCart);
        $this->assertSame(20, $stat->linkClicks);
    }

    public function test_synchronizace_ulozi_dny_a_opakovane_nic_nezdvoji(): void
    {
        $client = $this->klient();
        $account = $client->adAccounts->first();
        $this->fakeMeta([$this->insight('2026-09-28'), $this->insight('2026-09-29'), $this->insight('2026-09-29', '222')]);

        $period = Period::between('2026-09-23', '2026-09-29');
        app(AccountSync::class)->sync($account, $period);
        $run = app(AccountSync::class)->sync($account, $period);

        $this->assertSame('ok', $run->status);
        $this->assertSame(3, $run->rows);
        $this->assertSame(3, AdDailyStat::query()->count());
        $this->assertSame(3, AdCampaign::query()->where('ad_account_id', $account->id)->count()); // „Prospecting“ z přípravy + dvě z Mety
        $this->assertNull($account->refresh()->last_sync_error);
    }

    public function test_chyba_tokenu_se_zapise_k_uctu(): void
    {
        $account = $this->klient()->adAccounts->first();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]], 400)]);

        $run = app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Token Meta je neplatný', $account->refresh()->last_sync_error);
    }

    public function test_prikaz_sync_preskoci_ucty_bez_pristupu(): void
    {
        config(['ads.meta.token' => null]);
        $this->klient();
        Http::fake();

        $this->artisan('ads:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Ochrana před přetížením API
    |--------------------------------------------------------------------------
    */

    public function test_omezeni_od_mety_se_neopakuje_a_pozastavi_stahovani_do_zitra(): void
    {
        $account = $this->klient()->adAccounts->first();
        Http::fake([
            'graph.facebook.com/*/insights*' => Http::response(['error' => ['message' => 'User request limit reached', 'code' => 17]], 400),
            'graph.facebook.com/*' => Http::response(['account_id' => '1', 'name' => 'Účet', 'currency' => 'CZK', 'account_status' => 1]),
        ]);

        $first = app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));
        $second = app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));

        // Účet + insights, bez jediného opakování. Druhý běh už na Metu nesáhne.
        Http::assertSentCount(2);
        $this->assertSame('failed', $first->status);
        $this->assertStringContainsString('pozastavené', $second->error);
        $this->assertNotNull(ApiGuard::for('meta')->pausedUntil());
    }

    public function test_vysoke_vytizeni_podle_hlavicek_mety_pozastavi_stahovani(): void
    {
        $account = $this->klient()->adAccounts->first();
        Http::fake(['graph.facebook.com/*' => Http::response(
            ['account_id' => '1', 'name' => 'Účet', 'currency' => 'CZK', 'account_status' => 1, 'data' => []],
            200,
            ['X-Business-Use-Case-Usage' => json_encode(['1' => [['type' => 'ads_insights', 'call_count' => 82, 'total_cputime' => 10, 'total_time' => 12, 'estimated_time_to_regain_access' => 0]]])],
        )]);

        app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));

        $this->assertNotNull(ApiGuard::for('meta')->pausedUntil());
    }

    public function test_denni_strop_dotazu_zastavi_dalsi_volani(): void
    {
        config(['ads.meta.daily_call_limit' => 3]);
        $account = $this->klient()->adAccounts->first();
        $this->fakeMeta([]);

        app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));
        $run = app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));

        Http::assertSentCount(3);
        $this->assertStringContainsString('strop 3 dotazů', $run->error);
    }

    public function test_jeden_ucet_se_nestahuje_dvakrat_soucasne(): void
    {
        $account = $this->klient()->adAccounts->first();
        Http::fake();
        $lock = Cache::lock('ads.sync.account.'.$account->id, 600);
        $lock->get();

        $run = app(AccountSync::class)->sync($account, Period::between('2026-09-23', '2026-09-29'));

        $this->assertSame('skipped', $run->status);
        Http::assertNothingSent();
        $lock->release();
    }

    public function test_chyba_pri_nacteni_seznamu_uctu_se_pamatuje(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token', 'code' => 190]], 400)]);
        $directory = app(AccountDirectory::class);

        $directory->options(AdPlatform::Meta);
        $directory->options(AdPlatform::Meta);
        $problem = $directory->problem(AdPlatform::Meta);

        Http::assertSentCount(1);
        $this->assertStringContainsString('Token Meta je neplatný', $problem);
    }

    public function test_rucni_nacteni_ma_pauzu(): void
    {
        $client = Client::create(['name' => 'Demo', 'slug' => 'demo']);
        $client->adAccounts()->create(['platform' => AdPlatform::Demo, 'external_id' => 'demo_listek_meta', 'name' => 'Demo']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');

        Livewire::test(AdsClient::class, ['client' => $client])->callAction('sync');
        Livewire::test(AdsClient::class, ['client' => $client])->callAction('sync');

        $this->assertSame(1, AdSyncRun::query()->count());
    }

    public function test_automaticky_se_stahuje_jen_jednou_denne(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains($event->command ?? '', 'ads:sync'));

        $this->assertCount(1, $events);
        $this->assertSame('0 6 * * *', $events->first()->expression);
    }

    /*
    |--------------------------------------------------------------------------
    | Čísla
    |--------------------------------------------------------------------------
    */

    public function test_pomery_se_pocitaji_ze_souctu_ne_z_prumeru(): void
    {
        // Den 1: 10 prokliků z 100 zobrazení (10 %), den 2: 10 z 900 (1,1 %).
        // Průměr denních CTR by dal 5,6 %, správně je 20 / 1000 = 2 %.
        $metrics = Metrics::fromArray(['impressions' => 1000, 'link_clicks' => 20, 'spend' => 400, 'purchases' => 4, 'purchase_value' => 2000]);

        $this->assertEqualsWithDelta(2.0, $metrics->ctr(), 0.0001);
        $this->assertEqualsWithDelta(100.0, $metrics->costPerConversion(PrimaryGoal::Purchases), 0.0001);
        $this->assertEqualsWithDelta(5.0, $metrics->roas(), 0.0001);
        $this->assertNull(Metrics::empty()->roas());
    }

    public function test_mesic_se_srovnava_se_stejnymi_dny_minuleho_mesice(): void
    {
        $thisMonth = Period::preset('this_month', CarbonImmutable::parse('2026-09-30'));
        $lastMonth = Period::preset('last_month', CarbonImmutable::parse('2026-09-30'));

        $this->assertSame(['from' => '2026-08-01', 'to' => '2026-08-29'], $thisMonth->previous()->toArray());
        $this->assertSame(['from' => '2026-08-01', 'to' => '2026-08-31'], $lastMonth->toArray());
        $this->assertSame(['from' => '2026-07-01', 'to' => '2026-07-31'], $lastMonth->previous()->toArray());
        $this->assertSame('23.–29. 9. 2026', Period::preset('7d')->label());
    }

    public function test_dlazdice_ukazou_zmenu_a_jestli_je_dobra(): void
    {
        $client = $this->klient(['target_cpa' => 150]);
        $this->dny($client, '2026-09-16', '2026-09-22', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20, 'purchases' => 2, 'purchase_value' => 1000]);
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20, 'purchases' => 1, 'purchase_value' => 500]);

        $tiles = collect((new PerformanceView(ClientPerformance::build($client, Period::preset('7d'))))->tiles())->keyBy('label');

        $this->assertSame("700\u{00A0}Kč", $tiles['Útrata']['value']);
        $this->assertSame('bad', $tiles['Nákupy']['tone']);
        $this->assertSame('bad', $tiles['Cena za nákup']['tone']);
        $this->assertSame("cíl 150\u{00A0}Kč", $tiles['Cena za nákup']['hint']);
    }

    /*
    |--------------------------------------------------------------------------
    | Upozornění
    |--------------------------------------------------------------------------
    */

    public function test_drahe_konverze_u_maleho_uctu_nehlasime(): void
    {
        $client = $this->klient(['target_cpa' => 100]);
        // 7 nákupů za týden po 300 Kč, pod minimem 10 konverzí.
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 300, 'purchases' => 1]);

        app(AlertEngine::class)->run();

        $this->assertFalse(AdAlert::query()->where('rule', 'cost_above_target')->exists());
    }

    public function test_draha_konverze_nad_minimem_se_rozsviti_a_po_zlepseni_zavre(): void
    {
        $client = $this->klient(['target_cpa' => 100]);
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 600, 'purchases' => 3]);

        app(AlertEngine::class)->run();
        app(AlertEngine::class)->run();

        $alerts = AdAlert::query()->where('rule', 'cost_above_target')->get();
        $this->assertCount(1, $alerts);
        $this->assertSame(AlertStatus::Open, $alerts->first()->status);
        $this->assertStringContainsString('nad cílem', $alerts->first()->title);

        AdDailyStat::query()->update(['purchases' => 10]);
        app(AlertEngine::class)->run();

        $this->assertSame(AlertStatus::Resolved, $alerts->first()->refresh()->status);
    }

    public function test_zamitnute_upozorneni_se_tyden_neozve(): void
    {
        $client = $this->klient(['target_cpa' => 100]);
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 600, 'purchases' => 3]);

        app(AlertEngine::class)->run();
        AdAlert::query()->update(['status' => AlertStatus::Dismissed]);
        app(AlertEngine::class)->run();

        $this->assertSame(0, AdAlert::query()->live()->count());
    }

    public function test_utrata_bez_konverzi_po_funkcnim_mereni_hlasi_kontrolu_pixelu(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-09-01', '2026-09-26', ['spend' => 200, 'purchases' => 2]);
        $this->dny($client, '2026-09-27', '2026-09-29', ['spend' => 200, 'purchases' => 0]);

        app(AlertEngine::class)->run();

        $alert = AdAlert::query()->where('rule', 'no_conversions')->firstOrFail();
        $this->assertStringContainsString('měření', $alert->recommendation);
    }

    public function test_nezaplaceny_ucet_je_kriticke_upozorneni(): void
    {
        $client = $this->klient();
        $client->adAccounts->first()->update(['status' => 'unsettled']);

        app(AlertEngine::class)->run();

        $alert = AdAlert::query()->where('rule', 'account_status')->firstOrFail();
        $this->assertSame('critical', $alert->severity->value);
        $this->assertStringContainsString('Nezaplacený', $alert->title);
    }

    public function test_precerpani_rozpoctu(): void
    {
        $client = $this->klient(['monthly_budget' => 3000]);
        $this->dny($client, '2026-09-01', '2026-09-29', ['spend' => 200]);

        app(AlertEngine::class)->run();

        $this->assertTrue(AdAlert::query()->where('rule', 'budget_pacing')->where('title', 'like', 'Hrozí přečerpání%')->exists());
    }

    public function test_ranni_souhrn_odejde_jen_kdyz_je_co_hlasit(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'pavel@taveo.cz']);

        $this->artisan('ads:digest')->assertSuccessful();
        Mail::assertNothingSent();

        $client = $this->klient();
        $this->dny($client, '2026-09-29', '2026-09-29', ['spend' => 150, 'purchases' => 1]);
        $client->adAccounts->first()->update(['status' => 'disabled']);
        app(AlertEngine::class)->run();

        $this->artisan('ads:digest')->assertSuccessful();
        Mail::assertSent(AdsDailyDigest::class, fn (AdsDailyDigest $mail): bool => $mail->alerts->count() === 1 && count($mail->rows) === 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Reporty
    |--------------------------------------------------------------------------
    */

    public function test_report_ma_zmrazena_cisla(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-09-21', '2026-09-27', ['spend' => 100]);

        $report = app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));
        AdDailyStat::query()->update(['spend' => 999]);

        $this->assertEquals(700, $report->refresh()->snapshot['totals']['spend']);
        $this->assertSame('cajovna-zkouska-2026-09-21', $report->slug);
    }

    public function test_pondelni_beh_zalozi_koncepty_jen_jednou(): void
    {
        $client = $this->klient();
        $this->klient(['weekly_report' => false], 'Bez reportů');

        $this->artisan('ads:reports weekly')->assertSuccessful();
        $this->artisan('ads:reports weekly')->assertSuccessful();

        $this->assertSame(1, AdReport::query()->count());
        $this->assertSame($client->id, AdReport::query()->first()->client_id);
        $this->assertSame(ReportStatus::Draft, AdReport::query()->first()->status);
    }

    public function test_koncept_klient_bez_prihlaseni_neuvidi(): void
    {
        $client = $this->klient();
        $report = app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));

        $this->get('/report/'.$report->slug)->assertNotFound();

        $report->update(['is_public' => true, 'summary' => "Přesunuli jsme rozpočet.\n\n- první bod"]);

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->get('/report/'.$report->slug)
            ->assertOk()
            ->assertSee('Týdenní přehled reklam')
            ->assertSee('Přesunuli jsme rozpočet.')
            ->assertSee('noindex', false);

        $this->assertSame(1, $report->refresh()->view_count);
        $this->get('/report/'.$report->public_token)->assertRedirect('/report/'.$report->slug);
    }

    public function test_email_s_reportem_obsahuje_cisla_a_odkaz(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-09-21', '2026-09-27', ['spend' => 100, 'purchases' => 1]);
        $report = app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));
        $report->update(['is_public' => true]);

        $mail = new AdReportMail($report->refresh());
        $html = $mail->render();

        $this->assertStringContainsString('/report/cajovna-zkouska-2026-09-21', $html);
        $this->assertStringContainsString('700', $html);
        $this->assertStringNotContainsString('—', $html);
        $this->assertSame(['majitel@cajovna.test'], app(ReportBuilder::class)->recipients($client));
    }

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    */

    public function test_stranky_reklam_se_nacnou(): void
    {
        $client = $this->klient(['monthly_budget' => 5000, 'target_cpa' => 150, 'target_roas' => 4]);
        $this->dny($client, '2026-09-01', '2026-09-29', ['spend' => 150, 'impressions' => 2000, 'link_clicks' => 30, 'purchases' => 1, 'purchase_value' => 700]);
        $report = app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));
        app(AlertEngine::class)->run();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get('/nastroje/reklamy')->assertOk()->assertSee('Čajovna Zkouška');
        $this->get('/nastroje/reklamy/klient/'.$client->id)->assertOk()->assertSee('Prospecting')->assertSee('Cena za nákup');
        $this->get('/nastroje/reklamy/upozorneni')->assertOk();
        $this->get('/nastroje/reklamy/reporty')->assertOk()->assertSee($report->title);
        $this->get('/nastroje/reklamy/reporty/'.$report->id.'/edit')->assertOk();
        $this->get('/nastroje/reklamy/nastaveni')->assertOk()->assertSee('Kdy upozornit');
        $this->get('/report/'.$report->slug)->assertOk()->assertSee('Koncept');
    }

    public function test_editor_se_do_reklam_nedostane(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));

        $this->get('/nastroje/reklamy')->assertForbidden();
    }

    public function test_report_nepatri_do_vyhledavacu(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /report');
    }

    /*
    |--------------------------------------------------------------------------
    | Ukázková data
    |--------------------------------------------------------------------------
    */

    public function test_ukazkova_data_zalozi_klienty_s_cisly_a_smazou_jen_sebe(): void
    {
        $skutecny = $this->klient([], 'Skutečný klient');

        $this->artisan('ads:demo', ['--days' => 30])->assertSuccessful();
        $this->artisan('ads:demo', ['--days' => 30])->assertSuccessful();

        $demo = Client::query()->where('name', 'like', 'Ukázka:%')->get();
        $this->assertCount(count(DemoCatalog::CLIENTS), $demo);
        $this->assertGreaterThan(0, AdDailyStat::query()->whereIn('ad_account_id', AdAccount::query()->where('platform', AdPlatform::Demo)->pluck('id'))->count());
        $this->assertGreaterThan(0, AnalyticsDailyStat::query()->count());
        $this->assertTrue(AdAlert::query()->where('rule', 'no_conversions')->exists(), 'Levandule má rozbité měření');
        $this->assertTrue(AdReport::query()->where('is_public', true)->exists());

        $this->artisan('ads:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Client::query()->where('name', 'like', 'Ukázka:%')->count());
        $this->assertTrue($skutecny->fresh()->exists);
    }

    public function test_vypnuta_ukazkova_data_se_nezalozi(): void
    {
        config(['ads.demo_enabled' => false]);

        $this->artisan('ads:demo')->assertFailed();
        $this->assertSame(0, AdAccount::query()->count());
    }

    public function test_ukazkova_platforma_vraci_pro_stejny_den_stejna_cisla(): void
    {
        $client = Client::create(['name' => 'Demo', 'slug' => 'demo']);
        $account = $client->adAccounts()->create(['platform' => AdPlatform::Demo, 'external_id' => 'demo_listek_meta', 'name' => 'Demo']);
        $period = Period::between('2026-09-20', '2026-09-29');

        $first = app(DemoAds::class)->dailyStats($account, $period)->sum('spend');
        $second = app(DemoAds::class)->dailyStats($account, $period)->sum('spend');

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $first);
    }

    /*
    |--------------------------------------------------------------------------
    | Google Ads a GA4
    |--------------------------------------------------------------------------
    */

    public function test_google_ads_konverze_podle_cile_klienta(): void
    {
        $row = [
            'campaign' => ['id' => '42', 'name' => 'Search · značka', 'advertisingChannelType' => 'SEARCH'],
            'segments' => ['date' => '2026-09-28'],
            'metrics' => ['costMicros' => '123450000', 'impressions' => '1000', 'clicks' => '80', 'conversions' => 4.5, 'conversionsValue' => 3600.0],
        ];

        $shop = app(GoogleAds::class)->dailyStat($row, PrimaryGoal::Purchases);
        $leads = app(GoogleAds::class)->dailyStat($row, PrimaryGoal::Leads);

        $this->assertEqualsWithDelta(123.45, $shop->spend, 0.001);
        $this->assertSame(4.5, $shop->purchases);
        $this->assertSame(3600.0, $shop->purchaseValue);
        $this->assertSame(0.0, $leads->purchases);
        $this->assertSame(4.5, $leads->leads);
    }

    public function test_google_ads_synchronizace_pres_mcc(): void
    {
        config(['ads.google_ads' => [
            'client_id' => 'id', 'client_secret' => 'secret', 'refresh_token' => 'refresh',
            'login_customer_id' => '111-222-3333', 'developer_token' => null, 'api_version' => 'v24',
        ]]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'googleads.googleapis.com/*' => Http::sequence()
                ->push(['results' => [['customer' => ['id' => '4445556666', 'descriptiveName' => 'Zrnko', 'currencyCode' => 'CZK', 'timeZone' => 'Europe/Prague', 'status' => 'ENABLED']]]])
                ->push(['results' => [[
                    'campaign' => ['id' => '9', 'name' => 'PMax'],
                    'segments' => ['date' => '2026-09-28'],
                    'metrics' => ['costMicros' => '50000000', 'impressions' => '400', 'clicks' => '12', 'conversions' => 1, 'conversionsValue' => 900],
                ]]]),
        ]);

        $client = Client::create(['name' => 'Zrnko', 'slug' => 'zrnko']);
        $account = $client->adAccounts()->create(['platform' => AdPlatform::GoogleAds, 'external_id' => '444-555-6666', 'name' => 'Zrnko']);

        $run = app(AccountSync::class)->sync($account, Period::between('2026-09-28', '2026-09-28'));

        $this->assertSame('ok', $run->status, (string) $run->error);
        $this->assertEquals(50, AdDailyStat::query()->sum('spend'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'customers/4445556666/googleAds:search')
            && $request->hasHeader('login-customer-id', '1112223333'));
    }

    public function test_ga4_podepise_token_a_ulozi_navstevy_po_kanalech(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['ads.ga4.credentials' => json_encode(['client_email' => 'taveo@projekt.iam.gserviceaccount.com', 'private_key' => $pem])]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.ga4']),
            'analyticsadmin.googleapis.com/*' => Http::response(['displayName' => 'zrnko.cz', 'currencyCode' => 'CZK', 'timeZone' => 'Europe/Prague']),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [
                ['dimensionValues' => [['value' => '20260928'], ['value' => 'Paid Social']], 'metricValues' => [['value' => '120'], ['value' => '100'], ['value' => '70'], ['value' => '3'], ['value' => '2'], ['value' => '1800']]],
                ['dimensionValues' => [['value' => '20260928'], ['value' => 'Organic Search']], 'metricValues' => [['value' => '300'], ['value' => '250'], ['value' => '200'], ['value' => '6'], ['value' => '5'], ['value' => '4200']]],
            ]]),
        ]);

        $client = $this->klient();
        $property = $client->adAccounts()->create(['platform' => AdPlatform::Ga4, 'external_id' => '321', 'name' => 'GA4']);

        $run = app(AccountSync::class)->sync($property, Period::between('2026-09-28', '2026-09-28'));

        $this->assertSame('ok', $run->status, (string) $run->error);
        $this->assertEquals(420, AnalyticsDailyStat::query()->sum('sessions'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'oauth2.googleapis.com')
            && str_contains($request->body(), 'jwt-bearer'));

        // Analytika se nesčítá s reklamami, jde do vlastní sekce.
        $view = new PerformanceView(ClientPerformance::build($client->fresh(), Period::between('2026-09-28', '2026-09-28')));
        $this->assertSame(0.0, $view->totals->get('purchases'));
        $this->assertSame('Sociální sítě (placené)', collect($view->analytics()['channels'])->firstWhere('sessions', '120')['channel']);
    }

    /*
    |--------------------------------------------------------------------------
    | Hodiny, fakturace, vlastní dashboard
    |--------------------------------------------------------------------------
    */

    public function test_delka_prace_jde_zapsat_lidsky(): void
    {
        $this->assertSame(90, Billing::parseMinutes('1:30'));
        $this->assertSame(90, Billing::parseMinutes('1,5'));
        $this->assertSame(45, Billing::parseMinutes('45m'));
        $this->assertSame(120, Billing::parseMinutes('2 h'));
        $this->assertNull(Billing::parseMinutes('hodně'));
    }

    public function test_fakturace_pausal_a_hodiny_nad_ramec(): void
    {
        $client = $this->klient(['fee_czk' => 3000, 'included_hours' => 2, 'hourly_rate' => 1200]);
        $client->timeEntries()->createMany([
            ['worked_on' => '2026-09-03', 'minutes' => 90, 'description' => 'Kreativy', 'billable' => true],
            ['worked_on' => '2026-09-10', 'minutes' => 90, 'description' => 'Remarketing', 'billable' => true],
            ['worked_on' => '2026-09-11', 'minutes' => 60, 'description' => 'Oprava naší chyby', 'billable' => false],
            ['worked_on' => '2026-08-20', 'minutes' => 600, 'description' => 'Minulý měsíc', 'billable' => true],
        ]);

        $billing = Billing::for($client, now());

        $this->assertEqualsWithDelta(1.0, $billing->extraHours(), 0.001);
        $this->assertEquals(1200, $billing->extraAmount());
        $this->assertEquals(4200, $billing->total());
        $this->assertSame(2, $billing->markInvoiced());
        $this->assertSame(0, Billing::for($client, now())->uninvoicedEntries);
    }

    public function test_vybrane_dlazdice_a_sekce_plati_i_pro_report(): void
    {
        $client = $this->klient(['dashboard' => ['tiles' => ['spend', 'roas'], 'sections' => ['chart']]]);
        $this->dny($client, '2026-09-21', '2026-09-27', ['spend' => 100, 'purchases' => 1, 'purchase_value' => 400]);

        $report = app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));
        $view = new PerformanceView($report->snapshot);

        $this->assertSame(['spend', 'roas'], array_column($view->tiles(), 'key'));
        $this->assertTrue($view->shows('chart'));
        $this->assertFalse($view->shows('campaigns'));

        $report->update(['is_public' => true]);
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/report/'.$report->slug)->assertOk()->assertDontSee('Kampaně');
    }

    public function test_stranky_hodin_a_fakturace_se_nacnou(): void
    {
        $client = $this->klient(['fee_czk' => 3000]);
        $client->timeEntries()->create(['worked_on' => now(), 'minutes' => 45, 'description' => 'Týdenní kontrola', 'billable' => true]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get('/nastroje/reklamy/hodiny')->assertOk()->assertSee('Týdenní kontrola');
        $this->get('/nastroje/reklamy/fakturace')->assertOk()->assertSee('Čajovna Zkouška')->assertSee("3\u{00A0}000\u{00A0}Kč");
    }
}
