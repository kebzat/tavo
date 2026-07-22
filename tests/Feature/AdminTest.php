<?php

namespace Tests\Feature;

use App\Models\User;
use App\Settings\ContactSettings;
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

    /**
     * Prázdné pole ve Filamentu přijde jako null. Volitelné vlastnosti v settings
     * třídách proto musí být nullable, jinak uložení skončí chybou.
     */
    public function test_volitelna_nastaveni_snesou_prazdnou_hodnotu(): void
    {
        $this->seed(ContentSeeder::class);

        $contact = app(ContactSettings::class);
        $contact->ico = null;
        $contact->dic = null;
        $contact->address = null;
        $contact->company_name = null;
        $contact->save();

        $home = app(HomeSettings::class);
        $home->hero_eyebrow = null;
        $home->save();

        $this->get('/')->assertOk();
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
