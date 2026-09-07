<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jak se má náhled reference vejít do rámečku ve výpisu.
 *
 * Rámeček má všude pevný poměr 4:3, aby dlaždice ve mřížce lícovaly. Náhledy
 * ale přicházejí v různých poměrech — připravené koláže 4:3, ale i screenshoty
 * webů 1512×800. Těm širokým ořízne `cover` skoro třetinu výšky.
 *
 * Výchozí `cover` = dosavadní chování, takže stávající referencím se nic nemění.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('thumb_fit')->default('cover')->after('thumb_label');
        });
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn('thumb_fit');
        });
    }
};
