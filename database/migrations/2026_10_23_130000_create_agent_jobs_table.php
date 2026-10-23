<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Úlohy pro agenta z ClickUpu (docs/AGENT.md, ClickUp).
 *
 * - clients.clickup_list_id: seznam v ClickUpu, který patří klientovi. Úkol
 *   z něj jde do projektu agenta klienta.
 * - agent_jobs: jeden komentář „@agent …“ = jedna úloha. Komentář je unikátní,
 *   ClickUp někdy webhook pošle dvakrát a druhý běh by se nesměl spustit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('clickup_list_id', 40)->nullable()->after('agent_project')->index();
        });

        Schema::create('agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('clickup');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project', 60)->nullable();
            $table->string('clickup_task_id', 40)->index();
            $table->string('clickup_comment_id', 40)->unique();
            $table->string('clickup_list_id', 40)->nullable();
            $table->string('task_name')->nullable();
            $table->string('task_url')->nullable();
            $table->string('author')->nullable();
            $table->text('instruction');
            $table->uuid('session')->nullable();                 // konverzace Clauda, další @agent v úkolu na ni naváže
            $table->string('status', 20)->default('queued');     // queued, running, done, failed, rejected
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->json('reply_comment_ids')->nullable();       // naše odpovědi v ClickUpu, ať je webhook nespustí znovu
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_jobs');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['clickup_list_id']);
            $table->dropColumn('clickup_list_id');
        });
    }
};
