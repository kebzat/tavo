<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Catch-all /{slug} v routách stránek je registrovaný jako poslední,
     * ale kdyby se pořadí rozbilo, spolkl by i /install a průvodce by zmizel.
     */
    public function test_adresa_install_patri_pruvodci_a_ne_catch_all_slugu(): void
    {
        $this->assertTrue(Route::has('install.show'));

        $route = app('router')->getRoutes()->match(Request::create('/install', 'GET'));

        $this->assertSame('install.show', $route->getName());
    }

    /**
     * Nejdůležitější bezpečnostní vlastnost: na běžícím webu (klíč + migrace)
     * se průvodce nesmí dát otevřít, jinak by kdokoli přepsal konfiguraci.
     */
    public function test_pruvodce_je_na_nainstalovanem_webu_nedostupny(): void
    {
        $this->get('/install')->assertNotFound();
    }

    public function test_pruvodce_odmita_i_odeslany_formular(): void
    {
        $this->post('/install', [
            'app_name' => 'Podvržený web',
            'app_url' => 'https://example.com',
            'db_host' => '127.0.0.1',
            'db_database' => 'cizi_databaze',
            'db_username' => 'utocnik',
        ])->assertNotFound();
    }

    public function test_udrzba_vyzaduje_prihlaseni(): void
    {
        $this->get('/admin/maintenance')->assertRedirect('/admin/login');
    }

    public function test_prihlaseny_uzivatel_vidi_udrzbu(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/maintenance')
            ->assertOk()
            ->assertSee('Stav webu');
    }
}
