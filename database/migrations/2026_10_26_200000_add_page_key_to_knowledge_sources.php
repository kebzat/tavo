<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HTML dokument v archivu klienta (rozbor, plán) má stálou stránku ke čtení
 * na /stranka/{page_key}. Klíč vznikne, až ho někdo poprvé otevře.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->string('page_key', 100)->nullable()->unique()->after('files');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->dropUnique(['page_key']);
            $table->dropColumn('page_key');
        });
    }
};
