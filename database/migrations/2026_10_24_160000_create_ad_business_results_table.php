<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skutečné obchodní výsledky e-shopu (objednávky a tržby z administrace
 * e-shopu), zapsané ručně, dokud nemáme napojení na Shoptet a Upgates.
 * Ukazují se vedle čísel, která si připsaly reklamní systémy: konverze
 * z Mety a Googlu se nedají sečíst a nejsou to tržby (docs/ADS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_business_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('orders')->nullable();
            $table->decimal('revenue', 14, 2)->nullable();           // tržby s DPH, jak je ukazuje e-shop
            $table->string('source')->nullable();                     // Shoptet, Upgates, účetnictví…
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_business_results');
    }
};
