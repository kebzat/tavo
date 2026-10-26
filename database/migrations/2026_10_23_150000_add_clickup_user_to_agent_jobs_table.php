<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kdo agentovi v ClickUpu psal. Závěrečná odpověď ho označí, ať mu přijde upozornění. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->string('clickup_user_id', 40)->nullable()->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->dropColumn('clickup_user_id');
        });
    }
};
