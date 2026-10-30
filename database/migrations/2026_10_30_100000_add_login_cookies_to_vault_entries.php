<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cookies z posledního přihlášení (šifrované): administrace pozná známé zařízení a nepošle e-mail „Byli jste to Vy?“. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_entries', function (Blueprint $table) {
            $table->text('login_cookies')->nullable()->after('totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('vault_entries', function (Blueprint $table) {
            $table->dropColumn('login_cookies');
        });
    }
};
