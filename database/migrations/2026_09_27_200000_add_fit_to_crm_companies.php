<?php

/*
|--------------------------------------------------------------------------
| Posouzení firmy: sedí pro Taveo, nebo ne?
|--------------------------------------------------------------------------
| Rešerše plnila CRM ručně a bez filtru, takže se do fronty k oslovení
| dostali elektrikáři a autoškoly vedle zavedených e-shopů. Proklepnutí
| webu (App\Support\Crm\Scout\ProspectScout) změří veřejnou část webu,
| spočítá skóre a výsledek uloží sem. Fronta „K oslovení" se podle něj řadí.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->unsignedTinyInteger('fit_score')->nullable()->index()->after('priority');
            $table->string('fit_verdict', 20)->nullable()->index()->after('fit_score');  // App\Enums\Crm\FitVerdict

            // Měření, nálezy a důvody skóre. Z nich se skládá audit, takže
            // se nepřepočítávají pokaždé znovu a audit sedí na stav k datu.
            $table->json('scout_data')->nullable()->after('fit_verdict');
            $table->dateTime('scouted_at')->nullable()->after('scout_data');
        });
    }

    public function down(): void
    {
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->dropColumn(['fit_score', 'fit_verdict', 'scout_data', 'scouted_at']);
        });
    }
};
