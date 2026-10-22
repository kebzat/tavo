<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Na Metu (a další platformy) sahá jen ranní `ads:sync`, všechno ostatní čte
 * z databáze (docs/ADS.md, Jediný vstup na platformy).
 *
 * - ad_shared_accounts: účty, které nám klienti nasdíleli. Ranní stažení je
 *   zapíše jedním dotazem a nabídka při propojování čte odsud.
 * - ad_accounts.backfill_from: historie, kterou má ranní stažení doplnit
 *   (nový účet, nebo tlačítko „Doplnit starší historii“).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_shared_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('external_id');
            $table->string('name');
            $table->string('currency', 3)->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->nullable();
            $table->string('business')->nullable();
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
        });

        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->date('backfill_from')->nullable()->after('last_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn('backfill_from');
        });

        Schema::dropIfExists('ad_shared_accounts');
    }
};
