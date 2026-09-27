<?php

use App\Support\ContentSettingsMigration;

/**
 * Pruh s čísly pod úvodem homepage (návrh marketingu, 27. 9. 2026).
 *
 * Jen čísla, která už Pavel a Tom veřejně uvádí na svých webech:
 * - 8+ let: Tom „8+ let ve vývoji webů" (tomaskebza.cz), Pavel „9 let praxe
 *   v marketingu" (pavelvcelis.cz), takže „každého z nás" platí pro oba.
 * - 2 dny: Tom „2 dny, než se ozvu na poptávku" (tomaskebza.cz).
 * - 5 z 5: Pavlovo hodnocení na Googlu (pavelvcelis.cz), TAVEO vlastní profil nemá.
 * Počty projektů se schválně nesčítají, viz docs/BRAND-STRATEGY.md, oddíl 12.5.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'home.trust_items' => [
                ['value' => '8+ let', 'label' => 'praxe každého z nás'],
                ['value' => '2 dny', 'label' => 'než se ozveme na poptávku'],
                ['value' => '5 z 5', 'label' => 'hodnocení Pavla na Googlu'],
                ['value' => '2 lidé', 'label' => 'se kterými jednáte napřímo'],
            ],
        ]);
    }
};
