<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telefon k zakladateli.
 *
 * Web měl dosud jedno obecné číslo v nastavení kontaktů a tlačítko „Zavolat“.
 * Návštěvník, kterému hoří termín, ale nevolá firmě, volá člověku. Číslo proto
 * bydlí u toho, komu patří: Pavel řeší reklamu, Tom weby, a kdo je na řadě,
 * si volající vybere sám.
 *
 * Čísla doplňujeme jen tam, kde pole zůstalo prázdné, ať migrace nepřepíše
 * to, co si někdo mezitím upravil v administraci.
 */
return new class extends Migration
{
    private const PHONES = [
        'Pavel' => '+420 730 672 744',
        'Tom' => '+420 603 732 263',
    ];

    public function up(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('role_label');
        });

        foreach (self::PHONES as $name => $phone) {
            DB::table('founders')
                ->where('name', $name)
                ->whereNull('phone')
                ->update(['phone' => $phone]);
        }
    }

    public function down(): void
    {
        Schema::table('founders', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
