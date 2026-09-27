<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseStudy;
use App\Models\ClientLogo;
use App\Models\Founder;
use App\Models\User;
use App\Settings\HomeSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruh s čísly pod úvodem, pás log klientů a odkazy na osobní weby Pavla a Toma.
 */
class HomeTrustAndLogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ContentSeeder::class);
    }

    public function test_pruh_s_cisly_z_migrace_se_vypise(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['8+ let', 'praxe každého z nás', '2 dny', '5 z 5', 'hodnocení Pavla na Googlu']);
    }

    public function test_bez_cisel_pruh_neni(): void
    {
        $settings = app(HomeSettings::class);
        $settings->trust_items = [['value' => '', 'label' => 'bez čísla']];
        $settings->save();
        app()->forgetInstance(HomeSettings::class);

        $this->get('/')->assertOk()->assertDontSee('TAVEO v číslech')->assertDontSee('bez čísla');
    }

    public function test_zverejnena_loga_se_vypisou_a_skryta_ne(): void
    {
        $this->logo('Svět Cejlonu', true);
        $this->logo('Skrytý klient', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="klienti"', false)
            ->assertSee('alt="Svět Cejlonu"', false)
            ->assertDontSee('Skrytý klient');
    }

    public function test_bez_log_pas_neni(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="klienti"', false);
    }

    public function test_migrace_zalozi_loga_jen_na_webu_s_obsahem(): void
    {
        $migration = require database_path('migrations/2026_09_27_100100_add_client_logos.php');

        CaseStudy::query()->delete();
        $migration->up();
        $this->assertSame(0, ClientLogo::count());

        CaseStudy::create(['title' => 'Reference', 'slug' => 'reference', 'published' => true]);
        $migration->up();
        $migration->up();

        $this->assertSame(10, ClientLogo::count());
        $this->assertSame('Svět Cejlonu', ClientLogo::ordered()->first()->name);
        $this->assertNotNull(ClientLogo::ordered()->first()->logoImage());
    }

    public function test_osobni_web_se_ukaze_u_zakladatele(): void
    {
        Founder::query()->first()->update(['external_url' => 'https://www.pavelvcelis.cz/']);

        $this->get('/')
            ->assertOk()
            ->assertSee('href="https://www.pavelvcelis.cz/"', false)
            ->assertSee('pavelvcelis.cz');
    }

    public function test_stara_tomova_adresa_se_opravi(): void
    {
        DB::table('founders')->update(['external_url' => 'https://juliatom.cz/']);

        (require database_path('migrations/2026_09_27_100200_fix_tom_personal_website.php'))->up();

        $this->assertSame(['https://tomaskebza.cz/'], DB::table('founders')->distinct()->pluck('external_url')->all());
    }

    public function test_administrace_log_se_vykresli(): void
    {
        $this->logo('Svět Cejlonu', true);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get('/admin/client-logos')
            ->assertOk()
            ->assertSee('Svět Cejlonu');
    }

    private function logo(string $name, bool $published): ClientLogo
    {
        $logo = ClientLogo::create(['name' => $name, 'published' => $published]);
        $logo->addMedia(UploadedFile::fake()->image('logo.png', 300, 100))->toMediaCollection(ClientLogo::MEDIA_LOGO);

        return $logo;
    }
}
