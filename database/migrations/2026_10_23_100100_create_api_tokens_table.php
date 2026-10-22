<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Přístupy agentů ke strojovému rozhraní CRM
|--------------------------------------------------------------------------
|
| Každý agent (skill, automatizace) má vlastní token s výčtem toho, co smí.
| V databázi je jen SHA-256 otisk, samotný token se ukáže jednou při
| založení. api_token_calls je historie volání, aby šlo dohledat, kdo co
| nahrál. Viz docs/SECURITY.md.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_token_calls', function (Blueprint $table) {
            $table->id();
            // Prázdné = starý společný token z .env (CRM_IMPORT_TOKEN).
            $table->foreignId('api_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('endpoint', 100);
            $table->string('ip', 45)->nullable();
            $table->unsignedSmallInteger('status');
            $table->dateTime('called_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_calls');
        Schema::dropIfExists('api_tokens');
    }
};
