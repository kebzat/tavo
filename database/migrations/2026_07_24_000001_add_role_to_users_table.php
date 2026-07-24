<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hodnoty schválně natvrdo, ne přes App\Enums\UserRole — migrace musí
        // dát stejný výsledek i za rok, kdy může enum vypadat jinak.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('editor')->after('email');
        });

        // Dosud byl každý účet plnohodnotný správce. Kdyby všichni spadli
        // na výchozího redaktora, nikdo by se nedostal ke správě uživatelů.
        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
