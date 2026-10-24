<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Poslední chyba generování obrázků u zadání, ať ji vidí člověk, ne jen agent. */
    public function up(): void
    {
        Schema::table('ad_graphics', function (Blueprint $table) {
            $table->text('image_error')->nullable()->after('ai_images_used');
        });
    }

    public function down(): void
    {
        Schema::table('ad_graphics', function (Blueprint $table) {
            $table->dropColumn('image_error');
        });
    }
};
