<?php

namespace Tests\Feature;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\Ads\ReportType;
use App\Enums\UserRole;
use App\Filament\Tools\Pages\Ads\AdsOverview;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdPeriodReach;
use App\Models\Client;
use App\Models\User;
use App\Settings\AdsSettings;
use App\Support\Ads\AccountSync;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\MetricCatalog;
use App\Support\Ads\Metrics;
use App\Support\Ads\PerformanceView;
use App\Support\Ads\Period;
use App\Support\Ads\PeriodReach;
use App\Support\Ads\Platforms\DemoAds;
use App\Support\Ads\Platforms\MetaAds;
use App\Support\Ads\ReportBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Katalog čísel, zobrazení cílové stránky, přesný dosah za období a výběr čísel v přehledu. */
class AdsMetricsTest extends TestCase
{
    use RefreshDatabase;

    private const NBSP = "\u{00A0}";

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 09:00:00');
        config(['ads.meta.token' => 'test-token', 'ads.meta.app_secret' => null]);
    }

    /** @param  array<string, mixed>  $settings */
    private function klient(array $settings = [], PrimaryGoal $goal = PrimaryGoal::Purchases, string $name = 'Čajovna Zkouška'): Client
    {
        $client = Client::create(['name' => $name, 'slug' => str($name)->slug()]);
        $client->adSettings()->create($settings + ['primary_goal' => $goal]);

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

    /**
     * Meta: stav účtu, insights po kampaních a dosah na úrovni účtu
     * pro každé požadované období.
     */
    private function fakeMeta(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (! str_contains($request->url(), '/insights')) {
                return Http::response(['account_id' => '123', 'name' => 'Čajovna (Meta)', 'currency' => 'CZK', 'timezone_name' => 'Europe/Prague', 'account_status' => 1]);
            }

            if (($query['level'] ?? null) === 'account') {
                return Http::response(['data' => array_map(fn (array $range): array => [
                    'reach' => '5000', 'impressions' => '12000', 'frequency' => '2.4', 'unique_inline_link_clicks' => '150',
                    'date_start' => $range['since'], 'date_stop' => $range['until'],
                ], json_decode($query['time_ranges'], true))]);
            }

            return Http::response(['data' => [[
                'campaign_id' => '111', 'campaign_name' => 'Kampaň', 'objective' => 'OUTCOME_SALES',
                'spend' => '100', 'impressions' => '1000', 'reach' => '800', 'clicks' => '30', 'inline_link_clicks' => '20',
                'actions' => [
                    ['action_type' => 'omni_purchase', 'value' => '2'],
                    ['action_type' => 'landing_page_view', 'value' => '15'],
                ],
                'action_values' => [['action_type' => 'omni_purchase', 'value' => '1000']],
                'date_start' => '2026-09-29', 'date_stop' => '2026-09-29',
            ]]]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Poměry
    |--------------------------------------------------------------------------
    */

    public function test_nove_pomery_se_pocitaji_ze_souctu(): void
    {
        $m = Metrics::fromArray([
            'spend' => 1000, 'impressions' => 20000, 'clicks' => 500, 'link_clicks' => 200,
            'landing_page_views' => 160, 'add_to_cart' => 40, 'checkouts' => 20,
            'purchases' => 8, 'purchase_value' => 6400, 'leads' => 4,
        ]);

        $this->assertEqualsWithDelta(2.5, $m->ctrAll(), 0.0001);
        $this->assertEqualsWithDelta(1.0, $m->ctr(), 0.0001);
        $this->assertEqualsWithDelta(25.0, $m->costPerAddToCart(), 0.0001);
        $this->assertEqualsWithDelta(50.0, $m->costPerCheckout(), 0.0001);
        $this->assertEqualsWithDelta(800.0, $m->averageOrderValue(), 0.0001);
        $this->assertEqualsWithDelta(6.25, $m->costPerLandingPageView(), 0.0001);
        $this->assertEqualsWithDelta(80.0, $m->landingPageViewRate(), 0.0001);
        $this->assertEqualsWithDelta(5.0, $m->landingPageConversionRate(PrimaryGoal::Purchases), 0.0001);
        $this->assertEqualsWithDelta(2.5, $m->landingPageConversionRate(PrimaryGoal::Leads), 0.0001);
        $this->assertNull($m->landingPageConversionRate(PrimaryGoal::Traffic));
        $this->assertNull(Metrics::empty()->costPerLandingPageView());

        // Dosah se ze součtů nepočítá, bez přesného čísla za období je null.
        $this->assertNull($m->reach());
        $this->assertNull($m->periodFrequency());
        $this->assertNull($m->uniqueCtr());

        $exact = $m->withPeriodReach(['reach' => 8000, 'impressions' => 20000, 'unique_link_clicks' => 160]);
        $this->assertEqualsWithDelta(8000.0, $exact->reach(), 0.0001);
        $this->assertEqualsWithDelta(2.5, $exact->periodFrequency(), 0.0001);
        $this->assertEqualsWithDelta(2.0, $exact->uniqueCtr(), 0.0001);
        $this->assertFalse($exact->plus(Metrics::empty())->hasPeriodReach());
    }

    public function test_katalog_ma_nazvy_podle_cile_a_cisla_jen_kde_davaji_smysl(): void
    {
        $m = Metrics::fromArray(['spend' => 500, 'purchases' => 5, 'leads' => 10, 'link_clicks' => 50, 'purchase_value' => 2000]);

        $this->assertSame('Nákupy', MetricCatalog::label('conversions', PrimaryGoal::Purchases));
        $this->assertSame('Poptávky', MetricCatalog::label('conversions', PrimaryGoal::Leads));
        $this->assertSame('Cena za poptávku', MetricCatalog::label('cost_per_conversion', PrimaryGoal::Leads));
        $this->assertSame('Konverze', MetricCatalog::label('conversions'));
        $this->assertEqualsWithDelta(50.0, MetricCatalog::value('cost_per_conversion', $m, PrimaryGoal::Leads), 0.0001);
        $this->assertNull(MetricCatalog::value('roas', $m, PrimaryGoal::Leads));
        $this->assertSame('4,00×', MetricCatalog::format('roas', MetricCatalog::value('roas', $m, PrimaryGoal::Purchases)));
        $this->assertSame(-1, MetricCatalog::direction('cost_per_add_to_cart'));
        $this->assertArrayNotHasKey('roas', MetricCatalog::options(PrimaryGoal::Leads));
        $this->assertArrayHasKey('landing_page_views', MetricCatalog::options(PrimaryGoal::Traffic));
    }

    /*
    |--------------------------------------------------------------------------
    | Zobrazení cílové stránky
    |--------------------------------------------------------------------------
    */

    public function test_zobrazeni_cilove_stranky_z_akci_mety(): void
    {
        $stat = app(MetaAds::class)->dailyStat([
            'campaign_id' => '1', 'date_start' => '2026-09-28', 'spend' => '10',
            'actions' => [['action_type' => 'landing_page_view', 'value' => '42'], ['action_type' => 'link_click', 'value' => '50']],
        ]);

        $this->assertSame(42.0, $stat->landingPageViews);
        $this->assertSame(42.0, $stat->columns()['landing_page_views']);
    }

    public function test_migrace_doplni_zobrazeni_cilove_stranky_ze_surovych_akci(): void
    {
        $migration = require database_path('migrations/2026_09_30_185000_add_landing_page_views_and_period_reach.php');
        $migration->down();

        $client = $this->klient();
        $account = $client->adAccounts->first();
        $campaign = $account->campaigns()->first();
        $row = fn (string $date, ?array $raw): array => [
            'ad_account_id' => $account->id, 'ad_campaign_id' => $campaign->id, 'date' => $date,
            'spend' => 10, 'raw' => $raw === null ? null : json_encode($raw), 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('ad_daily_stats')->insert([
            $row('2026-09-01', ['actions' => ['landing_page_view' => 33, 'link_click' => 40], 'action_values' => []]),
            $row('2026-09-02', ['actions' => ['omni_landing_page_view' => 7], 'action_values' => []]),
            $row('2026-09-03', ['conversions' => 1]),
            $row('2026-09-04', null),
        ]);

        $migration->up();

        $this->assertEquals([33, 7, 0, 0], AdDailyStat::query()->orderBy('date')->pluck('landing_page_views')->map(fn ($v): float => (float) $v)->all());
    }

    public function test_synchronizace_ulozi_zobrazeni_cilove_stranky(): void
    {
        $account = $this->klient()->adAccounts->first();
        $this->fakeMeta();

        app(AccountSync::class)->sync($account, Period::between('2026-09-29', '2026-09-29'));

        $this->assertEquals(15, AdDailyStat::query()->sum('landing_page_views'));
        // Bez withReach (doplňování historie) se dosah nestahuje.
        $this->assertSame(0, AdPeriodReach::query()->count());
        Http::assertSentCount(2);
    }

    /*
    |--------------------------------------------------------------------------
    | Přesný dosah za období
    |--------------------------------------------------------------------------
    */

    public function test_ranni_beh_stahne_dosah_za_prednastavena_obdobi_jednim_dotazem(): void
    {
        $client = $this->klient(['dashboard' => ['tiles' => ['reach', 'frequency', 'unique_ctr'], 'sections' => null]]);
        $this->fakeMeta();

        $this->artisan('ads:sync')->assertSuccessful();

        $ranges = PeriodReach::ranges();
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'level=account')
            && str_contains(urldecode($request->url()), '"since":"2026-09-23","until":"2026-09-29"')
            && str_contains($request->url(), 'unique_inline_link_clicks'));
        $this->assertSame(count($ranges), AdPeriodReach::query()->count());

        // Přednastavené období: přesná čísla.
        $tiles = collect((new PerformanceView(ClientPerformance::build($client, Period::preset('7d'))))->tiles())->keyBy('key');
        $this->assertSame('5'.self::NBSP.'000', $tiles['reach']['value']);
        $this->assertSame('2,40', $tiles['frequency']['value']);
        $this->assertSame('3,00'.self::NBSP.'%', $tiles['unique_ctr']['value']);
        $this->assertNull($tiles['reach']['hint']);

        // Vlastní období: pomlčka a vysvětlení.
        $custom = collect((new PerformanceView(ClientPerformance::build($client, Period::between('2026-09-20', '2026-09-26'))))->tiles())->keyBy('key');
        $this->assertSame('–', $custom['reach']['value']);
        $this->assertSame('–', $custom['frequency']['value']);
        $this->assertSame(PeriodReach::UNAVAILABLE_HINT, $custom['reach']['hint']);

        // Další běh řádky přepíše, nezdvojí.
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->artisan('ads:sync')->assertSuccessful();
        $this->assertSame(count($ranges), AdPeriodReach::query()->count());
    }

    public function test_dosah_se_scita_pres_ucty_a_bez_vsech_uctu_neukaze(): void
    {
        $client = $this->klient();
        $second = $client->adAccounts()->create(['platform' => AdPlatform::Meta, 'external_id' => '999', 'name' => 'Druhý', 'is_active' => true]);
        // Google dosah nemá, nepočítá se do účtů, které ho musí mít.
        $client->adAccounts()->create(['platform' => AdPlatform::GoogleAds, 'external_id' => '888', 'name' => 'Google', 'is_active' => true]);
        $client->load('adAccounts');
        $period = Period::preset('7d');
        $ids = PeriodReach::accountIds($client->adAccounts);

        $store = fn (int $accountId, int $reach, int $impressions): AdPeriodReach => AdPeriodReach::query()->create([
            'ad_account_id' => $accountId, 'date_from' => $period->from->toDateString(), 'date_to' => $period->to->toDateString(),
            'reach' => $reach, 'impressions' => $impressions, 'frequency' => $impressions / $reach, 'unique_link_clicks' => 10,
        ]);

        $store($client->adAccounts->first()->id, 1000, 3000);
        $this->assertNull(PeriodReach::total($ids, $period), 'Druhý účet dosah nemá, půlka čísla by mátla.');

        $store($second->id, 3000, 5000);
        $total = PeriodReach::total($ids, $period);
        $this->assertCount(2, $ids);
        $this->assertSame(4000.0, $total['reach']);
        $this->assertEqualsWithDelta(2.0, Metrics::empty()->withPeriodReach($total)->periodFrequency(), 0.0001);
    }

    public function test_ukazkova_platforma_vraci_dosah_mensi_nez_zobrazeni(): void
    {
        $client = Client::create(['name' => 'Demo', 'slug' => 'demo']);
        $account = $client->adAccounts()->create(['platform' => AdPlatform::Demo, 'external_id' => 'demo_listek_meta', 'name' => 'Demo']);

        $stats = app(DemoAds::class)->periodReach($account, [Period::preset('7d'), Period::preset('30d')]);

        $this->assertCount(2, $stats);
        $this->assertGreaterThan(0, $stats[0]->reach);
        $this->assertLessThan($stats[0]->impressions, $stats[0]->reach);
        $this->assertGreaterThan($stats[0]->frequency, $stats[1]->frequency);
    }

    /*
    |--------------------------------------------------------------------------
    | Co ukazovat a staré reporty
    |--------------------------------------------------------------------------
    */

    public function test_bez_vyberu_se_ukaze_vychozi_sada_podle_cile(): void
    {
        $this->assertSame(
            ['spend', 'conversions', 'cost_per_conversion', 'roas', 'purchase_value', 'link_clicks', 'ctr', 'cpm'],
            MetricCatalog::tiles(null, PrimaryGoal::Purchases),
        );
        $this->assertSame(
            ['spend', 'conversions', 'cost_per_conversion', 'conversion_rate', 'link_clicks', 'cpc', 'ctr', 'cpm'],
            MetricCatalog::tiles(null, PrimaryGoal::Leads),
        );

        $client = $this->klient(goal: PrimaryGoal::Traffic);
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20]);
        $view = new PerformanceView(ClientPerformance::build($client, Period::preset('7d')));

        $this->assertSame(['spend', 'conversions', 'cost_per_conversion', 'impressions', 'ctr', 'cpm'], array_column($view->tiles(), 'key'));

        // Uložený výběr ve starých klíčích platí dál, výchozí sada se ukládá jako null.
        $this->assertSame(['cost_per_conversion', 'purchase_value', 'link_clicks'], MetricCatalog::tiles(['value', 'clicks', 'cost'], PrimaryGoal::Purchases));
        $this->assertNull(MetricCatalog::selectionToStore(MetricCatalog::defaults(PrimaryGoal::Leads), PrimaryGoal::Leads));
        $this->assertSame(['spend', 'reach'], MetricCatalog::selectionToStore(['reach', 'spend'], PrimaryGoal::Leads));
    }

    public function test_stary_snimek_reportu_se_dal_zobrazi(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-09-21', '2026-09-27', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20, 'purchases' => 1, 'purchase_value' => 400]);
        $report = app(ReportBuilder::class)->create($client, ReportType::Weekly, Period::week(now()->subWeek()));

        // Tvar snímku z verze 2: bez dosahu za období, bez zobrazení cílové stránky, staré klíče dlaždic.
        $old = $report->snapshot;
        unset($old['period_reach']);
        $old['version'] = 2;
        $old['dashboard'] = ['tiles' => ['spend', 'cost', 'value', 'clicks'], 'sections' => null];
        $strip = function (array $sums): array {
            unset($sums['landing_page_views']);

            return $sums;
        };
        $old['totals'] = $strip($old['totals']);
        $old['previous_totals'] = $strip($old['previous_totals']);
        $old['daily'] = array_map($strip, $old['daily']);
        $report->update(['snapshot' => $old, 'is_public' => true]);

        $view = new PerformanceView($report->refresh()->snapshot);

        $this->assertSame(['spend', 'cost_per_conversion', 'purchase_value', 'link_clicks'], array_column($view->tiles(), 'key'));
        $this->assertNotEmpty($view->funnel());
        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->get('/report/'.$report->slug)
            ->assertOk()
            ->assertSee('Cena za nákup')
            ->assertSee('Hodnota nákupů');
    }

    /*
    |--------------------------------------------------------------------------
    | Přehled klientů
    |--------------------------------------------------------------------------
    */

    public function test_cislo_v_prehledu_jde_vymenit_a_nezname_se_odmitne(): void
    {
        $client = $this->klient(goal: PrimaryGoal::Leads);
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20, 'leads' => 1]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');

        $page = Livewire::test(AdsOverview::class);
        $metrics = collect($page->instance()->cards()->first()['metrics'])->keyBy('key');

        $this->assertSame(MetricCatalog::OVERVIEW_DEFAULT, app(AdsSettings::class)->overview_tiles);
        $this->assertCount(6, $metrics);
        $this->assertSame('Poptávky', $metrics['conversions']['label']);
        // ROAS u poptávek nedává smysl: pomlčka.
        $this->assertSame('–', $metrics['roas']['value']);

        $page->call('setOverviewTile', 3, 'landing_page_views')
            ->call('setOverviewTile', 0, 'nesmysl')
            ->call('setOverviewTile', 6, 'reach')
            ->assertSee('Zobrazení cílové stránky');

        $this->assertSame(['spend', 'conversions', 'cost_per_conversion', 'landing_page_views', 'ctr', 'cpm'], app(AdsSettings::class)->overview_tiles);
    }

    public function test_prehled_ukaze_dosah_jen_kdyz_ho_mame(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20]);
        $settings = app(AdsSettings::class);
        $settings->overview_tiles = ['spend', 'reach', 'frequency', 'unique_ctr', 'ctr', 'cpm'];
        $settings->save();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');

        $metrics = fn (string $period) => collect(Livewire::withQueryParams(['period' => $period])->test(AdsOverview::class)->instance()->cards()->first()['metrics'])->keyBy('key');
        $this->assertSame('–', $metrics('7d')['reach']['value']);

        $period = Period::preset('7d');
        AdPeriodReach::query()->create([
            'ad_account_id' => $client->adAccounts->first()->id, 'date_from' => $period->from->toDateString(), 'date_to' => $period->to->toDateString(),
            'reach' => 2000, 'impressions' => 7000, 'frequency' => 3.5, 'unique_link_clicks' => 40,
        ]);

        $this->assertSame('2'.self::NBSP.'000', $metrics('7d')['reach']['value']);
        $this->assertSame('3,50', $metrics('7d')['frequency']['value']);
        $this->assertSame('2,00'.self::NBSP.'%', $metrics('7d')['unique_ctr']['value']);
    }
}
