<?php

/*
|--------------------------------------------------------------------------
| Paušál: předběžná částka do budoucna
|--------------------------------------------------------------------------
| „Teď 30 000 na web, od ledna asi 10 000.“ Změna částky je nový řádek
| s datem Od. Když ještě není domluvená, je předběžná: počítá se jen do
| Výhledu, nefakturuje se a klient ji v přehledu nevidí. Až se domluví,
| předběžnost se vypne.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_retainers', function (Blueprint $table) {
            $table->boolean('is_tentative')->default(false)->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('client_retainers', function (Blueprint $table) {
            $table->dropColumn('is_tentative');
        });
    }
};
