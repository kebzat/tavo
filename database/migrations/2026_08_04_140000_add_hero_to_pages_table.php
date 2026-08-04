<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hlavička statické stránky byla jen nadpis a perex. To stačí na právní texty,
 * ale dopadová stránka, na kterou chodí lidi z e-mailu, musí zaujmout dřív, než
 * stihnou zavřít záložku.
 *
 * Pole jsou nepovinná: bez nich se vysází přesně ta hlavička co dosud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('hero_eyebrow')->nullable()->after('title');
            $table->string('hero_accent')->nullable()->after('hero_eyebrow');
            $table->boolean('hero_cta')->default(false)->after('hero_accent');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['hero_eyebrow', 'hero_accent', 'hero_cta']);
        });
    }
};
