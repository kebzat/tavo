<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatizace klientů (zatím připomínky vyzvednutí zásilek v Upgates)
 * a zprávy, které kvůli nim odešly. Bez osobních údajů zákazníků: jen
 * čísla objednávek, dopravce a data. Viz docs/AUTOMATIZACE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->text('order_export_url')->nullable();
            $table->string('import_token', 40)->unique();
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->text('last_warning')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 40);
            $table->string('kind', 20);
            $table->string('target_status', 100);
            $table->string('state', 20)->default('pending');
            $table->date('stored_until')->nullable();
            $table->string('carrier', 40)->nullable();
            $table->string('detail', 500)->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'order_number', 'kind']);
            $table->index(['automation_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_events');
        Schema::dropIfExists('automations');
    }
};
