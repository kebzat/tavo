<?php

use App\Support\ContentSettingsMigration;

/**
 * Pruh s čísly po připomínkách (27. 9. 2026):
 * - „9 let": Pavel i Tom mají devět let praxe (Tom to potvrdil sám, Pavel
 *   to uvádí na pavelvcelis.cz).
 * - Hodnocení už není „Pavlovo", ale hodnocení klientů na Googlu. Zdroj je
 *   pořád stejný (5 z 5 na profilu Pavla), jen je to podané jako to, co to je:
 *   co o nás napsali klienti.
 *
 * Přepisuje jen tehdy, když v databázi pořád stojí původní znění z migrace
 * 2026_09_27_100000_trust_bar. Co mezitím upravil správce, zůstane.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->replaceIfUntouched('home.trust_items', [
            ['value' => '8+ let', 'label' => 'praxe každého z nás'],
            ['value' => '2 dny', 'label' => 'než se ozveme na poptávku'],
            ['value' => '5 z 5', 'label' => 'hodnocení Pavla na Googlu'],
            ['value' => '2 lidé', 'label' => 'se kterými jednáte napřímo'],
        ], [
            ['value' => '9 let', 'label' => 'zkušeností každého z nás'],
            ['value' => '2 dny', 'label' => 'než se ozveme na poptávku'],
            ['value' => '5,0 ★', 'label' => 'hodnocení klientů na Googlu'],
            ['value' => '2 lidé', 'label' => 'se kterými jednáte napřímo'],
        ]);
    }
};
