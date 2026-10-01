<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Krkonošský ráj: cookie lišta, měření Google Ads, SEO úvodní stránky
 * a kategorie Krkonoše, redesign jen jako možnost. Ověřeno 1. 10. 2026 na
 * krkonossky-raj.cz (Shoptet) v čistém prohlížeči bez kliknutí do lišty:
 * lišta má Odmítnout vedle Souhlasím, ale text říká „Dalším procházením
 * tohoto webu vyjadřujete souhlas“. Před souhlasem se načte Facebook sdk.js
 * (bez pixelu), Google čeká (gcs=G100), retargeting Seznamu jde s consent=0.
 * V kódu je vedle skutečného účtu AW-16678821238 i vzorové AW-0123456789
 * a události remarketingu (view_item s ecomm_prodid a ecomm_pagetype) mají
 * send_to jen na AW-0123456789 (úvodní stránka, kategorie, detail bedýnky L).
 * Úvodní stránka bez H1, meta popis je výčet značek. Kategorie Krkonoše
 * a Regionální produkty mají automatický popis („Krkonoše, Krkonošský ráj“),
 * Krkonoše bez textu. Medovina, Pečené čaje, Lázeňské oplatky a Med mají
 * vlastní texty i popisy. Váha úvodní stránky na mobilu 2,0 až 2,5 MB,
 * bez nálezu. 136 obrázků bez alt jsou náhledy v menu Shoptetu, vynecháno.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('krkonossky-raj', [
            'findings' => [
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Remarketing v Google Ads posílá data na vzorový účet',
                    'body' => 'Když si návštěvník prohlédne třeba dárkovou bedýnku, web pošle do Google Ads, který produkt to byl. '
                        .'Jenže na číslo účtu AW-0123456789, což je vzorové číslo z návodu. Váš skutečný účet má jiné číslo a tyhle '
                        .'informace nedostane. Pokud chcete ukazovat reklamy s produkty, které si člověk u vás prohlížel, '
                        .'nemají z čeho brát. Opravit se to dá v nastavení Shoptetu.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Cookie lišta tvrdí, že procházením webu souhlasíte',
                    'body' => 'Lišta má správně tlačítko Odmítnout hned vedle Souhlasím. Text ale říká, že dalším procházením webu '
                        .'s cookies souhlasíte. Podle českého zákona o elektronických komunikacích a pravidel EU to nestačí, '
                        .'souhlas musí být kliknutím. Před kliknutím se navíc načtou skripty Facebooku. Google Analytics, Google Ads '
                        .'i retargeting Seznamu máte nastavené správně, na souhlas čekají (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Kategorie Krkonoše je pro Google prázdná',
                    'body' => 'Kategorie Krkonoše nemá žádný text a popis pro vyhledávače skládá Shoptet sám: „Krkonoše, Krkonošský ráj“. '
                        .'Stejně je na tom Regionální produkty. Úvodní stránka nemá hlavní nadpis a její popis pro Google je výčet značek '
                        .'jako Curtis, Popradský nebo Madami. Kdo hledá dárek z Krkonoš, z výsledku hledání nepozná, že je na správném místě. '
                        .'Přitom to umíte: Medovina, Pečené čaje i Lázeňské oplatky mají vlastní texty i popisy.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Pavel a Tom',
                    'title' => 'Cookie lišta a měření reklam',
                    'body' => 'Text lišty bez „procházením souhlasíte“, skripty Facebooku až po souhlasu a remarketing Google Ads '
                        .'napojený na váš skutečný účet. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                        .'aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Adventní kalendář a vánoční dárky do začátku listopadu',
                    'who' => 'Tom',
                    'title' => 'Krkonoše a úvodní stránka pro Google',
                    'body' => 'Text a popis pro kategorii Krkonoše a Regionální produkty, ve stejném duchu, v jakém už máte medovinu '
                        .'a pečené čaje. K tomu hlavní nadpis na úvodní stránku a popis pro Google, ze kterého je jasné, že prodáváte dobroty z Krkonoš.',
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
                    '_after' => 'Úvodní stránka z Krkonoš',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a měření',
                    'body' => 'Nový text lišty, Facebook až po souhlasu, remarketing na správný účet.',
                    'later' => false,
                ],
                [
                    '_after' => 'Lidé a příběh',
                    'when' => '3. týden',
                    'title' => 'SEO úvodní stránky a Krkonoš',
                    'body' => 'Hlavní nadpis, popis pro Google a texty kategorií Krkonoše a Regionální produkty.',
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
