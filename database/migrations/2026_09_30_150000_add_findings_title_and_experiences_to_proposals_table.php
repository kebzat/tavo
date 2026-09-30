<?php

/*
|--------------------------------------------------------------------------
| Potenciální spolupráce: vlastní nadpis sekce 01 a naše zkušenosti
|--------------------------------------------------------------------------
| „Co jsme objevili“ je statický text společný všem nabídkám. U některé se
| hodí jiný nadpis („Stručné shrnutí“), tak ho jde přepsat u konkrétní
| stránky. Prázdné pole = platí statický text.
|
| Zkušenosti: krátké věty z naší praxe s vyznačenými čísly. JSON pole
| z repeateru jako ostatní sekce.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('findings_title')->nullable()->after('timeline');
            $table->json('experiences')->nullable()->after('examples');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['findings_title', 'experiences']);
        });
    }
};
