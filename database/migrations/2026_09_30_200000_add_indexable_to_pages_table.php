<?php

/*
|--------------------------------------------------------------------------
| Statická stránka mimo vyhledávače
|--------------------------------------------------------------------------
| Některé stránky jsou pro klienty, ne pro Google (třeba tipy se screenshoty
| cizích e-shopů na /pro-klienty). Vypnutá indexace dá stránce noindex
| a vyřadí ji z mapy webu. Odkaz na ni dál funguje.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('indexable')->default(true)->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('indexable');
        });
    }
};
