<?php

/*
|--------------------------------------------------------------------------
| Přehled pro klienta: plán ceny do budoucna
|--------------------------------------------------------------------------
| „Teď 30 000, od prosince 10 000, od února 5 000.“ Sekce Cena spolupráce
| ukáže klientovi paušál po obdobích, i předběžná. Zapíná se u každého
| klienta zvlášť, výchozí vypnuto: předběžný plán je jinak jen pro nás.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('dashboard_shows_pricing')->default(false)->after('dashboard_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('dashboard_shows_pricing');
        });
    }
};
