<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projekt agenta na serveru, který patří klientovi (docs/AGENT.md).
 * Jen název projektu: seznam projektů drží server, ne databáze webu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('agent_project', 60)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('agent_project');
        });
    }
};
