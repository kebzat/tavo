<?php

namespace Tests\Feature;

use App\Enums\AutomationType;
use App\Enums\UserRole;
use App\Enums\VaultType;
use App\Filament\Client\Pages\Auth\RequestPasswordReset;
use App\Filament\Client\Pages\Automations;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Tools\Resources\Automations\Pages\CreateAutomation;
use App\Filament\Tools\Resources\Automations\Pages\EditAutomation;
use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Models\Client;
use App\Models\User;
use App\Models\VaultEntry;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Účet klienta (/muj-ucet) a role Klient. Viz docs/AUTOMATIZACE.md. */
class ClientPanelTest extends TestCase
{
    use RefreshDatabase;

    private Client $cejlon;

    private Client $other;

    private Automation $mine;

    private Automation $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cejlon = Client::query()->firstOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu']);
        $this->other = Client::query()->create(['name' => 'Cizí obchod', 'slug' => 'cizi-obchod']);

        $this->mine = $this->automation($this->cejlon, '04294');
        $this->foreign = $this->automation($this->other, '88888');
    }

    private function automation(Client $client, string $order): Automation
    {
        $automation = Automation::query()->create([
            'client_id' => $client->id,
            'type' => AutomationType::UpgatesParcelPickup,
            'enabled' => true,
            'last_run_at' => now(),
            'snapshot' => ['parcels' => [['order' => $order, 'carrier' => 'zasilkovna', 'stored_until' => '2026-11-03', 'days_left' => 2, 'status' => 'PARCELSHOP', 'sent' => ['reminder']]]],
        ]);
        AutomationEvent::query()->create(['automation_id' => $automation->id, 'order_number' => $order, 'kind' => 'reminder', 'target_status' => 'Čeká na vyzvednutí']);

        return $automation;
    }

    private function marek(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Client, 'client_id' => $this->cejlon->id, 'name' => 'Marek', 'email' => 'info@svetcejlonu.cz', ...$attributes]);
    }

    public function test_klient_vidi_jen_sve_automatizace(): void
    {
        $this->actingAs($this->marek());

        $this->get('/muj-ucet')->assertRedirect('/muj-ucet/automatizace');
        $this->get('/muj-ucet/automatizace')
            ->assertOk()
            ->assertSee('Připomínky vyzvednutí zásilek')
            ->assertSee('04294')
            ->assertSee('Čeká na Upgates')
            ->assertSee('Zásilkovna')
            ->assertDontSee('88888')
            ->assertDontSee('Cizí obchod')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // ?klient= je jen pro správce, klientovi cizí data neukáže.
        $this->get('/muj-ucet/automatizace?klient='.$this->other->id)->assertOk()->assertDontSee('88888');
    }

    public function test_klient_se_nedostane_do_administrace_ani_nastroju(): void
    {
        $this->actingAs($this->marek());

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/case-studies')->assertForbidden();
        $this->get('/nastroje/today')->assertForbidden();
        $this->get('/nastroje/automatizace')->assertForbidden();
        $this->get('/nastroje/trezor')->assertForbidden();
    }

    public function test_klient_bez_prirazeneho_klienta_ani_redaktor_do_uctu_nesmi(): void
    {
        $this->actingAs($this->marek(['client_id' => null]))->get('/muj-ucet/automatizace')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))->get('/muj-ucet/automatizace')->assertForbidden();
    }

    public function test_spravce_vidi_vsechny_a_muze_vybrat_klienta(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get('/muj-ucet/automatizace')->assertOk()->assertSee('04294')->assertSee('88888');
        $this->get('/muj-ucet/automatizace?klient='.$this->other->id)->assertOk()->assertSee('88888')->assertDontSee('04294');
    }

    public function test_klient_meni_jen_prepinace_a_jen_u_sebe(): void
    {
        Filament::setCurrentPanel('client');
        $marek = $this->marek();

        Livewire::actingAs($marek)
            ->test(Automations::class)
            ->callAction('settings', ['enabled' => false, 'send_reminder' => false, 'send_last_day' => true, 'send_returned' => false, 'remind_days_before' => 3], ['automation' => $this->mine->id])
            ->assertHasNoActionErrors();

        $mine = $this->mine->fresh();
        $this->assertFalse($mine->enabled);
        $this->assertFalse($mine->setting('send_reminder'));
        $this->assertSame(3, $mine->setting('remind_days_before'));
        $this->assertSame('Odeslána', $mine->setting('status_sent'));

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($marek)
            ->test(Automations::class)
            ->callAction('settings', ['enabled' => false], ['automation' => $this->foreign->id]);
    }

    public function test_spravce_zalozi_automatizaci_a_vidi_adresu_csv(): void
    {
        Filament::setCurrentPanel('tools');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $zaslat = VaultEntry::query()->create(['client_id' => $this->cejlon->id, 'type' => VaultType::ApiKey, 'name' => 'Zaslat API', 'url' => 'zaslat.cz', 'secret' => 'k', 'agent_access' => 'read']);
        VaultEntry::query()->create(['client_id' => $this->other->id, 'type' => VaultType::ApiKey, 'name' => 'Cizí Zaslat', 'url' => 'zaslat.cz', 'secret' => 'k', 'agent_access' => 'read']);

        Livewire::actingAs($admin)
            ->test(CreateAutomation::class)
            ->fillForm([
                'client_id' => $this->cejlon->id,
                'type' => AutomationType::UpgatesParcelPickup->value,
                'settings.vault_entry_id' => $zaslat->id,
                'order_export_url' => "https://svetcejlonu.cz/export/vse.xml\nhttps://svetcejlonu.cz/export/24h.xml",
            ])
            ->assertFormFieldExists('settings.vault_entry_id')
            ->call('create')
            ->assertHasNoFormErrors();

        $automation = Automation::query()->latest('id')->first();
        $this->assertSame($zaslat->id, (int) $automation->setting('vault_entry_id'));
        $this->assertSame(['https://svetcejlonu.cz/export/vse.xml', 'https://svetcejlonu.cz/export/24h.xml'], $automation->exportUrls());
        $this->assertSame(['Odeslána', 'Čeká na vyzvednutí', 'Poslední den k vyzvednutí'], $automation->sourceStatuses());
        $this->assertFalse($automation->enabled);
        $this->assertSame(40, strlen($automation->import_token));
        $this->assertStringNotContainsString('vse.xml', (string) $automation->getRawOriginal('order_export_url'));

        $this->actingAs($admin)->get('/nastroje/automatizace')->assertOk()->assertSee('Svět Cejlonu');
        $this->actingAs($admin)->get('/nastroje/automatizace/'.$automation->id.'/edit')
            ->assertOk()
            ->assertSee($automation->csvUrl())
            ->assertDontSee('Cizí Zaslat');

        // Adresa exportu je v modelu skrytá, ale do formuláře se načte (jinak by uložení spadlo na povinném poli).
        Livewire::actingAs($admin)
            ->test(EditAutomation::class, ['record' => $automation->id])
            ->assertSchemaStateSet(['order_export_url' => "https://svetcejlonu.cz/export/vse.xml\nhttps://svetcejlonu.cz/export/24h.xml"])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_role_klient_potrebuje_klienta_a_jinde_se_klient_maze(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm(['name' => 'Marek', 'email' => 'info@svetcejlonu.cz', 'password' => 'heslo-heslo-1', 'role' => UserRole::Client->value])
            ->call('create')
            ->assertHasFormErrors(['client_id' => 'required']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm(['name' => 'Marek', 'email' => 'info@svetcejlonu.cz', 'password' => 'heslo-heslo-1', 'role' => UserRole::Client->value, 'client_id' => $this->cejlon->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $marek = User::query()->where('email', 'info@svetcejlonu.cz')->first();
        $this->assertSame(UserRole::Client, $marek->role);
        $this->assertSame($this->cejlon->id, $marek->client_id);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $marek->id])
            ->fillForm(['role' => UserRole::Editor->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($marek->fresh()->client_id);
    }

    public function test_ucty_klientu_nejsou_mezi_lidmi_tymu(): void
    {
        $this->marek();
        User::factory()->create(['role' => UserRole::Admin, 'email' => 'tom@taveo.cz']);

        $this->assertSame(['tom@taveo.cz'], User::query()->staff()->pluck('email')->all());
    }

    public function test_zapomenute_heslo_jen_pro_ucty_klientu(): void
    {
        $this->get('/muj-ucet/password-reset/request')->assertOk();
        $this->get('/muj-ucet/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        Notification::fake();
        Filament::setCurrentPanel('client');
        $marek = $this->marek();
        $tom = User::factory()->create(['role' => UserRole::Admin, 'email' => 'tom@taveo.cz']);

        foreach (['info@svetcejlonu.cz', 'tom@taveo.cz', 'nikdo@example.cz'] as $email) {
            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $email])
                ->call('request')
                ->assertNotified();
        }

        Notification::assertSentTo($marek, ResetPassword::class);
        Notification::assertNotSentTo($tom, ResetPassword::class);
    }
}
