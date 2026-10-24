<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mozek TAVEO (docs/MOZEK.md): podklady (surové texty a soubory, ze kterých
 * agent dělá poznatky) a poznatky (krátké schválené zkušenosti, které čtou
 * agenti při práci).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('body')->nullable();
            $table->json('files')->nullable();
            $table->text('note')->nullable();
            $table->string('author')->nullable();
            $table->string('status', 20)->default('queued');
            $table->foreignId('agent_job_id')->nullable()->constrained()->nullOnDelete();
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('layer', 20);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('tags')->nullable();
            $table->boolean('always')->default(false);
            $table->string('status', 20)->default('proposed');
            $table->string('origin')->nullable();
            $table->string('origin_type', 20)->default('rucne');
            $table->foreignId('knowledge_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('knowledge_entries')->nullOnDelete();
            $table->string('proposed_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'layer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
        Schema::dropIfExists('knowledge_sources');
    }
};
