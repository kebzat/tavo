<?php

/*
|--------------------------------------------------------------------------
| Interní nástroj: audity klientských webů
|--------------------------------------------------------------------------
| Audit je dlouhý dokument s nálezy (SEO, GEO, technika), který klient dostane
| odkazem a může se k němu vracet. Checklist vedle něj říká, co s nálezy dělat.
| Oba patří ke klientovi a na sdílené stránce na sebe odkazují.
|
| Text je v Markdownu. Audit je hlavně tabulky a nadpisy, na to je Markdown
| pohodlnější než rich-text editor a převod z podkladů je přímočarý.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('audited_at')->nullable();           // stav k datu, ukazuje se v hlavičce
            $table->text('intro')->nullable();                // perex v tmavé hlavičce
            $table->json('highlights')->nullable();           // dlaždice s čísly pod hlavičkou
            $table->longText('body')->nullable();             // Markdown, viz App\Support\AuditMarkdown
            $table->string('public_token', 40)->nullable()->unique();
            $table->boolean('is_public')->default(false);     // bez zapnutí odkaz vrací 404
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
