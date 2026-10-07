<?php

/*
|--------------------------------------------------------------------------
| Přehled pro klienta: čitelná adresa
|--------------------------------------------------------------------------
| Místo /klient/{40 náhodných znaků} je /klient/svet-cejlonu-k7f2q9.
| Šest náhodných znaků na konci zůstává, protože přehled ukazuje peníze
| a hodiny a podle názvu firmy se adresa nesmí dát uhodnout.
|
| Starý token platí dál a přesměruje na novou adresu. Klienti mají staré
| odkazy v e-mailech.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('dashboard_slug')->nullable()->unique()->after('dashboard_token');
        });

        foreach (DB::table('clients')->get(['id', 'name']) as $client) {
            DB::table('clients')->where('id', $client->id)->update([
                'dashboard_slug' => Str::limit(Str::slug($client->name), 60, '').'-'.Str::lower(Str::random(6)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['dashboard_slug']);
            $table->dropColumn('dashboard_slug');
        });
    }
};
