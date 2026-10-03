<?php

/*
|--------------------------------------------------------------------------
| Potenciální spolupráce: vlastní nadpis akčních kroků
|--------------------------------------------------------------------------
| „Akční kroky v prvních týdnech“ je statický text společný všem nabídkám.
| U plánu po měsících se hodí jiný nadpis, tak jde přepsat u konkrétní
| stránky. Prázdné pole = platí statický text.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('steps_title')->nullable()->after('recommendations');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('steps_title');
        });
    }
};
