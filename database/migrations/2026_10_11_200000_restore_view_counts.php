<?php

use App\Models\Ads\AdReport;
use App\Models\Audit;
use App\Models\Proposal;
use Illuminate\Database\Migrations\Migration;

/*
|--------------------------------------------------------------------------
| Vrácení počtů otevření
|--------------------------------------------------------------------------
|
| Migrace 2026_10_11_100200 (na produkci spuštěná 11. 10. 2026 v 18:29,
| pak ze zdrojáku odebraná) vynulovala view_count, first_viewed_at
| a last_viewed_at. Původní čísla zůstala ve `views_before_log`. Přepočet
| je vrátí a přičte k nim otevření klientem zapsaná od té doby.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        foreach ([Audit::class, Proposal::class, AdReport::class] as $model) {
            $model::query()->each(fn ($document) => $document->recountViews());
        }
    }

    public function down(): void
    {
        //
    }
};
