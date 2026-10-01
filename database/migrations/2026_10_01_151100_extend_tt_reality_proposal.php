<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * TTreality: cookie lišta, váha úvodní stránky, titulky hlavních stránek
 * a redesign jen jako možnost. Ověřeno 1. 10. 2026 na ttreality.cz
 * v čistém prohlížeči s běžným user agentem (BotStopper pustil, headless
 * user agent zablokoval). Cookie lišta na webu není, stránka Cookies píše
 * o souhlasu nastavením prohlížeče a o GA a FB pixelu, které web nenačítá.
 * Retargeting Seznamu (rtgId 155922) běží bez parametru consent, od 1. 8.
 * 2024 tak podle nápovědy Skliku nikoho nezařadí do publika. Na detailu
 * inzerátu se bez souhlasu načte Facebook sdk.js. Mobil (CDP, dvě měření):
 * 11,7 až 13,5 MB při otevření, kolem 17 MB po projetí, stahují se obě
 * videa (homepage_hradec-kralove.mp4 a -xs.mp4, dohromady 6,7 až 7 MB),
 * fotky inzerátů v kartě 286 × 253 px mají 3,4 až 3,6 MB (jpg) a 3 MB (png).
 * Úvod má titulek „Domovská stránka | TTreality“, úvod, Služby, Nemovitosti,
 * O nás a Blog bez meta description, Služby bez H1, O nás s prázdným H1.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('tt-reality', [
            'findings' => [
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Web nemá cookie lištu',
                    'body' => 'Na webu není cookie lišta. Stránka Cookies pořád píše, že souhlas dáváte nastavením prohlížeče, a zmiňuje '
                        .'Google Analytics a facebookový pixel, které web dnes nenačítá. Na každé stránce přitom běží retargetingový kód '
                        .'Seznamu, a to bez informace o souhlasu návštěvníka, kterou Seznam podle své nápovědy od roku 2024 u retargetingu chce. '
                        .'Pokud retargeting ve Skliku používáte, publikum se tak nejspíš neplní. U inzerátů '
                        .'se navíc bez souhlasu načítají skripty Facebooku. Podle českého zákona o elektronických komunikacích a pravidel EU '
                        .'smí reklamní nástroje začít až po souhlasu (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Telefon stahuje úvodní video ve dvou verzích',
                    'body' => 'Na telefonu stáhne úvodní stránka hned při otevření 12 až 14 MB, po projetí kolem 17 MB. Stahují se '
                        .'obě verze videa s Hradcem, velkou i menší pro mobil, dohromady skoro 7 MB, a přehraje jen tu menší. Dvě fotky '
                        .'inzerátů, které se ukazují v malé kartičce, mají přes 3 MB každá. Na mobilních datech to stránku zbytečně zdržuje. '
                        .'Vzhled se kvůli tomu měnit nemusí: stačí fotky zmenšit a video posílat jen v jedné verzi.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Úvodní stránka se pro Google jmenuje „Domovská stránka“',
                    'body' => 'Titulek úvodní stránky je „Domovská stránka | TTreality“. Že jste realitní kancelář z Hradce Králové, se z něj '
                        .'Google nedozví. Úvod, Služby, Nemovitosti, O nás ani Blog nemají popis pro vyhledávače, Služby nemají hlavní '
                        .'nadpis a na stránce O nás je prázdný. Články v blogu přitom máte udělané dobře: vlastní titulek, nadpis '
                        .'i strukturovaná data, podle kterých Google pozná článek.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit to hlavní do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Lišta s tlačítkem pro odmítnutí vedle souhlasu, retargeting Seznamu napojený na souhlas, aby zase sbíral '
                        .'publikum, a stránka Cookies podle toho, co web opravdu používá. Nejsme právníci, technickou stránku ale umíme '
                        .'nastavit tak, aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Cookie lišta a měření podle pravidel',
                    'who' => 'Tom',
                    'title' => 'Lehčí úvodní stránka',
                    'body' => 'Fotky inzerátů zmenšíme na velikost, ve které se opravdu zobrazují, a telefon bude stahovat jen menší video. '
                        .'Vzhled zůstane, stránka jen zhubne.',
                ],
                [
                    '_after' => 'Web, který vyhledávače najdou',
                    'who' => 'Tom',
                    'title' => 'Titulky a popisy hlavních stránek',
                    'body' => 'Úvod, Služby, Nemovitosti a O nás dostanou titulek a popis se slovy, která lidé hledají, třeba prodej bytu '
                        .'nebo realitní makléř v Hradci Králové, a každá jeden hlavní nadpis. U článků v blogu to už funguje, stačí to '
                        .'přenést i jinam.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Redesign jako možnost, ne podmínka',
                    'body' => 'Návrh nové úvodní stránky výš ukazuje, kam by se web mohl posunout, a nový vzhled by mu prospěl. '
                        .'Nic z toho, co tu píšeme, na něm ale nestojí. Začneme úpravami a o větší změně se pobavíme, '
                        .'až uvidíme, jaké poptávky z webu chodí.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Rychlé opravy',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a lehčí úvodní stránka',
                    'body' => 'Lišta s odmítnutím, Seznam napojený na souhlas, menší fotky inzerátů a jedno video pro telefon.',
                    'later' => false,
                ],
                [
                    '_after' => 'Nabídka a inzeráty',
                    'when' => '2. týden',
                    'title' => 'Titulky a popisy hlavních stránek',
                    'body' => 'Úvod, Služby, Nemovitosti a O nás.',
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
