<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dopadové stránky s nabídkami pro e-shopy (/mereni-pro-eshopy, /rozvoj-eshopu…).
 * Dřív bydlely v kódu (App\Support\EshopOffers), teď se edituje v administraci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eshop_offers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nav_label');
            $table->string('headline');
            // Odstavce oddělené prázdným řádkem.
            $table->text('intro')->nullable();
            // [{title, text}], text = odstavce oddělené prázdným řádkem
            $table->json('sections')->nullable();
            // [{question, answer}], zdroj i pro JSON-LD FAQPage
            $table->json('faq')->nullable();
            $table->string('cta_title')->nullable();
            $table->string('cta_perex')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('service_type')->nullable();
            $table->boolean('published')->default(true);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eshop_offers');
    }
};
