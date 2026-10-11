<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Počty otevření od nuly
|--------------------------------------------------------------------------
|
| Starší otevření nemají IP adresu, takže z nich nejde odečíst naše
| (rozhodnutí Toma z 11. 10. 2026: radši nula než čísla s našimi
| otevřeními). V přehledu se ukazují jen otevření zapsaná po jednom.
| Původní čísla zůstávají jako archiv ve `views_before_log`
| a `*_before_log_at`, nikde se nezobrazují ani nepřičítají.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        foreach (['audits', 'proposals', 'ad_reports'] as $name) {
            DB::table($name)->update([
                'view_count' => 0,
                'first_viewed_at' => null,
                'last_viewed_at' => null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['audits', 'proposals', 'ad_reports'] as $name) {
            DB::table($name)->update([
                'view_count' => DB::raw('views_before_log'),
                'first_viewed_at' => DB::raw('first_viewed_before_log_at'),
                'last_viewed_at' => DB::raw('last_viewed_before_log_at'),
            ]);
        }
    }
};
