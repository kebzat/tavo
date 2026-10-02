<?php

use App\Support\ContentSettingsMigration;

/**
 * Dvě nové sekce homepage, jen nová pole (add):
 *
 * - Recenze klientů (#recenze). Proklik na ně vede z „5,0 na Googlu".
 *   Odkaz na Google profil zůstává prázdný, dokud ho správce nevyplní.
 * - „Kolik hodin vlastně potřebuji?" pod kartami ceníku. Částky vychází
 *   z ceníku na webu k 2. 10. 2026 (pravidelná spolupráce od 8 000 Kč za
 *   8 hodin, 1 000 Kč za hodinu). Osm hodin je společná kapacita obou,
 *   viz docs/BRAND-STRATEGY.md, oddíl 6.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'home.reviews_title' => 'Co o nás říkají klienti',
            'home.reviews_perex' => 'Recenze, které klienti napsali Pavlovi nebo Tomovi. U každé je vidět, komu patří, a jméno vede na web klienta.',
            'home.reviews_google_url' => null,

            'home.pricing_examples_title' => 'Kolik hodin vlastně potřebuji?',
            'home.pricing_examples_perex' => 'Dva příklady z praxe. Hodiny jsou společná kapacita Pavla a Toma: hodinový hovor s oběma tedy spotřebuje dvě hodiny. Jak se zbytek rozdělí mezi marketing a web, domlouváme každý měsíc podle toho, co zrovna hoří.',
            'home.pricing_examples' => [
                [
                    'hours' => '8 hodin měsíčně',
                    'price' => '8 000 Kč / měsíc',
                    'for' => 'Menší e-shop nebo firemní web, který běží a potřebuje pravidelnou péči.',
                    'items' => [
                        'Kontrola kampaní na Facebooku a Instagramu a jedna nová reklama',
                        'Dvě až tři drobné úpravy webu, třeba lišta do dopravy zdarma nebo nový banner',
                        'Měsíční report s tím, co dál',
                    ],
                    'after' => 'Máte vyčištěné kampaně, ověřené měření a pár úprav, které zákazníkům ubraly zbytečné kliky. Pořád spíš postupné ladění než velké změny.',
                ],
                [
                    'hours' => '16 hodin měsíčně',
                    'price' => '16 000 Kč / měsíc',
                    'for' => 'E-shop, který chce růst a má rozpočet na reklamu i na úpravy webu.',
                    'items' => [
                        'Správa kampaní na Metě a každý měsíc nové reklamy k otestování',
                        'Jedna větší úprava webu, například nový košík, filtr v kategorii nebo šablona popisků produktů',
                        'Scénáře pro reels a zadání pro grafiku',
                        'Měsíční report a hovor nad čísly',
                    ],
                    'after' => 'Stihneme dotáhnout jednu větší věc od návrhu po spuštění, třeba redesign košíku nebo dárkové balíčky, a vyhodnotit první kampaně, které na ni posílají lidi.',
                ],
            ],
            'home.pricing_examples_note' => 'Ceny jsou za naši práci. Rozpočet na reklamu platíte přímo Metě nebo Googlu a natáčení s kameramanem se domlouvá zvlášť.',
        ]);
    }
};
