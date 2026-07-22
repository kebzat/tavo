<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_study_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->string('number', 8)->nullable();          // „01", „02"…
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();              // text v seznamu na homepage
            $table->boolean('has_detail_page')->default(false);
            $table->boolean('published')->default(true)->index();

            // Detailní stránka služby
            $table->string('hero_eyebrow')->nullable();
            $table->string('hero_headline')->nullable();
            $table->string('hero_headline_accent')->nullable();
            $table->text('hero_perex')->nullable();
            $table->string('target_group_title')->nullable();
            $table->json('target_groups')->nullable();        // [{text}]
            $table->string('offerings_title')->nullable();
            $table->json('offerings')->nullable();            // [{title, text}]
            $table->string('process_title')->nullable();
            $table->json('process_steps')->nullable();        // [{number, title, text}]

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->foreignId('case_study_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('published')->default(true)->index();

            // Výpis
            $table->string('eyebrow')->nullable();            // „E-commerce · Redesign + kampaně"
            $table->string('thumb_label')->nullable();        // popisek placeholderu, dokud není foto
            $table->text('excerpt')->nullable();
            $table->string('headline_metric')->nullable();    // „+41 %" v gridu
            $table->json('tags')->nullable();                 // [{text}]

            // Detail
            $table->string('hero_headline')->nullable();
            $table->string('hero_headline_accent')->nullable();
            $table->text('hero_perex')->nullable();
            $table->string('client')->nullable();
            $table->string('industry')->nullable();
            $table->string('scope')->nullable();
            $table->string('duration')->nullable();
            $table->string('problem_title')->nullable();
            $table->text('problem_text')->nullable();
            $table->json('problem_points')->nullable();       // [{text}]
            $table->string('roles_title')->nullable();
            $table->text('roles_perex')->nullable();
            $table->string('marketing_title')->nullable();
            $table->json('marketing_items')->nullable();      // [{text}]
            $table->string('dev_title')->nullable();
            $table->json('dev_items')->nullable();            // [{text}]
            $table->json('results')->nullable();              // [{value, label}]
            $table->text('quote')->nullable();
            $table->string('quote_author')->nullable();
            $table->text('disclaimer')->nullable();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('process_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->string('number', 8)->nullable();
            $table->string('title');
            $table->text('text')->nullable();
            $table->boolean('highlight')->default(false);     // poslední krok má cihlovou linku
            $table->timestamps();
        });

        Schema::create('founders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->string('name');
            $table->string('role_label')->nullable();         // „Marketing & růst"
            $table->text('bio')->nullable();
            $table->json('tags')->nullable();                 // [{text}]
            $table->string('external_url')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('perex')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('published')->default(true)->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('topic')->nullable();
            $table->string('budget')->nullable();
            $table->text('message');
            $table->string('status')->default('new')->index(); // new | in_progress | won | lost
            $table->text('note')->nullable();
            $table->string('source_url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('founders');
        Schema::dropIfExists('process_steps');
        Schema::dropIfExists('case_studies');
        Schema::dropIfExists('services');
        Schema::dropIfExists('case_study_categories');
    }
};
