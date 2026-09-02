<?php

namespace Tests\Feature;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Mail\CrmDailyDigest;
use App\Models\Crm\Company;
use App\Models\Crm\Demand;
use App\Models\User;
use App\Settings\CrmSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CrmDigestTest extends TestCase
{
    use RefreshDatabase;

    private function firmaPoTerminu(string $name = 'Pekárna U Nádraží'): Company
    {
        $company = Company::create([
            'name' => $name,
            'segment' => CompanySegment::Local,
            'source' => CompanySource::Research,
            'status' => CompanyStatus::FollowUp,
        ]);

        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'body' => 'Poslán studený e-mail.',
            'happened_at' => now()->subDays(10),
            'follow_up_at' => now()->subDays(3),
        ]);

        return $company->refresh();
    }

    public function test_souhrn_odejde_uzivateli_s_propasnutym_follow_upem(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'tom@taveo.cz']);
        $company = $this->firmaPoTerminu();

        $this->artisan('crm:daily-digest')->assertSuccessful();

        Mail::assertSent(CrmDailyDigest::class, function (CrmDailyDigest $mail) use ($user, $company): bool {
            return $mail->hasTo($user->email)
                && $mail->overdue->contains('id', $company->getKey());
        });
    }

    public function test_souhrn_obsahuje_nove_poptavky_a_odkaz_do_crm(): void
    {
        $user = User::factory()->create(['email' => 'tom@taveo.cz']);
        $this->firmaPoTerminu();
        Demand::factory()->create(['title' => 'Migrace e-shopu na Shoptet']);

        $mail = new CrmDailyDigest(
            $user,
            Company::overdue()->get(),
            Company::dueToday()->get(),
            Demand::untouched()->get(),
            collect(),
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Pekárna U Nádraží', $rendered);
        $this->assertStringContainsString('Migrace e-shopu na Shoptet', $rendered);
        $this->assertStringContainsString('/nastroje/today', $rendered);
        $this->assertStringContainsString('1 follow-upů na dnešek', $mail->envelope()->subject);
    }

    public function test_bez_prace_souhrn_prizna_ze_neni_co_delat(): void
    {
        $user = User::factory()->create();

        $mail = new CrmDailyDigest($user, collect(), collect(), collect(), collect());

        $this->assertStringContainsString('Dneska tě v CRM nic nečeká', $mail->render());
        $this->assertStringContainsString('žádné follow-upy', $mail->envelope()->subject);
    }

    public function test_nastaveni_omezi_prijemce_souhrnu(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'tom@taveo.cz']);
        User::factory()->create(['email' => 'pavel@taveo.cz']);
        $this->firmaPoTerminu();

        $settings = app(CrmSettings::class);
        $settings->digest_recipients = ['tom@taveo.cz'];
        $settings->save();

        $this->artisan('crm:daily-digest')->assertSuccessful();

        Mail::assertSent(CrmDailyDigest::class, 1);
        Mail::assertSent(CrmDailyDigest::class, fn (CrmDailyDigest $mail): bool => $mail->hasTo('tom@taveo.cz'));
    }

    /** Nefunkční mailer nesmí shodit naplánovaný běh. */
    public function test_selhani_odeslani_prikaz_neshodi(): void
    {
        User::factory()->create();
        $this->firmaPoTerminu();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP nedostupné'));

        $this->artisan('crm:daily-digest')->assertSuccessful();
    }

    public function test_bez_uctu_se_nic_neposila(): void
    {
        Mail::fake();

        $this->artisan('crm:daily-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_prikaz_crm_user_zalozi_ucet_s_pristupem_do_nastroju(): void
    {
        $this->artisan('crm:user', ['name' => 'Tom', 'email' => 'tom@taveo.cz', '--password' => 'tajneheslo'])
            ->assertSuccessful();

        $user = User::where('email', 'tom@taveo.cz')->firstOrFail();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('tools')));

        // Druhé spuštění nesmí přepsat heslo, které si mezitím někdo změnil.
        $original = $user->password;
        $this->artisan('crm:user', ['name' => 'Tom', 'email' => 'tom@taveo.cz', '--password' => 'jinéheslo'])
            ->assertSuccessful();

        $this->assertSame($original, $user->fresh()->password);
    }
}
