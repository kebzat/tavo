<?php

/*
|--------------------------------------------------------------------------
| Interní nástroj: potenciální spolupráce
|--------------------------------------------------------------------------
| Dopadová stránka pro konkrétní firmu, kterou chceme získat: co jsme na jejím
| webu a v marketingu našli, co doporučujeme a co bychom udělali v prvních
| týdnech. Mezi sekcemi ukázky, co umíme (návrh webu, obsah od konkurence).
|
| Sekce jsou JSON pole z repeaterů v administraci. Každá má pevnou strukturu
| a svou šablonu, volné skládání bloků by tu nic nepřineslo.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');                   // „IQ Hračky“, nad nadpisem a v záhlaví
            $table->string('slug')->unique();                 // /potencialni-spoluprace/{slug}
            $table->string('title');
            $table->text('intro')->nullable();
            $table->date('prepared_at')->nullable();
            $table->json('highlights')->nullable();           // dlaždice s čísly pod úvodem
            $table->text('findings_intro')->nullable();
            $table->json('findings')->nullable();             // co jsme objevili
            $table->text('recommendations_intro')->nullable();
            $table->json('recommendations')->nullable();      // co doporučujeme
            $table->text('steps_intro')->nullable();
            $table->json('steps')->nullable();                // akční kroky v prvních týdnech
            $table->json('examples')->nullable();             // ukázky mezi sekcemi
            $table->json('principles')->nullable();           // jak k tomu přistupujeme
            $table->boolean('is_public')->default(false);     // bez zapnutí vidí stránku jen přihlášený
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
