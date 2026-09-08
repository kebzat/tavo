<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volba „jak náhled vyplní rámeček" se ruší.
 *
 * Byl to pokus, jak srovnat náhledy dvou různých tvarů — koláže 4:3 a
 * screenshoty webů skoro 16:9. Varianta „zobrazit celý obrázek" ale nechala
 * kolem obrázku prázdný pruh podkladu a karta pak vypadala jako rámeček
 * s menším obrázkem uvnitř.
 *
 * Rámeček má nově poměr 16:10, který leží mezi oběma tvary, takže obojí vyplní
 * kartu celou a ořízne se z něj jen pár procent. Přepínač tím ztratil smysl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn('thumb_fit');
        });
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('thumb_fit')->default('cover')->after('thumb_label');
        });
    }
};
