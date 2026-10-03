<?php

/*
|--------------------------------------------------------------------------
| Potenciální spolupráce: varianty měsíční spolupráce
|--------------------------------------------------------------------------
| Karty s cenou za měsíc, rozsahem a tím, co v ceně je. Jedna může být
| označená jako doporučená (tmavá karta). JSON pole z repeateru jako
| ostatní sekce, prázdné = sekce se nezobrazí.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('packages_intro')->nullable()->after('steps');
            $table->json('packages')->nullable()->after('packages_intro');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['packages_intro', 'packages']);
        });
    }
};
