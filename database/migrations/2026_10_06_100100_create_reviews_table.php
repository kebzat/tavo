<?php

/*
|--------------------------------------------------------------------------
| Kontrola před odesláním: „Pavel – zkontrolováno“, „Tom – zkontrolováno“
|--------------------------------------------------------------------------
| Audit, nabídka, checklist i report odchází klientovi. Každý z kontrolujících
| si u nich odškrtne svou kontrolu, ať je vidět, co kdo už prošel.
|
| Když se obsah po kontrole změní, kontrola nezmizí, jen dostane outdated_at.
| V tabulce pak svítí oranžově: prošel to, ale ve starší podobě.
|
| Kdo kontroluje, určuje users.is_reviewer. Zapíná se v administraci
| u uživatele, výchozí jsou zakladatelé.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('reviewable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('reviewed_at');
            $table->dateTime('outdated_at')->nullable();
            $table->timestamps();

            $table->unique(['reviewable_type', 'reviewable_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false)->after('role');
        });

        DB::table('users')->whereIn('email', ['tom@taveo.cz', 'pavel@taveo.cz'])->update(['is_reviewer' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });

        Schema::dropIfExists('reviews');
    }
};
