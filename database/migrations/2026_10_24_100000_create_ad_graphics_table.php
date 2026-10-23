<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reklamní grafika od agenta (docs/AGENT.md, Reklamní grafika).
 *
 * - ad_graphics: jedno zadání (formáty, styl, podklady) = jedna úloha agenta.
 *   Úprava hotových návrhů je nové zadání s parent_id, agent naváže na stejnou
 *   konverzaci.
 * - agent_jobs: úlohy už nejsou jen z ClickUpu (source = grafika), proto
 *   úkol a komentář ClickUpu smí chybět.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_graphics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ad_graphics')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project', 60);
            $table->string('title');
            $table->text('brief');
            $table->text('instruction')->nullable();        // pokyn k úpravě (jen u navazujících zadání)
            $table->json('formats');                        // [{label, width, height}]
            $table->unsignedTinyInteger('variants')->default(5);
            $table->boolean('every_format')->default(false); // každý návrh ve všech formátech
            $table->string('website_url')->nullable();
            $table->string('fonts')->nullable();
            $table->json('colors')->nullable();
            $table->text('inspiration')->nullable();
            $table->json('assets')->nullable();             // nahrané podklady (cesty na disku local)
            $table->boolean('ai_images')->default(false);
            $table->unsignedSmallInteger('ai_images_used')->default(0);
            $table->string('token_hash', 64)->nullable();   // jednorázový přístup agenta k podkladům a obrázkům
            $table->string('status', 20)->default('queued'); // queued, running, done, failed
            $table->string('figma_url')->nullable();
            $table->json('outputs')->nullable();            // [{soubor, nazev, format, text}]
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->string('clickup_task_id', 40)->nullable()->change();
            $table->string('clickup_comment_id', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_graphics');
    }
};
