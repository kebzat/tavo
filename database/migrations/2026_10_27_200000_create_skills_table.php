<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skilly TAVEO (docs/MOZEK.md, Skilly): specialisté na jednu oblast (Meta Ads,
 * copywriting, onboarding…). Každý má pokyny, svého agenta (projekt bez kódu,
 * kde se učí) a v mozku vlastní poznatky a archiv (`skill_id`). Agenti klientů
 * je volají jako `/meta-ads`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->string('category', 20);
            $table->text('description');
            $table->text('instructions')->nullable();
            $table->json('builds_on')->nullable();
            $table->string('agent_project', 41)->nullable()->unique();
            $table->json('agent_project_request')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('skill_id');
        });

        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('skill_id');
        });

        Schema::dropIfExists('skills');
    }
};
