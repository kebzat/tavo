<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report na míru (zpětná vazba Pavla, docs/ADS.md, Reporty):
 *
 * - ad_campaign_group_id: report jen za skupinu kampaní,
 * - worked / not_worked: co fungovalo a co ne (vedle faktů, příčin a doporučení),
 * - approved_at / approved_by: schválená verze má zamčená čísla, po aktualizaci
 *   dat z Mety se tiše nezmění.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_reports', function (Blueprint $table) {
            $table->foreignId('ad_campaign_group_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->text('worked')->nullable()->after('causes');
            $table->text('not_worked')->nullable()->after('worked');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_campaign_group_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['worked', 'not_worked', 'approved_at']);
        });
    }
};
