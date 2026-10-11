<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Otevření z doby před zápisem po jednom
|--------------------------------------------------------------------------
|
| Počty se nově dopočítávají z client_views. Starší otevření tam nejsou
| (neměla IP adresu), takže by je první přepočet smazal. Uložíme je
| stranou a přepočet je k novým jen přičte.
|
*/
return new class extends Migration
{
    private const TABLES = ['audits', 'proposals', 'ad_reports'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('views_before_log')->default(0)->after('last_viewed_at');
                $table->dateTime('first_viewed_before_log_at')->nullable()->after('views_before_log');
                $table->dateTime('last_viewed_before_log_at')->nullable()->after('first_viewed_before_log_at');
            });

            DB::table($name)->update([
                'views_before_log' => DB::raw('view_count'),
                'first_viewed_before_log_at' => DB::raw('first_viewed_at'),
                'last_viewed_before_log_at' => DB::raw('last_viewed_at'),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['views_before_log', 'first_viewed_before_log_at', 'last_viewed_before_log_at']);
            });
        }
    }
};
