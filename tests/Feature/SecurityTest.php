<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Filament\Tools\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\ApiToken;
use App\Models\ApiTokenCall;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Zabezpečení panelů a strojového rozhraní. Viz docs/SECURITY.md.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Admin, ...$attributes]);
    }

    /*
    |--------------------------------------------------------------------------
    | Dvoufázové ověření
    |--------------------------------------------------------------------------
    */

    public function test_bez_dvoufazoveho_overeni_se_do_panelu_nedostane(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/nastroje/today')->assertRedirect('/nastroje/multi-factor-authentication/set-up');
        $this->actingAs($user)->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
        $this->actingAs($user)->get('/nastroje/multi-factor-authentication/set-up')->assertOk();
    }

    public function test_heslo_samo_neprihlasi_a_kod_z_aplikace_ano(): void
    {
        Filament::setCurrentPanel('tools');
        $secret = app(Google2FA::class)->generateSecretKey(16);
        $user = $this->admin(['email' => 'tom@taveo.cz', 'app_authentication_secret' => $secret]);

        $login = Livewire::test(Login::class)
            ->fillForm(['email' => 'tom@taveo.cz', 'password' => 'password'])
            ->call('authenticate');

        $this->assertGuest();

        $login->set('data.multiFactor.app.code', '000000')->call('authenticate');
        $this->assertGuest();

        $login->set('data.multiFactor.app.code', app(Google2FA::class)->getCurrentOtp($secret))->call('authenticate');
        $this->assertAuthenticatedAs($user);
    }

    public function test_tajny_klic_je_v_databazi_sifrovany(): void
    {
        $user = $this->admin(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        $raw = (string) $user->newQuery()->toBase()->where('id', $user->getKey())->value('app_authentication_secret');

        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $raw);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
        $this->assertArrayNotHasKey('app_authentication_secret', $user->toArray());
    }

    /*
    |--------------------------------------------------------------------------
    | Tokeny agentů
    |--------------------------------------------------------------------------
    */

    public function test_token_pusti_jen_na_to_co_smi_a_volani_se_zapise(): void
    {
        [$token, $plain] = ApiToken::issue('Audity', [ApiAbility::CompaniesScout]);

        $this->getJson(route('crm.companies.scout', ['website' => 'nikde.cz']), ['X-Crm-Token' => $plain])->assertNotFound();
        $this->getJson(route('crm.export.pipeline'), ['X-Crm-Token' => $plain])->assertForbidden();

        $this->assertSame(
            [['crm.companies.scout', 404], ['crm.export.pipeline', 403]],
            ApiTokenCall::orderBy('id')->get()->map(fn (ApiTokenCall $call): array => [$call->endpoint, $call->status])->all(),
        );
        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_token_prijde_i_jako_bearer(): void
    {
        [, $plain] = ApiToken::issue('Export', [ApiAbility::PipelineExport]);

        $this->withToken($plain)->getJson(route('crm.export.pipeline'))->assertOk();
    }

    public function test_zruseny_token_neplati(): void
    {
        [$token, $plain] = ApiToken::issue('Starý', [ApiAbility::PipelineExport]);
        $token->revoke();

        $this->getJson(route('crm.export.pipeline'), ['X-Crm-Token' => $plain])->assertUnauthorized();
    }

    public function test_token_v_adrese_se_neprijima(): void
    {
        [, $plain] = ApiToken::issue('Export', [ApiAbility::PipelineExport]);
        config(['crm.import_token' => 'spolecny']);

        $this->getJson(route('crm.export.pipeline', ['token' => $plain]))->assertUnauthorized();
        $this->getJson(route('crm.export.pipeline', ['token' => 'spolecny']))->assertUnauthorized();
    }

    public function test_v_databazi_je_jen_otisk_tokenu(): void
    {
        [$token, $plain] = ApiToken::issue('Audity', [ApiAbility::AuditsImport]);

        $this->assertStringStartsWith(ApiToken::PREFIX, $plain);
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);
    }

    public function test_starý_spolecny_token_zatim_plati_a_zapise_se_bez_agenta(): void
    {
        config(['crm.import_token' => 'spolecny']);

        $this->getJson(route('crm.export.pipeline'), ['X-Crm-Token' => 'spolecny'])->assertOk();

        $this->assertNull(ApiTokenCall::sole()->api_token_id);
    }

    public function test_bez_tokenu_rozhrani_nic_nepusti(): void
    {
        config(['crm.import_token' => null]);

        $this->getJson(route('crm.export.pipeline'))->assertUnauthorized();
        $this->getJson(route('crm.export.pipeline'), ['X-Crm-Token' => ''])->assertUnauthorized();
    }

    public function test_hadani_tokenu_se_po_deseti_pokusech_zastavi(): void
    {
        [, $plain] = ApiToken::issue('Export', [ApiAbility::PipelineExport]);

        foreach (range(1, 10) as $attempt) {
            $this->getJson(route('crm.export.pipeline'), ['X-Crm-Token' => 'tavo_hadam'.$attempt])->assertUnauthorized();
        }

        // Ani správný token už z téže adresy chvíli neprojde.
        $this->getJson(route('crm.export.pipeline'), ['X-Crm-Token' => $plain])->assertTooManyRequests();
    }

    public function test_spravce_zalozi_a_zrusi_token_v_nastrojich(): void
    {
        Filament::setCurrentPanel('tools');
        $this->actingAs($admin = $this->admin());

        $this->get('/nastroje/api-tokens')->assertOk();

        Livewire::test(ListApiTokens::class)
            ->callAction('create', ['name' => 'Audit e-shopu', 'abilities' => ['companies:scout', 'audits:import']])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $token = ApiToken::sole();
        $this->assertSame(['companies:scout', 'audits:import'], $token->abilities);
        $this->assertSame($admin->getKey(), $token->created_by);

        Livewire::test(ListApiTokens::class)->callTableAction('revoke', $token);

        $this->assertTrue($token->fresh()->isRevoked());
    }

    public function test_redaktor_k_tokenum_nesmi(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)->get('/nastroje/api-tokens')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Bezpečnostní hlavičky
    |--------------------------------------------------------------------------
    */

    public function test_web_panely_i_api_posilaji_bezpecnostni_hlavicky(): void
    {
        foreach (['/', '/nastroje/login', '/admin/login', '/nastroje/api/export/pipeline'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_hsts_jen_pres_https(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
