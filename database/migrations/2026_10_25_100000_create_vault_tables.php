<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trezor přístupů (docs/TREZOR.md): hesla a klíče klientů zašifrované
 * klíčem aplikace, historie použití a soubory, které agent nahrává přes FTP.
 * Agent hesla nikdy nedostane, práci s nimi za něj dělá web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            $table->text('secret')->nullable();
            $table->text('app_password')->nullable();
            $table->text('totp_secret')->nullable();
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();
            $table->string('agent_access', 20)->default('none');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vault_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_entry_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->json('files');
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'vault_entry_id']);
        });

        Schema::create('vault_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_name');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor');
            $table->string('action', 40);
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vault_entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_logs');
        Schema::dropIfExists('vault_uploads');
        Schema::dropIfExists('vault_entries');
    }
};
