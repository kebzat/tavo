<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loga klientů na homepage. Samotný obrázek leží v media knihovně
 * (kolekce `logo`), tady je jen název, pořadí a zveřejnění.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_logos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->string('name');
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_logos');
    }
};
