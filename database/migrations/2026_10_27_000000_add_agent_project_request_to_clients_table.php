<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projekt agenta pro klienta se zakládá na pozadí (`agent:projekty-klientu`),
 * ne v kliknutí. `agent_project_request` = stav žádosti (čeká, nepovedlo se a proč).
 *
 * 26. 10. 2026 se při Načíst z ClickUpu založilo jen 5 projektů, pak se fronta
 * na serveru zastavila. Všichni aktivní klienti bez projektu proto rovnou čekají.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('agent_project_request')->nullable()->after('agent_project');
        });

        DB::table('clients')->where('is_archived', false)->whereNull('agent_project')->update([
            'agent_project_request' => json_encode(['status' => 'queued', 'requested_at' => now()->toIso8601String()]),
        ]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('agent_project_request');
        });
    }
};
