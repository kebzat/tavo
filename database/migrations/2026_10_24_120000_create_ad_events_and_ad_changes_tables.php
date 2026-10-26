<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalendář termínů a historie zásahů v reklamách (docs/ADS.md).
 *
 * - ad_events: konec licence UGC, začátek akce, příprava Black Friday, konec
 *   kampaně, čekání na podklady. Upozorní předem a v ranním přehledu visí,
 *   dokud ho někdo neoznačí jako vyřešený. Reklamu vypíná Pavel ručně.
 * - ad_changes: co jsme v účtu změnili (rozpočet, kreativa, nabídka, web).
 *   Ukazuje se v grafu, v reportu („Co jsme změnili“) a agent z toho vychází
 *   při komentování výsledků.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ad_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);                            // App\Enums\Ads\AdEventType
            $table->string('title');
            $table->date('due_on');                                 // den, kdy se to stane (konec licence, start akce)
            $table->unsignedSmallInteger('remind_days')->default(7); // kolik dní předem upozornit
            $table->date('remind_on');                              // due_on − remind_days, dopočítá model
            $table->string('ads')->nullable();                      // konkrétní reklamy nebo videa, volným textem
            $table->string('terms_url', 500)->nullable();           // podmínky licence
            $table->json('links')->nullable();                      // [{label, url}] videa, podklady
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['resolved_at', 'remind_on']);
        });

        Schema::create('ad_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ad_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->date('changed_on');
            $table->string('type', 30);                            // App\Enums\Ads\AdChangeType
            $table->text('description');
            $table->string('expected')->nullable();                 // co od změny čekáme, ať se dá později vyhodnotit
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'changed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_changes');
        Schema::dropIfExists('ad_events');
    }
};
