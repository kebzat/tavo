<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\WorkArea;
use App\Filament\Tools\Actions\Ads\LogTimeAction;
use App\Filament\Tools\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use App\Support\Ads\Billing;
use App\Support\ClientDashboard;
use App\Support\ClientDashboardDemo;
use App\Support\RetainerSchedule;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00');
    }

    private function klient(): Client
    {
        $client = Client::create(['name' => 'Bylinky Zkouška', 'slug' => 'bylinky-zkouska', 'started_on' => '2026-09-01', 'dashboard_enabled' => true]);
        $client->retainers()->createMany([
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'order_column' => 1],
            ['area' => WorkArea::Marketing, 'label' => 'Marketing', 'monthly_fee' => 5000, 'order_column' => 2],
        ]);

        return $client;
    }

    private function url(Client $client, array $query = []): string
    {
        return $client->dashboardPreviewUrl().($query ? '?'.http_build_query($query) : '');
    }

    public function test_ceska_louka_je_zalozena_s_rozdelenym_pausalem(): void
    {
        $client = Client::where('slug', 'ceska-louka')->firstOrFail();

        $this->assertSame('https://www.ceskalouka.cz', $client->website_url);
        $this->assertFalse($client->dashboard_enabled);
        $this->assertSame([['web', 10000], ['marketing', 5000]], $client->retainers->map(fn ($r) => [$r->area->value, $r->monthly_fee])->all());
        $this->assertEquals(15000, Billing::for($client, now())->total());
    }

    public function test_vypnuty_prehled_vidi_jen_prihlaseny(): void
    {
        $client = $this->klient();
        $client->update(['dashboard_enabled' => false]);

        $this->get($this->url($client))->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get($this->url($client))->assertOk()->assertSee('Náhled. Klient přehled zatím nevidí');
    }

    public function test_klient_vidi_hodiny_po_ukolech_a_oblastech(): void
    {
        $client = $this->klient();
        $tom = User::factory()->create(['name' => 'Tom']);
        $pavel = User::factory()->create(['name' => 'Pavel']);

        $speed = $client->tasks()->create(['title' => 'Rychlejší mobilní web', 'description' => 'Úvodní stránka se načítala přes 6 sekund.', 'area' => WorkArea::Web, 'status' => TaskStatus::Done, 'internal_note' => 'TAJNÁ POZNÁMKA']);
        $ads = $client->tasks()->create(['title' => 'Nové kampaně na podzim', 'area' => WorkArea::Marketing, 'status' => TaskStatus::InProgress]);

        $client->timeEntries()->createMany([
            ['task_id' => $speed->id, 'user_id' => $tom->id, 'worked_on' => '2026-10-02', 'minutes' => 150, 'description' => 'Interní popis zápisu', 'billable' => true],
            ['task_id' => $ads->id, 'user_id' => $pavel->id, 'worked_on' => '2026-10-05', 'minutes' => 60, 'description' => 'Kreativy', 'billable' => true],
            ['area' => WorkArea::Web, 'user_id' => $tom->id, 'worked_on' => '2026-10-06', 'minutes' => 30, 'description' => 'Telefonát', 'billable' => true],
            ['task_id' => $speed->id, 'user_id' => $tom->id, 'worked_on' => '2026-10-07', 'minutes' => 120, 'description' => 'Oprava naší chyby', 'billable' => false],
            ['task_id' => $speed->id, 'user_id' => $tom->id, 'worked_on' => '2026-09-20', 'minutes' => 60, 'description' => 'Minulý měsíc', 'billable' => true],
        ]);

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get($this->url($client))
            ->assertOk()
            ->assertSee('Říjen 2026')
            ->assertSee("4\u{00A0}h", false)                 // 2,5 + 1 + 0,5, bez nefakturované opravy
            ->assertSee("15\u{00A0}000\u{00A0}Kč", false)
            ->assertSeeInOrder(['Vývoj webu', "10\u{00A0}000\u{00A0}Kč", "3\u{00A0}h", 'Marketing', "5\u{00A0}000\u{00A0}Kč", "1\u{00A0}h"], false)
            ->assertSee('Rychlejší mobilní web')
            ->assertSee('Úvodní stránka se načítala přes 6 sekund.')
            ->assertSee("2,5\u{00A0}h", false)
            ->assertSee('Komunikace, konzultace a drobné úpravy')
            ->assertSee('Pavel')
            ->assertDontSee('TAJNÁ POZNÁMKA')
            ->assertDontSee('Interní popis zápisu')
            ->assertDontSee('Oprava naší chyby');
    }

    public function test_ceka_na_vas_a_plan_po_mesicich(): void
    {
        $client = $this->klient();
        $client->tasks()->createMany([
            ['title' => 'Poslat fotky produktů', 'area' => WorkArea::Web, 'status' => TaskStatus::Waiting],
            ['title' => 'Zpožděný úkol ze září', 'area' => WorkArea::Web, 'status' => TaskStatus::Planned, 'planned_for' => '2026-09-01'],
            ['title' => 'Blog o pěstování', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Planned, 'planned_for' => '2026-12-01'],
            ['title' => 'Nápad bez termínu', 'area' => WorkArea::Web, 'status' => TaskStatus::Planned],
            ['title' => 'Rozpracované bez termínu', 'area' => WorkArea::Web, 'status' => TaskStatus::InProgress],
        ]);
        $worked = $client->tasks()->create(['title' => 'Už se na tom dělá', 'area' => WorkArea::Web, 'status' => TaskStatus::InProgress]);
        $client->timeEntries()->create(['task_id' => $worked->id, 'worked_on' => '2026-10-02', 'minutes' => 60, 'description' => 'x', 'billable' => true]);

        $this->get($this->url($client))
            ->assertOk()
            ->assertSee('Čeká na vás')
            ->assertSee('Poslat fotky produktů')
            ->assertSeeInOrder(['Co jsme udělali', 'Už se na tom dělá', 'Co následuje', 'Říjen 2026', 'Rozpracované bez termínu', 'Zpožděný úkol ze září', 'Prosinec 2026', 'Blog o pěstování', 'Později', 'Nápad bez termínu']);

        // Minulý měsíc ukazuje jen tehdejší práci, ne dnešní plán.
        $this->get($this->url($client, ['mesic' => '2026-09']))
            ->assertOk()
            ->assertSee('Září 2026')
            ->assertSee('Vyberte měsíc')
            ->assertSee('aria-current="page"', false)
            ->assertSee('mesic=2026-10', false)
            ->assertDontSee('Poslat fotky produktů')
            ->assertDontSee('Co následuje');
    }

    public function test_starsi_mesice_jsou_v_seznamu(): void
    {
        $client = $this->klient();
        $client->update(['started_on' => '2025-07-01']);

        $months = ClientDashboard::for($client->fresh(), '2025-09')->months();

        $this->assertSame(['Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen'], array_column($months['recent'], 'label'));
        $this->assertSame('Duben 2026', $months['older'][0]['label']);
        $this->assertSame('Červenec 2025', end($months['older'])['label']);
        $this->assertTrue($months['older_active']);

        $this->get($this->url($client, ['mesic' => '2025-09']))
            ->assertOk()
            ->assertSee('Starší měsíce')
            ->assertSee('<option value="2025-09" selected>Září 2025</option>', false);
    }

    public function test_mesic_mimo_spolupraci_spadne_do_rozsahu(): void
    {
        $client = $this->klient();

        $this->get($this->url($client, ['mesic' => '2025-01']))->assertOk()->assertSee('Září 2026');
        $this->get($this->url($client, ['mesic' => '2027-05']))->assertOk()->assertSee('Říjen 2026');
    }

    public function test_cil_a_komentar_mesice(): void
    {
        $client = $this->klient();
        $client->months()->create(['month' => '2026-10-14', 'goal' => 'Zrychlit web', 'summary' => "Zjistili jsme, že **košík** padá.\n\n<script>alert(1)</script>"]);

        $this->get($this->url($client))
            ->assertOk()
            ->assertSee('Zrychlit web')
            ->assertSee('<strong>košík</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_pausal_bez_hodin_nic_neuctuje_navic(): void
    {
        $client = $this->klient();
        $client->timeEntries()->create(['worked_on' => '2026-10-02', 'minutes' => 900, 'description' => 'Hodně práce', 'billable' => true]);

        $billing = Billing::for($client, now());
        $this->assertEquals(15000, $billing->fee);
        $this->assertEquals(0, $billing->extraHours());
        $this->assertEquals(15000, $billing->total());

        $client->retainers()->first()->update(['included_hours' => 8]);
        $billing = Billing::for($client->fresh(), now());
        $this->assertEqualsWithDelta(7.0, $billing->extraHours(), 0.001);
    }

    public function test_zapis_casu_bere_oblast_z_ukolu(): void
    {
        $client = $this->klient();
        $task = $client->tasks()->create(['title' => 'Kampaně', 'area' => WorkArea::Marketing]);

        $entry = LogTimeAction::create(['worked_on' => '2026-10-02', 'duration' => '1:30', 'task_id' => $task->id, 'area' => 'web', 'description' => 'x'], $client);

        $this->assertSame(WorkArea::Marketing, $entry->area);
        $this->assertSame(90, $entry->minutes);
    }

    public function test_hotovy_ukol_dostane_datum_a_navratem_ho_ztrati(): void
    {
        $task = $this->klient()->tasks()->create(['title' => 'Úkol', 'area' => WorkArea::Web, 'planned_for' => '2026-11-17']);
        $this->assertSame('2026-11-01', $task->planned_for->toDateString());

        $task->update(['status' => TaskStatus::Done]);
        $this->assertSame('2026-10-20', $task->done_on->toDateString());

        $task->update(['status' => TaskStatus::InProgress]);
        $this->assertNull($task->fresh()->done_on);
    }

    public function test_prehled_nepatri_do_vyhledavacu(): void
    {
        $this->get($this->url($this->klient()))->assertSee('noindex', false);
        $this->get('/robots.txt')->assertSee('Disallow: /klient');
    }

    public function test_ukazka_se_obnovi_k_aktualnimu_mesici_a_odkaz_zustane(): void
    {
        $demo = app(ClientDashboardDemo::class);
        $url = $demo->install();   // migrace ji už založila, tohle ji obnoví
        $client = $demo->client();

        $this->assertSame(1, Client::where('name', ClientDashboardDemo::NAME)->count());
        $this->assertSame(0, $client->timeEntries()->where('worked_on', '>', now()->toDateString())->count());
        $tasks = $client->tasks()->count();

        Carbon::setTestNow('2026-12-01 05:00');
        $this->artisan('clients:dashboard-demo --refresh')->assertSuccessful();

        $this->assertSame($url, $demo->client()->dashboardPreviewUrl());
        $this->assertSame($tasks, $demo->client()->tasks()->count());
        $this->assertSame('2026-10-01', $demo->client()->started_on->toDateString());

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get($url)
            ->assertOk()
            ->assertSee('Prosinec 2026')
            ->assertSee('Stránka pro velkoodběratele')
            ->assertSee('Poslat fotky nových směsí');

        $this->artisan('clients:dashboard-demo --remove')->assertSuccessful();
        $this->artisan('clients:dashboard-demo --refresh')->assertSuccessful();
        $this->assertNull($demo->client());
    }

    public function test_administrace_klienta_se_nacte(): void
    {
        $client = $this->klient();
        $client->tasks()->create(['title' => 'Úkol v administraci', 'area' => WorkArea::Web]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get('/nastroje/clients/'.$client->id.'/edit')->assertOk()->assertSee('Pravidelná spolupráce')->assertSee('Úkoly');
        $this->get('/nastroje/fakturace')->assertOk()->assertSee('Bylinky Zkouška')->assertSee("10\u{00A0}000\u{00A0}Kč", false)->assertSee("5\u{00A0}000\u{00A0}Kč", false);
        $this->get('/nastroje/reklamy/hodiny')->assertOk();
    }

    /** 30 000 do listopadu, od prosince 10 000 do ledna, od února předběžně 5 000. */
    private function klientSPlanem(): Client
    {
        $client = Client::create(['name' => 'Plán Zkouška', 'slug' => 'plan-zkouska', 'started_on' => '2026-09-01', 'dashboard_enabled' => true]);
        $client->retainers()->createMany([
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 30000, 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-30'],
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'starts_on' => '2026-12-01', 'ends_on' => '2027-01-31'],
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 5000, 'starts_on' => '2027-02-01', 'is_tentative' => true],
        ]);

        return $client;
    }

    public function test_plan_ceny_se_slije_do_obdobi(): void
    {
        $periods = RetainerSchedule::for($this->klientSPlanem());

        $this->assertSame(['říjen – listopad 2026', 'prosinec 2026 – leden 2027', 'od února 2027'], array_column($periods, 'label'));
        $this->assertSame([false, false, true], array_column($periods, 'tentative'));
        $this->assertSame("5\u{00A0}000\u{00A0}Kč", $periods[2]['total']);
    }

    public function test_plan_ceny_vidi_klient_jen_kdyz_ho_zapneme(): void
    {
        $client = $this->klientSPlanem();

        $this->get($this->url($client))->assertOk()->assertDontSee('Cena spolupráce');

        $client->update(['dashboard_shows_pricing' => true]);

        $this->get($this->url($client))
            ->assertOk()
            ->assertSee('Cena spolupráce')
            ->assertSee('prosinec 2026 – leden 2027')
            ->assertSee('od února 2027')
            ->assertSee('předběžně');
    }

    public function test_prekryv_pausalu_neprojde(): void
    {
        $client = $this->klientSPlanem();
        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['retainers' => [
                ['area' => 'web', 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'starts_on' => '2026-12-01', 'ends_on' => '2027-02-28', 'is_tentative' => false],
                ['area' => 'web', 'label' => 'Vývoj webu', 'monthly_fee' => 5000, 'starts_on' => '2027-02-01', 'ends_on' => null, 'is_tentative' => true],
            ]])
            ->call('save')
            ->assertHasFormErrors(['retainers']);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['retainers' => [
                ['area' => 'web', 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'starts_on' => '2026-12-01', 'ends_on' => '2027-01-31', 'is_tentative' => false],
                ['area' => 'web', 'label' => 'Vývoj webu', 'monthly_fee' => 5000, 'starts_on' => '2027-02-01', 'ends_on' => null, 'is_tentative' => true],
                ['area' => 'marketing', 'label' => 'Marketing', 'monthly_fee' => 5000, 'starts_on' => '2026-12-01', 'ends_on' => null, 'is_tentative' => false],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $client->retainers()->count());

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->assertSee('Plán ceny, jak ho uvidí klient');
    }
}
