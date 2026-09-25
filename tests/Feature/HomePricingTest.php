<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Settings\HomeSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blok „A kolik to celé stojí?" na homepage. Karty jsou v settings jako
 * pole z repeateru, takže musí přežít chybějící klíče i vyprázdněná pole.
 */
class HomePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_vychozi_cenik_z_migrace_se_vypise(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="cenik"', false)
            ->assertSeeInOrder(['Strategická konzultace', '2 000 Kč', 'Jednorázová spolupráce', '1 000 Kč', 'Pravidelná spolupráce', 'od 8 900 Kč']);
    }

    public function test_karta_bez_ceny_se_vynecha_a_chybejici_klice_nevadi(): void
    {
        $this->setPlans([
            ['name' => 'Konzultace', 'price' => '2 000 Kč'],
            ['name' => 'Bez ceny', 'price' => '', 'text' => 'Tahle karta se nesmí vypsat.'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Konzultace')
            ->assertDontSee('Tahle karta se nesmí vypsat.');
    }

    public function test_bez_karet_se_sekce_nezobrazi(): void
    {
        $this->setPlans([]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="cenik"', false);
    }

    public function test_zalozka_ceniku_se_v_administraci_vykresli(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get('/admin/manage-home')
            ->assertOk()
            ->assertSee('Formy spolupráce');
    }

    private function setPlans(array $plans): void
    {
        $settings = app(HomeSettings::class);
        $settings->pricing_plans = $plans;
        $settings->save();

        // Spatie drží settings jako singleton, bez zapomenutí by controller
        // dostal tenhle objekt a test by nepoznal, že se nic neuložilo.
        app()->forgetInstance(HomeSettings::class);
    }
}
