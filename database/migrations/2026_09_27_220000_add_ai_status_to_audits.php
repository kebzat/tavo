<?php

/*
|--------------------------------------------------------------------------
| Podrobný audit od Clauda
|--------------------------------------------------------------------------
| Claude si projde web a přepíše koncept auditu. Trvá to pár minut a běží
| to po odeslání odpovědi, takže audit potřebuje vědět, v jakém stavu
| přepis je, a administrace to ukáže.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('ai_status', 20)->nullable()->after('is_teaser');  // running | done | failed
            $table->text('ai_note')->nullable()->after('ai_status');           // cena a počet stránek, nebo chyba
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn(['ai_status', 'ai_note']);
        });
    }
};
