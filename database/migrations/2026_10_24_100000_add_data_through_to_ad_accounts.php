<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spolehlivost čísel v reklamách (docs/ADS.md, Čemu věřit):
 *
 * - data_through: poslední den, za který máme čísla účtu kompletně stažená.
 *   Den po něm není nula, ale „nemáme data“.
 * - refresh_requested_at: ruční obnova z detailu klienta. Odsud se na Metu
 *   nesahá, žádost vyřídí `ads:sync --requested` do pár minut.
 * - refreshed_at: kdy proběhla poslední ruční obnova, kvůli časovému omezení.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->date('data_through')->nullable()->after('last_sync_error');
            $table->timestamp('refresh_requested_at')->nullable()->after('backfill_from');
            $table->timestamp('refreshed_at')->nullable()->after('refresh_requested_at');
        });

        // Dosavadní účty: konec posledního úspěšného stažení.
        $through = DB::table('ad_sync_runs')
            ->where('status', 'ok')
            ->groupBy('ad_account_id')
            ->selectRaw('ad_account_id, max(date_to) as through')
            ->pluck('through', 'ad_account_id');

        foreach ($through as $accountId => $date) {
            DB::table('ad_accounts')->where('id', $accountId)->update(['data_through' => substr((string) $date, 0, 10)]);
        }
    }

    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn(['data_through', 'refresh_requested_at', 'refreshed_at']);
        });
    }
};
