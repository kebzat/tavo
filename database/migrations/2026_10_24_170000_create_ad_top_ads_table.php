<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Čísla jednotlivých reklam za posledních 30 dní (přehled nejlepších
 * reklam a kreativ). Ranní stažení je jedním dotazem na účet přepíše,
 * nejvýš 50 reklam s největší útratou. Historie po dnech se pro reklamy
 * neukládá, na srovnání kreativ stačí součty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_top_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('campaign_external_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('link_clicks')->default(0);
            $table->decimal('purchases', 12, 2)->default(0);
            $table->decimal('purchase_value', 14, 2)->default(0);
            $table->decimal('leads', 12, 2)->default(0);
            $table->decimal('engagements', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['ad_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_top_ads');
    }
};
