<?php

/*
|--------------------------------------------------------------------------
| Potenciální spolupráce: Kdysi, dnes a s námi
|--------------------------------------------------------------------------
| Vedle sebe tři podoby webu: jak vypadal dřív, jak vypadá dnes a jak by mohl
| vypadat s námi. Stejně jako ostatní sekce je to JSON pole z repeateru.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('timeline_intro')->nullable()->after('highlights');
            $table->json('timeline')->nullable()->after('timeline_intro');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['timeline_intro', 'timeline']);
        });
    }
};
