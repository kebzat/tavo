<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Vivien Cosmetics: cookie lišta, SEO popisy a redesign jen jako možnost.
 * Ověřeno 1. 10. 2026 na viviencosmetics.cz v čistém prohlížeči bez
 * kliknutí do lišty. V síťových požadavcích před souhlasem: retargeting
 * Seznamu (c.seznam.cz/retargeting), skripty Facebooku (sdk.js, bez
 * facebook.com/tr), sledovací skript Ecomailu (cookies _sp_id, _sp_ses),
 * Leadhub (cookie _lhic) a skript z im9.cz (cookie _TCC, nepojmenováno).
 * Google Analytics s gcs=G100, bez _ga. Lišta má jen Souhlasím a
 * Nastavení. O reklamních kampaních nic netvrdíme (pokyn marketéra).
 * SEO: úvodní stránka bez H1, meta description slibuje „Poštovné zdarma
 * nad 1 200 Kč“, Shoptet přitom má freeShippingFrom 500. Automatické
 * popisy „Název, VIVIEN cosmetics“ u Dámské parfémy, Dárkové sady
 * kosmetiky, Dárkové sady do 400 Kč, Kosmetika s hadím jedem. Texty
 * kategorií dlouhé, produkty mají microdata Product/Offer. Obrázky bez alt
 * jsou skoro všechny v menu (124 z 155), proto nezmiňujeme. Váha v pořádku
 * (3,7 MB na mobilu při otevření ve dvou měřeních, 3,8 až 4,1 MB celkem).
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('vivien-cosmetics', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Seznam, Ecomail a Leadhub se spustí ještě před souhlasem',
                    'body' => 'Hned při otevření webu, než zákaznice v cookie liště cokoli klikne, se spustí retargeting Seznamu, skripty '
                        .'Facebooku, sledovací skript Ecomailu a Leadhub. Ecomail a Leadhub si rovnou uloží své cookies. Podle českého '
                        .'zákona o elektronických komunikacích a pravidel EU smí tohle začít až po souhlasu. Lišta navíc nabízí jen '
                        .'Souhlasím a Nastavení, tlačítko pro odmítnutí v ní chybí. Úřad pro ochranu osobních údajů i evropští regulátoři '
                        .'chtějí, aby odmítnutí bylo stejně snadné jako souhlas. Google Analytics na souhlas čeká, jak má '
                        .'(ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Popis pro Google slibuje dopravu zdarma až od 1 200 Kč',
                    'body' => 'Popis úvodní stránky pro vyhledávače končí větou „Poštovné zdarma nad 1 200 Kč“, přitom posíláte zdarma '
                        .'od 500 Kč. Úvodní stránka nemá hlavní nadpis. U kategorií Dámské parfémy, Dárkové sady kosmetiky nebo Dárkové '
                        .'sady do 400 Kč je popis jen název a „VIVIEN cosmetics“, poskládaný automaticky. Texty v kategoriích přitom '
                        .'máte dlouhé a poradí, třeba u pleťové kosmetiky, a produkty mají strukturovaná data s cenou. Chybí jen ten '
                        .'krátký text, který lidé uvidí ve výsledcích hledání.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Tlačítko Odmítnout hned vedle Souhlasím. Seznam, Facebook, Ecomail a Leadhub spustíme až po souhlasu, '
                        .'stejně jako už to funguje u Google Analytics. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                        .'aby odpovídala pravidlům a data z měření byla čistá.',
                ],
                [
                    '_after' => 'Výloha pro vlastní české parfémy',
                    'who' => 'Tom',
                    'title' => 'Popisy pro Google u dárků a parfémů',
                    'body' => 'Opravíme popis úvodní stránky na dopravu zdarma od 500 Kč a doplníme hlavní nadpis. U dárkových sad, '
                        .'parfémů a hlavních kategorií napíšeme popisy ručně, ať jsou připravené na Vánoce. Texty v kategoriích '
                        .'máte dobré, stavíme na nich.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Redesign jako možnost, ne podmínka',
                    'body' => 'Návrh nové úvodní stránky výš ukazuje, kam by se web mohl posunout, a nový vzhled by mu prospěl. '
                        .'Nic z toho, co tu píšeme, na něm ale nestojí. Začneme úpravami a o větší změně se pobavíme, až uvidíme data ze sezony.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Vánoční úvodní stránka',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a popisy pro Google',
                    'body' => 'Tlačítko pro odmítnutí, nástroje třetích stran až po souhlasu, opravený popis úvodní stránky.',
                    'later' => false,
                ],
                [
                    '_after' => 'Rozcestník Co vás trápí',
                    'when' => '2. týden',
                    'title' => 'Popisy dárků a parfémů',
                    'body' => 'Ručně psané popisy pro Google u dárkových sad, parfémů a hlavních kategorií.',
                    'later' => false,
                ],
            ],
        ]);
    }

    public function down(): void
    {
        // Body mohli mezitím upravit v nástrojích, mažou se tam.
    }
};
