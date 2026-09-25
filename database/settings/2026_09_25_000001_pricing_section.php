<?php

use App\Support\ContentSettingsMigration;

/**
 * Blok „A kolik to celé stojí?" mezi postupem spolupráce a formulářem.
 *
 * Tři formy spolupráce podle Pavlova textu z 24. 9. 2026. Ceny jsou jen
 * výchozí hodnoty, správce je přepíše v Nastavení → Homepage → Ceník.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 * Obchodní kontext cen: docs/BRAND-STRATEGY.md, oddíl 6.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'home.pricing_title' => 'A kolik to celé stojí?',
            'home.pricing_perex' => 'Záleží na tom, co potřebujete vyřešit a jestli chcete pomoct nárazově, nebo se nám svěřit do pravidelné péče.',
            'home.pricing_plans' => [
                [
                    'name' => 'Strategická konzultace',
                    'when' => 'Když potřebujete poradit a určit další krok.',
                    'text' => 'S Pavlem nebo Tomášem proberete konkrétní problém a možnosti řešení, online nebo osobně. Před konzultací si v 15minutovém hovoru zdarma ujasníme téma a priority. V ceně je příprava, záznam a akční to-do list.',
                    'price' => '2 000 Kč',
                    'price_unit' => '/ hod.',
                    'price_note' => null,
                    'highlight' => false,
                ],
                [
                    'name' => 'Jednorázová spolupráce',
                    'when' => 'Když potřebujete dotáhnout konkrétní změnu nebo nakopnout výkon webu či marketingu.',
                    'text' => 'Domluvíme si zadání, rozsah práce, termín a způsob předání. Předem dostanete cenový odhad podle potřebných hodin. Když se práce rozroste, rozšíření vždy nejdřív schválíte.',
                    'price' => '1 000 Kč',
                    'price_unit' => '/ hod.',
                    'price_note' => null,
                    'highlight' => false,
                ],
                [
                    'name' => 'Pravidelná spolupráce',
                    'when' => 'Když chcete mít web i marketing průběžně v péči.',
                    'text' => 'Rezervujete si společnou kapacitu Pavla a Tomáše. Priority určujeme s vámi a podle potřeby střídáme konzultace, marketingovou práci a úpravy webu. Máte pravidelnou podporu a předem domluvený měsíční rozpočet.',
                    'price' => 'od 8 900 Kč',
                    'price_unit' => '/ měsíc',
                    'price_note' => 'Základ zahrnuje 8 hodin práce měsíčně, rozdělených mezi oba specialisty.',
                    'highlight' => true,
                ],
            ],
        ]);
    }
};
