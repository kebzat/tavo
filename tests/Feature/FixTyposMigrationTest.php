<?php

namespace Tests\Feature;

use App\Models\Founder;
use App\Settings\HomeSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migrace opravující překlepy z administrace musí sáhnout jen na chybné
 * slovo a zbytek textu správce nechat, jak ho napsal.
 */
class FixTyposMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opravi_jen_preklepy_a_zbytek_textu_necha(): void
    {
        $this->seed(ContentSeeder::class);

        $settings = app(HomeSettings::class);
        $settings->loop_perex = 'Každý projekt řešíme společně. Veškěrá doporučení posuzujeme i technicky.';
        $settings->save();

        $founder = Founder::query()->first();
        $founder->update(['bio' => 'Řešil desítky firem, od e-commerce po B2B spolupráce — V Meta ads řídil rozpočty. Řeší převod návštěvíků webu na zákazníky.']);

        (require database_path('migrations/2026_09_25_180000_fix_typos_in_live_copy.php'))->up();

        app()->forgetInstance(HomeSettings::class);

        $this->assertSame(
            'Každý projekt řešíme společně. Veškerá doporučení posuzujeme i technicky.',
            app(HomeSettings::class)->loop_perex,
        );
        $this->assertSame(
            'Řešil desítky firem, od e-commerce po B2B spolupráce. V Meta ads řídil rozpočty. Řeší převod návštěvníků webu na zákazníky.',
            $founder->fresh()->bio,
        );
    }
}
