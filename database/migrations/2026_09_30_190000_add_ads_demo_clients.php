<?php

/*
|--------------------------------------------------------------------------
| Reklamy: ukázkoví klienti
|--------------------------------------------------------------------------
| Na produkci ještě nemáme přístupy k Metě a Googlu. Ukázkoví klienti
| s vymyšlenými čísly ukážou, jak nástroj vypadá a reaguje. Čísla stahuje
| běžná synchronizace z ukázkové platformy a každé ráno je doplní.
|
| Po napojení skutečných účtů: ADS_DEMO=false a php artisan ads:demo --remove.
| V testech se nezakládají, testy si data připravují samy.
*/

use App\Support\Ads\DemoData;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || ! config('ads.demo_enabled') || app(DemoData::class)->installed()) {
            return;
        }

        app(DemoData::class)->install();
    }

    public function down(): void
    {
        app(DemoData::class)->remove();
    }
};
