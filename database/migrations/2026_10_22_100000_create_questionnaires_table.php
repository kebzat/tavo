<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dotazníky: sada otázek na sdíleném odkazu, odpovědi se ukládají průběžně.
 * Jeden záznam = jeden člověk, který odpovídá (Pavel, klient před spoluprací).
 * Otázky i odpovědi jsou JSON, odpovědi se párují přes klíč otázky.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('respondent')->nullable();
            $table->string('slug')->unique();
            $table->text('intro')->nullable();
            $table->json('sections')->nullable();
            $table->json('answers')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires');
    }
};
