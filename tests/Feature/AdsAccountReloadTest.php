<?php

namespace Tests\Feature;

use App\Enums\Ads\AdPlatform;
use App\Enums\UserRole;
use App\Filament\Tools\Pages\Ads\AdsOverview;
use App\Filament\Tools\Pages\Ads\ManageAds;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdDailyStat;
use App\Models\Client;
use App\Models\User;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\Platforms\ApiGuard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nově nasdílený účet hned, ne až druhý den ráno: Načíst účty znovu
 * v Nastavení reklam a první stažení čísel hned po propojení.
 */
class AdsAccountReloadTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> Účty pod MCC, číslo => název. */
    private array $mcc = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 14:00:00');
        config([
            'ads.demo_enabled' => false,
            'ads.meta.token' => null,
            'ads.google_ads' => [
                'client_id' => 'id', 'client_secret' => 'secret', 'refresh_token' => 'refresh',
                'login_customer_id' => '111-222-3333', 'developer_token' => 'dev', 'api_version' => 'v24',
                'daily_call_limit' => 200,
            ],
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');
    }

    protected function tearDown(): void
    {
        ApiGuard::close();

        parent::tearDown();
    }

    /**
     * Google Ads podle dotazu: seznam účtů pod MCC, účet, denní čísla kampaní.
     * Účty se čtou z $this->mcc, ať jdou v průběhu testu změnit.
     */
    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'googleads.googleapis.com/*' => function (Request $request) {
                $accounts = $this->mcc;
                $query = (string) ($request->data()['query'] ?? '');

                return Http::response(['results' => match (true) {
                    str_contains($query, 'FROM customer_client') => collect($accounts)->map(fn (string $name, string $id): array => ['customerClient' => ['id' => $id, 'descriptiveName' => $name, 'currencyCode' => 'CZK', 'status' => 'ENABLED']])->values()->all(),
                    str_contains($query, 'FROM customer ') => [['customer' => ['id' => array_key_first($accounts), 'descriptiveName' => reset($accounts), 'currencyCode' => 'CZK', 'status' => 'ENABLED']]],
                    str_contains($query, "AND '2026-10-25'") => [[
                        'campaign' => ['id' => '9', 'name' => 'PMax'],
                        'segments' => ['date' => '2026-10-25'],
                        'metrics' => ['costMicros' => '50000000', 'impressions' => '400', 'clicks' => '12', 'conversions' => 1, 'conversionsValue' => 900],
                    ]],
                    default => [],
                }]);
            },
        ]);
    }

    public function test_nacist_ucty_znovu_stahne_seznam_hned_i_kdyz_rano_uz_probehl(): void
    {
        $directory = app(AccountDirectory::class);

        // Ráno MCC ještě Snowbear nemělo.
        $this->mcc = ['4445556666' => 'Zrnko'];
        $this->fakeGoogle();
        $this->artisan('ads:sync')->assertSuccessful();
        $this->assertArrayNotHasKey('7778889999', $directory->options(AdPlatform::GoogleAds));

        // Odpoledne ho klient přijal. Tlačítko jen zapíše žádost, na Google nesahá.
        $this->mcc['7778889999'] = 'Snowbear';
        $sent = count(Http::recorded());
        Livewire::test(ManageAds::class)
            ->callAction('reloadAccounts')
            ->assertNotified('Účty se načtou do 5 minut');
        $this->assertCount($sent, Http::recorded());
        $this->assertTrue($directory->refreshPending());

        $this->artisan('ads:sync --requested')->assertSuccessful();

        $this->assertFalse($directory->refreshPending());
        $this->assertSame('Snowbear · 7778889999 · CZK', $directory->options(AdPlatform::GoogleAds)['7778889999']);

        // Bez žádosti --requested nic nevolá, další ruční načtení až za 10 minut.
        $sent = count(Http::recorded());
        $this->artisan('ads:sync --requested')->assertSuccessful();
        $this->assertCount($sent, Http::recorded());
        Livewire::test(ManageAds::class)->callAction('reloadAccounts')->assertNotified('Teď to nejde');
        $this->assertFalse($directory->refreshPending());

        Carbon::setTestNow(now()->addMinutes(11));
        Livewire::test(ManageAds::class)->callAction('reloadAccounts')->assertNotified('Účty se načtou do 5 minut');
    }

    public function test_bez_napojene_platformy_tlacitko_neni(): void
    {
        config(['ads.google_ads.client_id' => null, 'ads.demo_enabled' => true]);

        Livewire::test(ManageAds::class)->assertActionHidden('reloadAccounts');
    }

    public function test_nove_propojeny_ucet_se_stahne_hned_i_s_historii(): void
    {
        $this->mcc = ['7778889999' => 'Snowbear'];
        $this->fakeGoogle();
        app(AccountDirectory::class)->requestRefresh();
        $this->artisan('ads:sync --requested')->assertSuccessful();
        $client = Client::create(['name' => 'Snowbear', 'slug' => 'snowbear']);

        Livewire::test(AdsOverview::class)
            ->callAction('addClient', ['client_id' => $client->id, 'platform' => AdPlatform::GoogleAds->value, 'external_id' => '7778889999', 'primary_goal' => 'purchases'])
            ->assertHasNoActionErrors()
            ->assertNotified('Snowbear je propojený s klientem Snowbear');

        $account = AdAccount::query()->where('external_id', '7778889999')->sole();
        $this->assertNotNull($account->refresh_requested_at);

        $this->artisan('ads:sync --requested')->assertSuccessful();

        $account->refresh();
        $this->assertNull($account->refresh_requested_at);
        $this->assertNull($account->backfill_from);
        $this->assertSame('2026-10-25', $account->data_through->toDateString());
        $this->assertEquals(50, AdDailyStat::query()->where('ad_account_id', $account->id)->sum('spend'));
        // Denní období a pak historie od začátku, kterou ještě nemáme.
        Http::assertSent(fn (Request $request): bool => str_contains((string) ($request->data()['query'] ?? ''), "BETWEEN '".AdAccount::historyStart()."'"));
    }
}
