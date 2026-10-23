<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upozornění jako pracovní seznam (zpětná vazba Pavla, docs/ADS.md):
 *
 * - ad_campaign_group_id: upozornění skupiny kampaní („Prodej: cena za nákup nad cílem“),
 * - category: data (stahování, měření, stránky) / performance (výkon proti cíli),
 * - facts a basis: co se změnilo proti čemu a z jakého množství dat,
 * - snoozed_until: odložené do data, do té doby nesvítí,
 * - waiting_for: na co čekáme u stavu „Čekám na klienta“,
 * - occurrences a recovered_on: opakovaný problém je jedno upozornění,
 *   vrácení do normálu se zapíše,
 * - resolved_by: system (pravidlo přestalo platit) / user,
 * - history: krátký deník stavů.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_alerts', function (Blueprint $table) {
            $table->foreignId('ad_campaign_group_id')->nullable()->after('ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20)->default('performance')->after('rule');
            $table->text('facts')->nullable()->after('title');
            $table->string('basis')->nullable()->after('facts');
            $table->date('snoozed_until')->nullable()->after('status');
            $table->string('waiting_for')->nullable()->after('snoozed_until');
            $table->unsignedSmallInteger('occurrences')->default(1)->after('last_seen_on');
            $table->date('recovered_on')->nullable()->after('resolved_at');
            $table->string('resolved_by', 10)->nullable()->after('recovered_on');
            $table->json('history')->nullable()->after('snapshot');
        });

        DB::table('ad_alerts')
            ->whereIn('rule', ['sync_failed', 'account_status', 'measurement_value', 'measurement_duplicates', 'analytics_mismatch', 'ad_issues', 'landing_page', 'landing_pixel', 'lead_feedback'])
            ->update(['category' => 'data']);
    }

    public function down(): void
    {
        Schema::table('ad_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_campaign_group_id');
            $table->dropColumn(['category', 'facts', 'basis', 'snoozed_until', 'waiting_for', 'occurrences', 'recovered_on', 'resolved_by', 'history']);
        });
    }
};
