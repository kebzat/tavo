<?php

namespace Tests\Feature;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WorkArea;
use App\Filament\Tools\Pages\Agent\Briefings;
use App\Filament\Tools\Pages\Invoicing;
use App\Filament\Tools\Resources\Clients\Pages\EditClient;
use App\Filament\Tools\Resources\Clients\RelationManagers\TaskProposalsRelationManager;
use App\Mail\AdsDailyDigest;
use App\Mail\BillingStatementMail;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdDailyStat;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\Marketing\ClientBriefing;
use App\Models\Marketing\LandingCheck;
use App\Models\User;
use App\Settings\AdsSettings;
use App\Support\Ads\AlertEngine;
use App\Support\Ads\Billing;
use App\Support\Ads\Format;
use App\Support\Ads\Period;
use App\Support\ClientDashboard;
use App\Support\Marketing\BillingStatement;
use App\Support\Marketing\MarketingGuide;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Automatizace marketingu podle dotazníku Pavla, viz docs/MARKETING.md. */
class MarketingTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-agenta-pro-testy-dlouhy-aspon-32-znaku';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 09:00:00');
        config(['services.agent.api_token' => self::TOKEN, 'ads.meta.token' => 'test-token', 'ads.meta.app_secret' => null]);
    }

    /** @param  array<string, mixed>  $settings */
    private function klient(array $settings = [], string $name = 'Čajovna Zkouška', array $attributes = []): Client
    {
        $client = Client::create($attributes + ['name' => $name, 'slug' => str($name)->slug(), 'contact_email' => 'majitel@cajovna.test']);
        $client->adSettings()->create($settings + ['primary_goal' => PrimaryGoal::Purchases]);

        $account = $client->adAccounts()->create([
            'platform' => AdPlatform::Meta,
            'external_id' => (string) random_int(100000, 999999),
            'name' => $name.' (Meta)',
            'status' => 'active',
            'last_synced_at' => now(),
            'data_through' => now()->subDay()->toDateString(),
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
            AdDailyStat::query()->insert([
                'ad_account_id' => $account->id,
                'ad_campaign_id' => $campaign->id,
                'date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ] + $values);
        }
    }

    private function spravce(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Pavel']);
    }

    private function api(): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN];
    }

    /*
    |--------------------------------------------------------------------------
    | Upozornění
    |--------------------------------------------------------------------------
    */

    public function test_nakupy_bez_hodnoty_hlasi_problem_mereni(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-10-14', '2026-10-20', ['spend' => 500, 'impressions' => 5000, 'purchases' => 2, 'purchase_value' => 0]);

        app(AlertEngine::class)->run();

        $alert = $client->adAlerts()->where('rule', 'measurement_value')->sole();
        $this->assertSame(AlertSeverity::Critical, $alert->severity);
        $this->assertStringContainsString('problém měření', $alert->recommendation);
    }

    public function test_vic_nakupu_nez_pokladen_je_podezreni_na_zdvojene_mereni(): void
    {
        $client = $this->klient();
        $this->dny($client, '2026-10-14', '2026-10-20', ['spend' => 500, 'impressions' => 5000, 'purchases' => 3, 'checkouts' => 1, 'purchase_value' => 3000]);

        app(AlertEngine::class)->run();

        $this->assertTrue($client->adAlerts()->where('rule', 'measurement_duplicates')->exists());
    }

    public function test_zamitnute_reklamy_z_ranni_kontroly(): void
    {
        $client = $this->klient();
        $client->adAccounts->first()->update([
            'issues' => [['name' => 'Black Friday video', 'status' => 'DISAPPROVED', 'feedback' => 'Zakázaný obsah']],
            'issues_checked_at' => now(),
        ]);

        app(AlertEngine::class)->run();

        $alert = $client->adAlerts()->where('rule', 'ad_issues')->sole();
        $this->assertSame('Zamítnuté reklamy (1)', $alert->title);
        $this->assertStringContainsString('Black Friday video', $alert->facts);
    }

    public function test_nefunkcni_cilova_stranka_a_chybejici_pixel(): void
    {
        $client = $this->klient(attributes: ['landing_pages' => [['url' => 'https://cajovna.test/akce'], ['url' => 'https://cajovna.test/caj']]]);
        LandingCheck::create(['client_id' => $client->id, 'url' => 'https://cajovna.test/akce', 'status_code' => 500, 'ok' => false, 'error' => 'HTTP 500', 'checked_at' => now()]);
        LandingCheck::create(['client_id' => $client->id, 'url' => 'https://cajovna.test/caj', 'status_code' => 200, 'ok' => true, 'has_meta_pixel' => false, 'checked_at' => now()]);

        app(AlertEngine::class)->run();

        $this->assertSame(AlertSeverity::Critical, $client->adAlerts()->where('title', 'Cílová stránka nefunguje (1)')->sole()->severity);
        $this->assertTrue($client->adAlerts()->where('title', 'Na cílové stránce chybí Meta pixel')->exists());
    }

    public function test_kontrola_cilovych_stranek_jednim_dotazem_na_adresu(): void
    {
        $this->klient(attributes: ['landing_pages' => [['url' => 'https://cajovna.test/akce']], 'website_url' => 'https://cajovna.test']);
        Http::fake([
            'cajovna.test/akce' => Http::response('<html><script src="https://connect.facebook.net/en_US/fbevents.js"></script></html>'),
        ]);

        $this->artisan('marketing:landing-check')->assertSuccessful();

        Http::assertSentCount(1);
        $check = LandingCheck::sole();
        $this->assertTrue($check->ok);
        $this->assertTrue($check->has_meta_pixel);
    }

    public function test_leady_bez_zpetne_vazby_a_levny_lead_neni_uspech(): void
    {
        $client = $this->klient(['primary_goal' => PrimaryGoal::Leads]);
        $this->dny($client, '2026-09-22', '2026-10-20', ['spend' => 100, 'impressions' => 1000, 'leads' => 1]);

        app(AlertEngine::class)->run();
        $this->assertTrue($client->adAlerts()->where('rule', 'lead_feedback')->exists());

        $client->leadFeedback()->create(['period_from' => '2026-10-01', 'period_to' => '2026-10-15', 'leads' => 20, 'qualified' => 6, 'deals' => 1]);
        app(AlertEngine::class)->run();
        $this->assertFalse($client->adAlerts()->live()->where('rule', 'lead_feedback')->exists());
    }

    public function test_vlastni_prahy_ztlumeni_a_nalehavost_klienta(): void
    {
        $settings = app(AdsSettings::class);
        $client = $this->klient(['monthly_budget' => 31000]);
        // Do 20. 10. utraceno 200 Kč denně = 4 000 z očekávaných 20 000: pomalé čerpání.
        $this->dny($client, '2026-10-01', '2026-10-20', ['spend' => 200, 'impressions' => 2000, 'purchases' => 1, 'purchase_value' => 500, 'checkouts' => 1]);

        app(AlertEngine::class)->run();
        $this->assertSame(AlertSeverity::Warning, $client->adAlerts()->where('rule', 'budget_pacing')->sole()->severity);

        // Vysoká naléhavost: Pozor se hlásí jako Hoří.
        $client->adSettings->update(['alert_overrides' => ['urgency' => 'high']]);
        app(AlertEngine::class)->run();
        $this->assertSame(AlertSeverity::Critical, $client->adAlerts()->live()->where('rule', 'budget_pacing')->sole()->severity);

        // Vlastní práh: čerpání pod 10 % u tohoto klienta nevadí.
        $client->adSettings->update(['alert_overrides' => ['budget_under_pct' => 10]]);
        app(AlertEngine::class)->run();
        $this->assertFalse($client->adAlerts()->live()->where('rule', 'budget_pacing')->exists());

        // Ztlumení: výpadek stahování se ztlumit nedá.
        $client->adSettings->update(['alert_overrides' => ['muted' => ['budget_pacing', 'sync_failed']]]);
        $client->adAccounts->first()->update(['last_synced_at' => now()->subDays(5), 'data_through' => now()->subDays(6)->toDateString()]);
        app(AlertEngine::class)->run();
        $this->assertTrue($client->adAlerts()->live()->where('rule', 'sync_failed')->exists());
        $this->assertSame(80, $settings->budget_under_pct);
    }

    public function test_boosting_pocita_interakce_a_nema_pravidla_vykonu(): void
    {
        $client = $this->klient(['primary_goal' => PrimaryGoal::Awareness, 'target_cpa' => 1]);
        $this->dny($client, '2026-10-14', '2026-10-20', ['spend' => 100, 'impressions' => 10000, 'engagements' => 50]);

        app(AlertEngine::class)->run();

        $this->assertFalse($client->adAlerts()->whereIn('rule', ['no_conversions', 'cost_above_target'])->exists());
        $this->assertSame('Interakce', PrimaryGoal::Awareness->conversionLabel());
    }

    public function test_ranni_souhrn_oddeli_nova_upozorneni_od_trvajicich(): void
    {
        $client = $this->klient();
        $base = ['client_id' => $client->id, 'severity' => AlertSeverity::Warning, 'status' => 'open', 'recommendation' => 'x', 'last_seen_on' => today()];
        AdAlert::create($base + ['rule' => 'a', 'fingerprint' => 'a', 'title' => 'Nové dnes', 'detected_on' => today()]);
        AdAlert::create($base + ['rule' => 'b', 'fingerprint' => 'b', 'title' => 'Trvá od pondělí', 'detected_on' => today()->subDays(2)]);
        ClientBriefing::create(['client_id' => $client->id, 'date' => today(), 'body' => 'Přehled']);

        $mail = new AdsDailyDigest(User::factory()->create(), AdAlert::query()->with('client')->get(), collect());

        $this->assertSame('Reklamy: 1 nové upozornění', $mail->envelope()->subject);
        $mail->assertSeeInHtml('Trvá beze změny (1)');
        $mail->assertSeeInHtml('Otevřít ranní přehled');
    }

    /*
    |--------------------------------------------------------------------------
    | Rozhraní pro agenta
    |--------------------------------------------------------------------------
    */

    public function test_bez_tokenu_rozhrani_agenta_neexistuje(): void
    {
        $this->klient();

        $this->getJson('/nastroje/api/agent/klienti')->assertUnauthorized();
        config(['services.agent.api_token' => null]);
        $this->getJson('/nastroje/api/agent/klienti', $this->api())->assertNotFound();
    }

    public function test_agent_dostane_cisla_spocitana_systemem_se_zdrojem(): void
    {
        $client = $this->klient(['monthly_budget' => 10000, 'target_cpa' => 300], attributes: ['profile' => ['goals' => 'Prodej čajů přes e-shop', 'kpis' => 'PNO do 20 %']]);
        $this->dny($client, '2026-10-07', '2026-10-13', ['spend' => 100, 'impressions' => 1000, 'link_clicks' => 20, 'purchases' => 1, 'purchase_value' => 400]);
        $this->dny($client, '2026-10-14', '2026-10-20', ['spend' => 150, 'impressions' => 1000, 'link_clicks' => 20, 'purchases' => 1, 'purchase_value' => 600]);

        $data = $this->getJson('/nastroje/api/agent/klienti/cajovna-zkouska', $this->api())->assertOk()->json();

        $this->assertSame('Prodej čajů přes e-shop', $data['karta']['pole']['Cíle']);
        $week = $data['reklamy']['poslednich_7_dni']['cisla'];
        $this->assertEquals(1050, $week['Útrata']['hodnota']);
        $this->assertSame(Format::change(50.0), $week['Útrata']['zmena']);
        $this->assertStringContainsString('atribuce', mb_strtolower($data['reklamy']['zdroj']));
        $this->assertNotEmpty($data['pravidla_vykladu']);
        $this->assertNull($data['web_ga4']);
    }

    public function test_agent_zapise_prehled_a_navrhy_ukolu_ktere_klient_nevidi(): void
    {
        $client = $this->klient();

        $this->postJson('/nastroje/api/agent/klienti/cajovna-zkouska/prehled', ['body' => "### Co se změnilo\nNic."], $this->api())->assertOk();
        $this->postJson('/nastroje/api/agent/klienti/cajovna-zkouska/prehled', ['body' => 'Druhá verze'], $this->api())->assertOk();
        $this->assertSame('Druhá verze', $client->briefings()->sole()->body);

        $tasks = ['tasks' => [
            ['title' => 'Dodat fotky k Black Friday', 'waiting_for' => 'klient', 'source' => 'schůzka 21. 10.'],
            ['title' => 'Dodat fotky k Black Friday'],
        ]];
        $this->postJson('/nastroje/api/agent/klienti/cajovna-zkouska/ukoly', $tasks, $this->api())->assertOk()->assertJson(['zalozeno' => 1]);
        $this->postJson('/nastroje/api/agent/klienti/cajovna-zkouska/ukoly', $tasks, $this->api())->assertJson(['zalozeno' => 0]);

        $proposal = $client->taskProposals()->sole();
        $this->assertTrue($proposal->is_proposal);
        $this->assertSame(WorkArea::Marketing, $proposal->area);
        $this->assertSame(0, $client->tasks()->count());

        $client->update(['dashboard_enabled' => true]);
        $proposal->update(['status' => TaskStatus::Waiting]);
        $this->assertSame([], ClientDashboard::for($client->fresh())->waiting());
    }

    public function test_agent_posune_schvaleny_ukol_do_hotove_prace(): void
    {
        $client = $this->klient(attributes: ['dashboard_enabled' => true]);
        $task = $client->tasks()->create(['title' => 'Smazat ukázkový obsah', 'area' => WorkArea::Web, 'status' => TaskStatus::InProgress, 'internal_note' => 'Od Toma.']);
        $proposal = $client->taskProposals()->create(['title' => 'Návrh od agenta', 'area' => WorkArea::Web, 'is_proposal' => true]);

        $list = $this->getJson('/nastroje/api/agent/klienti/cajovna-zkouska/ukoly', $this->api())->assertOk()->json();
        $this->assertSame([$task->id], array_column($list['otevrene'], 'id'));
        $this->assertSame($task->id, $this->getJson('/nastroje/api/agent/klienti/cajovna-zkouska', $this->api())->json('ukoly.otevrene.0.id'));

        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}/stav", ['stav' => 'done', 'poznamka' => 'Ověřeno na živém webu.'], $this->api())
            ->assertOk()->assertJson(['stav' => 'done', 'hotovo_dne' => '2026-10-21']);

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame("Od Toma.\n21. 10. 2026, agent: Hotovo. Ověřeno na živém webu.", $task->internal_note);
        $this->assertSame(['Smazat ukázkový obsah'], array_column(ClientDashboard::for($client->fresh())->work(), 'title'));
        $this->assertSame([$task->id], array_column($this->getJson('/nastroje/api/agent/klienti/cajovna-zkouska/ukoly', $this->api())->json('hotove_tento_mesic'), 'id'));

        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}/stav", ['stav' => 'waiting', 'ceka_na' => 'fotky od klienta'], $this->api())->assertOk();
        $this->assertNull($task->fresh()->done_on);
        $this->assertSame('fotky od klienta', $task->fresh()->waiting_for);

        $this->postJson("/nastroje/api/agent/ukoly/{$proposal->id}/stav", ['stav' => 'done'], $this->api())->assertStatus(409);
        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}/stav", ['stav' => 'smazano'], $this->api())->assertUnprocessable();
        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}/stav", ['stav' => 'done'])->assertUnauthorized();
    }

    public function test_agent_zapise_ukol_rovnou_do_planu_a_presune_ho_na_jiny_mesic(): void
    {
        $client = $this->klient(attributes: ['dashboard_enabled' => true]);
        $tom = User::factory()->create();
        $client->tasks()->create(['title' => 'Úklid sitemapy', 'area' => WorkArea::Web, 'status' => TaskStatus::Planned, 'user_id' => $tom->id, 'planned_for' => '2026-11-01']);
        $proposal = $client->taskProposals()->create(['title' => 'Merchant Center', 'area' => WorkArea::Web, 'is_proposal' => true]);

        $tasks = ['schvaleno' => true, 'tasks' => [
            ['title' => 'Dotaz na Upgates', 'area' => 'web', 'v_planu_na' => '2026-11'],
            ['title' => 'Přehled za říjen', 'area' => 'web'],
            ['title' => 'úklid sitemapy'],
        ]];
        $ids = $this->postJson('/nastroje/api/agent/klienti/cajovna-zkouska/ukoly', $tasks, $this->api())->assertOk()->assertJson(['zalozeno' => 2])->json('ids');

        $task = ClientTask::find($ids[0]);
        $this->assertFalse($task->is_proposal);
        $this->assertSame('2026-11-01', $task->planned_for->toDateString());
        $this->assertSame($tom->id, $task->user_id);
        $this->assertSame('2026-10-01', ClientTask::find($ids[1])->planned_for->toDateString());
        $this->assertSame(['Přehled za říjen'], array_column(ClientDashboard::for($client->fresh())->plan()[0]['tasks'], 'title'));

        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}", ['v_planu_na' => '2026-10', 'termin' => '2026-10-31'], $this->api())
            ->assertOk()->assertJson(['v_planu_na' => '2026-10', 'termin' => '2026-10-31', 'navrh' => false]);
        $this->assertStringContainsString('agent: v plánu na 2026-10, termín 2026-10-31.', $task->fresh()->internal_note);

        $this->assertSame([$proposal->id], array_column($this->getJson('/nastroje/api/agent/klienti/cajovna-zkouska/ukoly', $this->api())->json('navrhy'), 'id'));
        $this->postJson("/nastroje/api/agent/ukoly/{$proposal->id}", ['schvalit' => true], $this->api())->assertOk()->assertJson(['navrh' => false, 'v_planu_na' => '2026-10']);
        $this->assertSame($tom->id, $proposal->fresh()->user_id);

        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}", ['nazev' => 'Dotaz na Heureku', 'popis' => 'Zeptám se Heureky.'], $this->api())
            ->assertOk()->assertJson(['nazev' => 'Dotaz na Heureku']);
        $this->assertSame('<p>Zeptám se Heureky.</p>', $task->fresh()->description);
        $this->assertStringContainsString('agent: nový název, nový popis.', $task->fresh()->internal_note);

        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}", [], $this->api())->assertUnprocessable();
        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}", ['v_planu_na' => 'listopad'], $this->api())->assertUnprocessable();
        $this->postJson("/nastroje/api/agent/ukoly/{$task->id}", ['v_planu_na' => '2026-10'])->assertUnauthorized();
    }

    public function test_agent_prida_rozhodnuti_a_doplni_jen_prazdna_pole_konceptu_reportu(): void
    {
        $client = $this->klient();

        $this->postJson('/nastroje/api/agent/klienti/cajovna-zkouska/rozhodnuti', ['decision' => 'Black Friday od 20. 11.', 'source' => 'schůzka 21. 10.'], $this->api())->assertOk();
        $this->assertSame('Black Friday od 20. 11.', $client->fresh()->decisions[0]['decision']);

        $report = $client->adReports()->create([
            'type' => 'weekly', 'period_start' => '2026-10-13', 'period_end' => '2026-10-19', 'title' => 'Týden',
            'snapshot' => [], 'status' => 'draft', 'causes' => 'Pavel už napsal', 'public_token' => str_repeat('a', 40),
        ]);

        $this->dny($client, '2026-10-13', '2026-10-19', ['spend' => 100, 'impressions' => 1000, 'purchases' => 1, 'purchase_value' => 400]);
        $draft = $this->getJson('/nastroje/api/agent/klienti/cajovna-zkouska', $this->api())->json('reporty_k_doplneni.0');
        $this->assertSame($report->id, $draft['id']);
        $this->assertEquals(700, $draft['cisla']['Útrata']['hodnota']);

        $this->postJson("/nastroje/api/agent/reporty/{$report->id}", ['facts' => 'Útrata 1 050 Kč', 'causes' => 'Přepsat?'], $this->api())->assertOk();
        $this->assertSame('Útrata 1 050 Kč', $report->fresh()->facts);
        $this->assertSame('Pavel už napsal', $report->fresh()->causes);

        $report->update(['status' => 'sent']);
        $this->postJson("/nastroje/api/agent/reporty/{$report->id}", ['facts' => 'x'], $this->api())->assertStatus(409);
    }

    /*
    |--------------------------------------------------------------------------
    | Administrace
    |--------------------------------------------------------------------------
    */

    public function test_karta_klienta_se_ulozi_z_formulare(): void
    {
        $client = $this->klient();

        Livewire::actingAs($this->spravce())
            ->test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm([
                'profile.goals' => 'Prodej přes e-shop',
                'profile.brand_voice' => 'Tykáme, bez slova „levný“',
                'landing_pages' => [['url' => 'https://cajovna.test/akce', 'note' => 'BF']],
                'billing_statement.mode' => 'email',
                'billing_statement.detail' => 'entries',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertSame('Prodej přes e-shop', $client->profileFields()['Cíle']);
        $this->assertSame(['https://cajovna.test/akce'], $client->landingUrls());
        $this->assertSame('entries', $client->billing_statement['detail']);
    }

    public function test_schvaleni_navrhu_udela_bezny_ukol(): void
    {
        $client = $this->klient();
        $proposal = $client->taskProposals()->create(['title' => 'Návrh', 'area' => 'marketing', 'status' => 'planned', 'is_proposal' => true]);

        Livewire::actingAs($this->spravce())
            ->test(TaskProposalsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->callTableAction('approve', $proposal);

        $this->assertFalse($proposal->fresh()->is_proposal);
        $this->assertSame(1, $client->tasks()->count());
    }

    public function test_ranni_prehled_ukaze_priority_navrhy_a_prehledy(): void
    {
        $client = $this->klient();
        $user = $this->spravce();
        $client->tasks()->create(['title' => 'Po termínu', 'area' => 'marketing', 'status' => 'planned', 'due_on' => '2026-10-19', 'user_id' => $user->id]);
        $client->taskProposals()->create(['title' => 'Návrh', 'area' => 'marketing', 'status' => 'planned', 'is_proposal' => true]);
        ClientBriefing::create(['client_id' => $client->id, 'date' => today(), 'body' => "### Co se změnilo\nÚtrata **+50 %**."]);

        Livewire::actingAs($user)
            ->test(Briefings::class)
            ->assertOk()
            ->assertSee('Po termínu')
            ->assertSee('po termínu 19. 10.')
            ->assertSee('Čajovna Zkouška')
            ->assertSeeHtml('<strong>+50 %</strong>');
    }

    public function test_vyuctovani_podle_prani_klienta_bez_internich_popisu(): void
    {
        Mail::fake();
        $client = $this->klient(['fee_czk' => 5000, 'included_hours' => 2, 'hourly_rate' => 1000], attributes: [
            'billing_statement' => ['mode' => 'email', 'detail' => 'tasks', 'recipients' => 'ucetni@cajovna.test'],
        ]);
        $task = $client->tasks()->create(['title' => 'Kampaň Black Friday', 'area' => 'marketing', 'status' => 'in_progress']);
        $client->timeEntries()->create(['task_id' => $task->id, 'worked_on' => '2026-09-10', 'minutes' => 180, 'description' => 'interní: hádka s grafikem', 'billable' => true]);

        $statement = BillingStatement::for(Billing::for($client->fresh(), Carbon::parse('2026-09-01'), WorkArea::Marketing));

        $this->assertStringContainsString('Kampaň Black Friday: 3:00', $statement->text());
        $this->assertStringNotContainsString('hádka', $statement->text());
        $this->assertSame(['ucetni@cajovna.test'], $statement->recipients());

        Livewire::actingAs($this->spravce())
            ->test(Invoicing::class)
            ->set('month', '2026-09')
            ->callAction('statement', ['recipients' => 'ucetni@cajovna.test', 'body' => $statement->text()], ['client' => $client->id, 'area' => 'marketing']);

        Mail::assertSent(BillingStatementMail::class, fn (BillingStatementMail $mail): bool => $mail->hasTo('ucetni@cajovna.test'));
    }

    public function test_pruvodce_pro_pavla_ma_vsechny_obrazky_a_neni_ve_vyhledavacich(): void
    {
        $guide = MarketingGuide::content();

        $this->get('/pruvodce/'.MarketingGuide::SLUG)
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee($guide['agent']['title'])
            ->assertSee($guide['agent']['news'][0]['title'])
            ->assertSee($guide['features'][0]['title']);

        foreach ($guide['features'] as $feature) {
            $this->assertFileExists(public_path(ltrim($feature['src'], '/')));
            $this->assertGreaterThan(0, $feature['width']);
        }

        foreach ($guide['agent']['news'] as $news) {
            foreach ($news['images'] ?? [] as $image) {
                $this->assertFileExists(public_path(ltrim($image['src'], '/')));
                $this->assertGreaterThan(0, $image['width']);
            }
        }

        $this->get('/pruvodce/marketing')->assertNotFound();
    }
}
