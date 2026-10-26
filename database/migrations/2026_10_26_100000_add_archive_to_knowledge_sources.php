<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podklady mozku jsou zároveň archiv klienta (docs/MOZEK.md, Archiv klienta):
 * druh dokumentu, k jakému datu platí, popis obsahu od knihovníka a jestli
 * z nich má agent dělat poznatky, nebo je jen uložit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('client_id');
            $table->date('document_date')->nullable()->after('category');
            $table->text('summary')->nullable()->after('note');
            $table->boolean('learn')->default(true)->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->dropColumn(['category', 'document_date', 'summary', 'learn']);
        });
    }
};
