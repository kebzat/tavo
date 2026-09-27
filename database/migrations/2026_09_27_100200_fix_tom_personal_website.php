<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tom má u sebe v „Lidech" pořád starou adresu juliatom.cz. Ta se propisuje
 * do strukturovaných dat (founder, sameAs) a nově i do odkazu „Osobní web".
 * Jeho web je tomaskebza.cz. Mění se jen ta stará hodnota, nic jiného.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('founders')
            ->whereIn('external_url', ['https://juliatom.cz/', 'https://juliatom.cz', 'https://www.juliatom.cz/'])
            ->update(['external_url' => 'https://tomaskebza.cz/']);
    }

    public function down(): void
    {
        // Starou adresu nevracíme.
    }
};
