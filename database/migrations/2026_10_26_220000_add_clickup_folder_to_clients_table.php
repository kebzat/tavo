<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klient = složka v ClickUpu (Projekty PAVELVCELIS.cz, Projekty TAVEO.cz).
 * `@agent` u úkolu z kteréhokoli seznamu složky jde do projektu klienta.
 * `clickup_import` = stav načtení složky do archivu (stav, kdy, co se nepovedlo).
 * `knowledge_sources.external_ref` = odkud dokument přišel („clickup:list:123“),
 * opakované načtení ho přepíše místo založení dalšího.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('clickup_folder_id', 40)->nullable()->unique()->after('clickup_list_id');
            $table->json('clickup_import')->nullable()->after('clickup_folder_id');
        });

        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->string('external_ref', 100)->nullable()->index()->after('page_key');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->dropIndex(['external_ref']);
            $table->dropColumn('external_ref');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['clickup_folder_id']);
            $table->dropColumn(['clickup_folder_id', 'clickup_import']);
        });
    }
};
