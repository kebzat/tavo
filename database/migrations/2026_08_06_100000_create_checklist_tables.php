<?php

/*
|--------------------------------------------------------------------------
| Interní nástroj: technické checklisty klientských webů
|--------------------------------------------------------------------------
| Web Taveo je jinak jednonájemní prezentace bez pojmu „klient". Tyhle tabulky
| stojí stranou obsahu webu a slouží pracovnímu nástroji v panelu /nastroje:
| držíme jednu univerzální šablonu a z ní klonujeme checklist pro každého
| klienta zvlášť.
|
| Šablona i klientský checklist jsou jeden a týž tvar dat, liší se jen
| příznakem `is_template`. Díky tomu je klonování prostá kopie řádků.
|
| Hierarchie: checklist → kategorie → sekce → položka.
| Kategorie je rozcestník (karta na úvodní stránce), sekce je podnadpis
| uvnitř kategorie. Bez sekcí by tabulka o třiceti položkách byla nečitelná,
| bez kategorií by byl rozcestník příliš dlouhý.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website_url')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('note')->nullable();                  // interní, klient ji nikdy nevidí
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_template')->default(false)->index();
            $table->string('name');
            $table->text('intro')->nullable();                 // úvodní odstavec na sdílené stránce
            $table->string('public_token', 40)->nullable()->unique();
            $table->boolean('is_public')->default(false);      // bez zapnutí odkaz vrací 404
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('checklist_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');                            // adresa /checklist/{token}/{slug}
            $table->text('description')->nullable();           // popisek na kartě rozcestníku
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->timestamps();

            // Slug stačí unikátní v rámci jednoho checklistu, ne globálně.
            $table->unique(['checklist_id', 'slug']);
        });

        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();

            // Odkaz na checklist navíc, i když se dá dopočítat přes sekci a kategorii.
            // Eloquent umí protáhnout vztah jen přes jednu mezitabulku, takže bez něj
            // by se progres a souhrnná tabulka v administraci sestavovaly ručním joinem.
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();           // vysvětlivka, klient si ji rozbalí
            $table->text('internal_note')->nullable();         // jen pro nás, ven se nevykresluje
            $table->string('priority', 20)->default('must');   // App\Enums\ChecklistPriority
            $table->string('status', 20)->default('todo');     // App\Enums\ChecklistItemStatus
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_sections');
        Schema::dropIfExists('checklist_categories');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('clients');
    }
};
