<?php

namespace Tests\Feature;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\UserRole;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Pages\Ads\AdsOverview;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdDailyStat;
use App\Models\Client;
use App\Models\User;
use App\Support\Ads\AdsScope;
use App\Support\Ads\AlertEngine;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\ClientPerformance;
use App\Support\Ads\CsvExport;
use App\Support\Ads\DataStatus;
use App\Support\Ads\PerformanceView;
use App\Support\Ads\Period;
use App\Support\Ads\PeriodReach;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\Platforms\MetaAds;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Čemu z čísel věřit (zpětná vazba Pavla, bod 1 a 3): do kdy jsou data
 * kompletní, nula není chybějící den, odhady a srovnání jen z kompletních
 * dní, doplnění děr, ruční obnova, export a nová období.
 */
class AdsReliabilityTest extends TestCase
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

    /** @param  array<string, mixed>  $settings */
    private function klient(array $settings = [], ?string $through = '2026-09-29'): Client
    {
        $client = Client::create(['name' => 'Čajovna Zkouška', 'slug' => 'cajovna-zkouska']);
        $client->adSettings()->create($settings + ['primary_goal' => PrimaryGoal::Purchases]);

        $account = $client->adAccounts()->create([
            'platform' => AdPlatform::Meta,
            'external_id' => '123456',
            'name' => 'Čajovna (Meta)',
            'status' => 'active',
            'last_synced_at' => now()->subDay(),
            'data_through' => $through,
        ]);
        $account->campaigns()->create(['external_id' => '1', 'name' => 'Prospecting']);

        return $client->load(['adAccounts', 'adSettings']);
    }

    /** @param  array<string, mixed>  $values */
    private function dny(Client $client, string $from, string $to, array $values): void
    {
        $account = $client->adAccounts->first();
        $campaign = $account->campaigns()->first();

        foreach (Period::between($from, $to)->dates() as $date) {
            AdDailyStat::query()->insert(['ad_account_id' => $account->id, 'ad_campaign_id' => $campaign->id, 'date' => $date, 'created_at' => now(), 'updated_at' => now()] + $values);
        }
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');
    }

    public function test_stav_dat_rika_do_kdy_jsou_cisla_kompletni(): void
    {
        $ok = DataStatus::for($this->klient()->adAccounts);

        $this->assertSame(DataStatus::OK, $ok->state);
        $this->assertSame('Data do 29. 9.', $ok->badge());
        $this->assertSame('settling', $ok->dayState('2026-09-28'));
        $this->assertSame('ok', $ok->dayState('2026-09-20'));
        $this->assertSame('missing', $ok->dayState('2026-09-30'));

        Client::query()->delete();
        $stale = DataStatus::for($this->klient(through: '2026-09-26')->adAccounts);
        $this->assertSame(DataStatus::STALE, $stale->state);
        $this->assertSame(3, $stale->staleDays());
        $this->assertSame(3, $stale->missingDays(Period::preset('7d')));
        $this->assertStringContainsString('neznamená, že se nic neutratilo', $stale->sentence());
    }

    public function test_chybejici_dny_nejsou_nula_a_srovnani_se_neukaze(): void
    {
        $client = $this->klient(through: '2026-09-26');
        $this->dny($client, '2026-09-16', '2026-09-26', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20, 'purchases' => 1, 'purchase_value' => 500]);

        $view = new PerformanceView(ClientPerformance::build($client, Period::preset('7d')));
        $tiles = collect($view->tiles())->keyBy('key');
        $chart = $view->chart();

        // Bez dat za 27.–29. 9. by útrata vyšla jako propad o 43 %. Nesmí.
        $this->assertNull($tiles['spend']['change']);
        $this->assertSame('–', $tiles['spend']['previous']);
        $this->assertStringContainsString('chybí', $view->dataNote());
        // Poslední tři stažené dny Meta ještě dopočítává, další tři chybí.
        $this->assertSame(['ok', 'settling', 'settling', 'settling', 'missing', 'missing', 'missing'], $chart['states']);
    }

    public function test_rozpocet_a_odhad_jen_z_kompletnich_dni(): void
    {
        $client = $this->klient(['monthly_budget' => 3000], through: '2026-09-19');
        $this->dny($client, '2026-09-01', '2026-09-19', ['spend' => 100]);

        $naive = BudgetPace::for($client->activeAdAccountIds(), 3000);
        $pace = BudgetPace::for($client->activeAdAccountIds(), 3000, null, DataStatus::for($client->adAccounts)->completeThrough);

        // Naivně: 1 900 Kč za 29 dní, „čerpá se pomalu“. Ve skutečnosti 100 Kč denně = přesně podle plánu.
        $this->assertLessThan(70, $naive->pacePercent());
        $this->assertTrue($pace->partial);
        $this->assertSame(19, $pace->daysElapsed);
        $this->assertEqualsWithDelta(100, $pace->pacePercent(), 0.01);
        $this->assertEqualsWithDelta(3000, $pace->projected(), 0.01);
        $this->assertStringContainsString('19. 9.', $pace->note());
    }

    public function test_pri_neuplnych_datech_nesviti_vykon_a_nic_se_nezavre(): void
    {
        $client = $this->klient(['monthly_budget' => 30000]);
        $this->dny($client, '2026-09-01', '2026-09-29', ['spend' => 100, 'impressions' => 1000]);
        app(AlertEngine::class)->run();
        $pacing = $client->adAlerts()->where('rule', 'budget_pacing')->sole();

        // Výpadek stahování: data jen do 25. 9.
        $client->adAccounts->first()->update(['data_through' => '2026-09-25', 'last_synced_at' => now()->subDays(5)]);
        app(AlertEngine::class)->run();

        $this->assertTrue($client->adAlerts()->live()->where('rule', 'sync_failed')->exists());
        // Upozornění na čerpání zůstává, jak bylo: nezavře se jako „vyřešené“ z neúplných dat.
        $this->assertTrue($pacing->refresh()->status->value === 'open');
        $this->assertSame(1, $client->adAlerts()->where('rule', 'budget_pacing')->count());
    }

    public function test_nula_za_stazene_obdobi_je_skutecna_nula(): void
    {
        $client = $this->klient();
        $this->admin();

        Livewire::test(AdsClient::class, ['client' => $client])
            ->assertSee('kampaně v něm neběžely');

        $client->adAccounts->first()->update(['data_through' => null]);
        Livewire::test(AdsClient::class, ['client' => $client->fresh()])
            ->assertSee('ještě nestahoval');
    }

    public function test_ranni_stazeni_doplni_dny_od_posledniho_kompletniho(): void
    {
        ApiGuard::openForSync();
        $client = $this->klient(through: '2026-09-10');
        Http::fake(['graph.facebook.com/*/insights*' => Http::response(['data' => []]), 'graph.facebook.com/*' => Http::response(['account_id' => '123456', 'name' => 'Čajovna (Meta)', 'currency' => 'CZK', 'account_status' => 1, 'data' => []])]);

        $this->artisan('ads:sync')->assertSuccessful();

        $run = $client->adAccounts->first()->syncRuns()->latest('id')->first();
        $this->assertSame('2026-09-11', $run->date_from->toDateString());
        $this->assertSame('2026-09-29', $run->date_to->toDateString());
        $this->assertSame('2026-09-29', $client->adAccounts->first()->refresh()->data_through->toDateString());
    }

    public function test_rucni_obnova_jen_zapise_zadost_a_vyridi_ji_stazeni(): void
    {
        $client = $this->klient();
        $this->admin();
        Http::fake();

        Livewire::test(AdsClient::class, ['client' => $client])->callAction('refresh');
        Http::assertNothingSent();
        $account = $client->adAccounts->first()->refresh();
        $this->assertNotNull($account->refresh_requested_at);

        // Bez žádosti stažení nic nedělá, se žádostí stáhne účet a přepočítá upozornění.
        Http::fake(['graph.facebook.com/*/insights*' => Http::response(['data' => []]), 'graph.facebook.com/*' => Http::response(['account_id' => '123456', 'name' => 'Čajovna (Meta)', 'currency' => 'CZK', 'account_status' => 1, 'data' => []])]);
        $this->artisan('ads:sync --requested')->assertSuccessful();
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'insights'));
        $this->assertNull($account->refresh()->refresh_requested_at);
        $this->assertNotNull($account->refreshed_at);

        // Do dvou hodin znovu nejde.
        Livewire::test(AdsClient::class, ['client' => $client])->callAction('refresh');
        $this->assertNull($account->refresh()->refresh_requested_at);
    }

    public function test_bez_zadosti_obnova_nic_nevola(): void
    {
        $this->klient();
        Http::fake();

        $this->artisan('ads:sync --requested')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_nova_obdobi_a_srovnani_s_minulym_tydnem(): void
    {
        $today = CarbonImmutable::parse('2026-09-30');

        $this->assertSame(['from' => '2026-09-29', 'to' => '2026-09-29'], Period::preset('yesterday', $today)->toArray());
        $this->assertSame(['from' => '2026-09-28', 'to' => '2026-09-28'], Period::preset('day_before', $today)->toArray());
        $this->assertSame(['from' => '2026-09-28', 'to' => '2026-09-29'], Period::preset('2d', $today)->toArray());
        // Včerejšek s předevčírem, nebo se stejným dnem minulého týdne.
        $this->assertSame(['from' => '2026-09-28', 'to' => '2026-09-28'], Period::preset('yesterday', $today)->comparison('previous')->toArray());
        $this->assertSame(['from' => '2026-09-22', 'to' => '2026-09-22'], Period::preset('yesterday', $today)->comparison('week_ago')->toArray());
        // Tento měsíc se stejně dlouhou částí minulého.
        $this->assertSame(['from' => '2026-08-01', 'to' => '2026-08-29'], Period::preset('this_month', $today)->comparison('previous')->toArray());
        // Dosah se stahuje i pro nová období a obě srovnání.
        $ranges = collect(PeriodReach::ranges($today))->map(fn (Period $period): string => $period->from->toDateString().'|'.$period->to->toDateString());
        $this->assertContains('2026-09-22|2026-09-22', $ranges);
        $this->assertContains('2026-09-28|2026-09-29', $ranges);
    }

    public function test_prehled_ukaze_ranni_souhrn_a_stav_dat(): void
    {
        $client = $this->klient(through: '2026-09-25');
        $this->dny($client, '2026-09-20', '2026-09-25', ['spend' => 100, 'impressions' => 1000]);
        app(AlertEngine::class)->run();
        $this->admin();

        $page = Livewire::test(AdsOverview::class)
            ->assertSee('Dnes ráno:')
            ->assertSee('1 problém s daty')
            ->assertSee('Data jen do 25. 9.');
        $card = $page->instance()->cards()->first();

        $this->assertFalse($card['covered']);
        $this->assertNull($card['metrics'][0]['change']);
    }

    public function test_export_do_csv_pocita_pomery_ze_souctu(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-09-28', '2026-09-28', ['spend' => 100, 'impressions' => 100, 'link_clicks' => 10, 'purchases' => 1, 'purchase_value' => 400]);
        $this->dny($client, '2026-09-29', '2026-09-29', ['spend' => 300, 'impressions' => 900, 'link_clicks' => 10, 'purchases' => 1, 'purchase_value' => 400]);

        $rows = (new CsvExport(new AdsScope($client), Period::preset('2d'), 'campaigns'))->rows();
        $head = array_flip($rows[0]);

        $this->assertCount(2, $rows);
        $this->assertSame('Prospecting', $rows[1][$head['Kampaň']]);
        $this->assertSame('400,00', $rows[1][$head['Útrata']]);
        // CTR 20 / 1000 = 2 %, ne průměr denních 10 % a 1,1 %.
        $this->assertSame('2,00', $rows[1][$head['CTR odkazu %']]);
        $this->assertSame('200,00', $rows[1][$head['Cena za nákup']]);
        $this->assertSame(3, count((new CsvExport(new AdsScope($client), Period::preset('2d')))->rows()));
    }

    public function test_poptavky_se_rozpadnou_na_formular_a_web(): void
    {
        $stat = app(MetaAds::class)->dailyStat([
            'campaign_id' => '1', 'date_start' => '2026-09-29', 'spend' => '100',
            'actions' => [
                ['action_type' => 'lead', 'value' => '5'],
                ['action_type' => 'onsite_conversion.lead_grouped', 'value' => '3'],
                ['action_type' => 'offsite_conversion.fb_pixel_lead', 'value' => '2'],
            ],
        ]);

        $this->assertSame(5.0, $stat->leads);
        $this->assertSame(3.0, $stat->formLeads);
        $this->assertSame(2.0, $stat->webLeads);
    }

    public function test_pixel_neupozornuje_kdyz_meta_udalosti_z_webu_dostava(): void
    {
        $client = $this->klient();
        $client->update(['website_url' => 'https://cajovna.test']);
        $client->landingChecks()->create(['url' => 'https://cajovna.test', 'status_code' => 200, 'ok' => true, 'has_meta_pixel' => false, 'checked_at' => now()]);
        $this->dny($client, '2026-09-23', '2026-09-29', ['spend' => 100, 'impressions' => 1000]);

        app(AlertEngine::class)->run();
        $this->assertTrue(AdAlert::query()->where('rule', 'landing_pixel')->exists());

        // Pixel jede přes GTM: Meta dostává události z webu, upozornění by bylo falešné a zavře se.
        AdDailyStat::query()->update(['landing_page_views' => 30, 'purchases' => 1]);
        app(AlertEngine::class)->run();
        $this->assertFalse(AdAlert::query()->live()->where('rule', 'landing_pixel')->exists());
    }
}
