<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Dvoufázové ověření přes aplikaci (Google Authenticator apod.)
|--------------------------------------------------------------------------
|
| Tajný klíč je šifrovaný APP_KEY, záložní kódy jsou navíc hashované.
| Oba sloupce plní Filament při nastavení v profilu. Viz docs/SECURITY.md.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
