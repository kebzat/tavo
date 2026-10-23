<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chyba zveřejnění skrytá křížkem v kartě Hotová práce: rovnou do historie,
 * ne až po hodině.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_publications', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('agent_publications', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
