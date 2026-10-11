<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Pages\ManageCrm;
use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Filament\Tools\Resources\Proposals\Pages\EditProposal;
use App\Models\Audit;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\StaffIp;
use App\Models\User;
use App\Settings\CrmSettings;
use App\Support\OurIps;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Otevření sdílených dokumentů: klientovi se nepočítají otevření z našich adres.
 */
class ClientViewsTest extends TestCase
{
    use RefreshDatabase;

    private function audit(): Audit
    {
        $client = Client::create(['name' => 'Hračky Zkouška', 'slug' => 'hracky-zkouska']);

        return Audit::create(['client_id' => $client->id, 'title' => 'Audit', 'is_public' => true]);
    }

    private function open(Audit $audit, string $ip): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari/605.1')
            ->get($audit->publicUrl())
            ->assertOk();
    }

    public function test_otevreni_z_rucne_zadane_adresy_se_nepocita(): void
    {
        $settings = app(CrmSettings::class);
        $settings->ignored_ips = ['89.24.10.5'];
        $settings->save();

        $audit = $this->audit();
        $this->open($audit, '89.24.10.5');
        $this->open($audit, '193.85.1.1');

        $audit->refresh();
        $this->assertSame(1, $audit->view_count);
        $this->assertSame(2, $audit->clientViews()->count(), 'Naše otevření se zapíše, jen se nepočítá.');
        $this->assertStringContainsString('89.24.10.5 · my, nepočítá se', $audit->viewLog()[1]);
    }

    public function test_prihlaseni_odecte_i_drivejsi_otevreni_ze_stejne_adresy(): void
    {
        $audit = $this->audit();
        $this->open($audit, '89.24.10.5');
        $this->open($audit, '193.85.1.1');
        $this->assertSame(2, $audit->fresh()->view_count);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->withServerVariables(['REMOTE_ADDR' => '89.24.10.5'])
            ->get('/');

        $this->assertDatabaseHas('staff_ips', ['network' => '89.24.10.5']);
        $this->assertSame(1, $audit->fresh()->view_count);
    }

    public function test_ipv6_se_porovnava_podle_site(): void
    {
        $this->assertSame('2a00:1028:9b2c:1a0::/64', OurIps::network('2a00:1028:9b2c:1a0:5d1:aa:3:1'));
        $this->assertSame('89.24.10.5', OurIps::network('89.24.10.5'));
        $this->assertNull(OurIps::network('nesmysl'));

        app(OurIps::class)->remember('2a00:1028:9b2c:1a0::1', null);

        $audit = $this->audit();
        $this->open($audit, '2a00:1028:9b2c:1a0:5d1:aa:3:1');

        $this->assertSame(0, $audit->fresh()->view_count);
    }

    public function test_stara_prihlasena_adresa_uz_nase_neni(): void
    {
        StaffIp::create([
            'network' => '89.24.10.5',
            'ip' => '89.24.10.5',
            'first_seen_at' => now()->subMonths(4),
            'last_seen_at' => now()->subMonths(3),
        ]);

        $audit = $this->audit();
        $this->open($audit, '89.24.10.5');

        $this->assertSame(1, $audit->fresh()->view_count);
    }

    public function test_ulozeni_adres_v_nastaveni_prepocita_dokumenty(): void
    {
        $audit = $this->audit();
        $this->open($audit, '89.24.10.5');
        $this->assertSame(1, $audit->fresh()->view_count);

        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(ManageCrm::class)
            ->fillForm(['ignored_ips' => ['89.24.10.5']])
            ->call('save')
            ->assertHasNoFormErrors();

        $audit->refresh();
        $this->assertSame(0, $audit->view_count);
        $this->assertNull($audit->first_viewed_at);
    }

    public function test_administrace_ukaze_jednotliva_otevreni(): void
    {
        $audit = $this->audit();
        $this->open($audit, '193.85.1.1');
        app(OurIps::class)->remember('89.24.10.5', null);
        $this->open($audit, '89.24.10.5');

        $proposal = Proposal::create(['company_name' => 'Hračky', 'title' => 'Návrh', 'slug' => 'hracky', 'is_public' => true]);
        $proposal->recordView('Mozilla/5.0 (Windows NT 10.0)', '193.85.1.2');

        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tomáš']));

        Livewire::test(EditAudit::class, ['record' => $audit->getRouteKey()])
            ->assertOk()
            ->assertSee('Jednotlivá otevření')
            ->assertSee('mobil · 193.85.1.1')
            ->assertSee('89.24.10.5 · my, nepočítá se');

        Livewire::test(EditProposal::class, ['record' => $proposal->getRouteKey()])
            ->assertOk()
            ->assertSee('počítač · 193.85.1.2');

        Livewire::test(ManageCrm::class)
            ->assertOk()
            ->assertSee('Naše IP adresy')
            ->assertSee('89.24.10.5 · naposledy');
    }

    public function test_starsi_otevreni_bez_ip_zustanou(): void
    {
        $audit = $this->audit();
        $audit->forceFill([
            'view_count' => 9,
            'first_viewed_at' => '2026-09-30 11:13:00',
            'last_viewed_at' => '2026-10-02 07:29:00',
            'views_before_log' => 9,
            'first_viewed_before_log_at' => '2026-09-30 11:13:00',
            'last_viewed_before_log_at' => '2026-10-02 07:29:00',
        ])->saveQuietly();

        $this->open($audit, '193.85.1.1');
        app(OurIps::class)->remember('89.24.10.5', null);
        $this->open($audit, '89.24.10.5');

        $audit->refresh();
        $this->assertSame(10, $audit->view_count);
        $this->assertSame('2026-09-30 11:13', $audit->first_viewed_at->format('Y-m-d H:i'));
        $this->assertTrue($audit->last_viewed_at->isToday());
    }

    public function test_migrace_vrati_vynulovane_pocty(): void
    {
        $audit = $this->audit();
        $audit->forceFill([
            'view_count' => 0,
            'first_viewed_at' => null,
            'last_viewed_at' => null,
            'views_before_log' => 9,
            'first_viewed_before_log_at' => '2026-09-30 11:13:00',
            'last_viewed_before_log_at' => '2026-10-02 07:29:00',
        ])->saveQuietly();
        $audit->clientViews()->create(['ip' => '193.85.1.1', 'network' => '193.85.1.1', 'user_agent' => 'Mozilla/5.0', 'viewed_at' => now()]);

        (require database_path('migrations/2026_10_11_200000_restore_view_counts.php'))->up();

        $audit->refresh();
        $this->assertSame(10, $audit->view_count);
        $this->assertSame('2026-09-30 11:13', $audit->first_viewed_at->format('Y-m-d H:i'));
        $this->assertTrue($audit->last_viewed_at->isToday());
    }
}
