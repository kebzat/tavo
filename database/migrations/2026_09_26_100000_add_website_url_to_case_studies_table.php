<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odkaz na živý web projektu. Na detailu reference se ukáže pod perexem
 * jako „Podívat se na web ↗"; prázdné pole = žádný odkaz.
 *
 * Adresy existujících referencí pochází z tomaskebza.cz/reference (ověřeno
 * 26. 9. 2026). RAPPA žádnou nemá: na rappa.cz zatím běží starý Magento,
 * ne nový e-shop, na kterém Tom dělal.
 */
return new class extends Migration
{
    private const URLS = [
        '2e-kompresory' => 'https://www.2e-kompresory.cz/',
        'ales-malinsky' => 'https://alesmalinsky.cz/',
        'chrudimlab' => 'https://www.chrudimlab.cz/',
        'hopnjoy' => 'https://hopnjoy.cz/',
        'sh-mediace' => 'https://shmediace.cz/',
        'them-cars' => 'https://themcars.cz/',
        'vcely-uhersko' => 'https://vcelyuhersko.cz/',
        'mycomedica' => 'https://www.mycomedica.cz/',
        'helago' => 'https://www.helago-cz.cz/',
        'rostex' => 'https://eshop.rostex.cz/',
        'pozarni-zbozi' => 'https://www.pozarni-zbozi.cz/',
        'mesto-chocen' => 'https://www.chocen.cz/',
        'casopis-stavebnictvi' => 'https://www.casopisstavebnictvi.cz/',
        'tiyo' => 'https://www.tiyo.cz/',
        'milan-schirlo' => 'https://www.milanschirlo.cz/',
        'mekko' => 'https://www.mekko.cz/',
        'kariera-jmk' => 'https://kariera-jmk.cz/',
        'vas-najem' => 'https://vasnajem.cz/',
        'koor' => 'https://www.koor.cz/',
    ];

    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('duration');
        });

        foreach (self::URLS as $slug => $url) {
            DB::table('case_studies')
                ->where('slug', $slug)
                ->whereNull('website_url')
                ->update(['website_url' => $url]);
        }
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn('website_url');
        });
    }
};
