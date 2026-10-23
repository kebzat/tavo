<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zveřejnění hotové práce agenta z nástrojů: větev agent/… → pull request
 * → testy → sloučení do main → nasazení. Jeden řádek na jedno kliknutí
 * na Zveřejnit (nebo Vrátit). Viz docs/AGENT.md, Zveřejnění.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_publications', function (Blueprint $table) {
            $table->id();
            $table->string('project', 60);
            $table->string('repo');
            $table->string('branch');
            // Commit, který člověk zkontroloval. Sloučí se právě tenhle, ne novější.
            $table->string('sha', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedInteger('pr_number')->nullable();
            $table->string('pr_node_id')->nullable();
            $table->string('merge_sha', 40)->nullable();
            $table->text('error')->nullable();
            $table->string('details_url')->nullable();
            // Vrácení dřívějšího zveřejnění (revert pull request na GitHubu).
            $table->foreignId('reverts_id')->nullable()->constrained('agent_publications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('pr_opened_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_publications');
    }
};
