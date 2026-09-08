<?php

use App\Support\WebTexts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statické texty webu, které nemají vlastní pole v nastavení.
 *
 * Nadpisy a perexy výpisů, popisky tlačítek, hlášky formuláře. Do teď byly
 * natvrdo v šablonách a každá úprava znamenala commit a nasazení. Tabulka je
 * jen přepis: šablona zůstává zdrojem výchozího znění, tady se drží to, co si
 * správce přepsal v administraci.
 *
 * @see WebTexts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_texts', function (Blueprint $table) {
            $table->id();
            // Klíč z šablony, např. `reference.perex`.
            $table->string('key')->unique();
            // Skupina do administrace, ať texty nejsou v jedné dlouhé řadě.
            $table->string('group')->nullable()->index();
            // Nápověda, kde na webu text je.
            $table->string('note')->nullable();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_texts');
    }
};
