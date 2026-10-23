<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rozhovor s agentem ve vlákně komentáře v ClickUpu.
 *
 * - clickup_thread_id: hlavní komentář vlákna. Agent odpovídá vždy do něj,
 *   odpověď na odpověď by ClickUp schoval do vnořeného vlákna.
 * - is_followup: zpráva ve vlákně s agentem bez spouštěče, navazuje na
 *   konverzaci jako v chatu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->string('clickup_thread_id', 40)->nullable()->after('clickup_comment_id')->index();
            $table->boolean('is_followup')->default(false)->after('clickup_thread_id');
        });

        DB::table('agent_jobs')->whereNull('clickup_thread_id')->update(['clickup_thread_id' => DB::raw('clickup_comment_id')]);
    }

    public function down(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->dropIndex(['clickup_thread_id']);
            $table->dropColumn(['clickup_thread_id', 'is_followup']);
        });
    }
};
