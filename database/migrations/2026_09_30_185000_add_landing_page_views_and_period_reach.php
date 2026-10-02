<?php

/*
|--------------------------------------------------------------------------
| Reklamy: zobrazení cílové stránky a přesný dosah za období
|--------------------------------------------------------------------------
| Zobrazení cílové stránky (landing_page_view) je sčítatelné jako ostatní
| akce, přibývá sloupec v ad_daily_stats. Staré řádky se doplní z `raw`,
| kam se celé actions z Mety ukládaly od začátku.
|
| Dosah, frekvence a unikátní prokliky se přes dny ani kampaně sčítat nedají
| (tentýž člověk by se započítal víckrát). Meta je pro přednastavená období
| vrací jedním dotazem na účet, ukládají se do ad_period_reach. Viz docs/ADS.md.
|
| Datum je schválně před 2026_09_30_190000_add_ads_demo_clients: ta zakládá
| ukázková data současným kódem, který už sloupec potřebuje. Na existující
| databázi se migrace pustí normálně jako čekající.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stejné pořadí typů akcí jako MetaAds::ACTIONS['landing_page_views']. */
    private const ACTION_TYPES = ['landing_page_view', 'omni_landing_page_view'];

    public function up(): void
    {
        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->decimal('landing_page_views', 12, 2)->default(0)->after('checkouts');
        });

        Schema::create('ad_period_reach', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedBigInteger('reach')->default(0);         // lidé za celé období, bez překryvů mezi dny
            $table->unsignedBigInteger('impressions')->default(0);
            $table->decimal('frequency', 8, 4)->default(0);
            $table->unsignedBigInteger('unique_link_clicks')->default(0);
            $table->timestamps();

            $table->unique(['ad_account_id', 'date_from', 'date_to']);
        });

        $this->backfillLandingPageViews();
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_period_reach');

        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->dropColumn('landing_page_views');
        });
    }

    /** Doplní zobrazení cílové stránky ze surových actions. Po kouscích v PHP, ať to jede na MySQL i SQLite. */
    private function backfillLandingPageViews(): void
    {
        DB::table('ad_daily_stats')
            ->whereNotNull('raw')
            ->select(['id', 'raw'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $views = self::pick(json_decode((string) $row->raw, true)['actions'] ?? null);

                    if ($views > 0) {
                        DB::table('ad_daily_stats')->where('id', $row->id)->update(['landing_page_views' => $views]);
                    }
                }
            });
    }

    private static function pick(mixed $actions): float
    {
        if (! is_array($actions)) {
            return 0.0;
        }

        foreach (self::ACTION_TYPES as $type) {
            if (array_key_exists($type, $actions)) {
                return (float) $actions[$type];
            }
        }

        return 0.0;
    }
};
