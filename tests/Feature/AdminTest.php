<?php

namespace Tests\Feature;

use App\Models\User;
use App\Settings\HomeSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrace_vyzaduje_prihlaseni(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_prihlaseny_uzivatel_vidi_nastenku(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk();
    }

    public function test_zmena_nastaveni_se_projevi_na_webu(): void
    {
        $this->seed(ContentSeeder::class);

        $settings = app(HomeSettings::class);
        $settings->hero_line_1 = 'Zcela nový nadpis';
        $settings->save();

        $this->get('/')->assertOk()->assertSee('Zcela nový nadpis', false);
    }
}
