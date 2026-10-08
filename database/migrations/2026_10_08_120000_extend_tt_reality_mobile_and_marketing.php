<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * TTreality: mobil, stará doména, favicon a marketing podle Pavla
 * (připomínky ze 7. 10. 2026). Ověřeno 8. 10. 2026:
 *
 * - https://www.ttreal.cz vrací 200 se starým webem (index, follow), http
 *   přesměruje 301 na nový. Stránka visí na „Vydržte… Ukládáme potřebná data“,
 *   protože styly bere z www.ttreality.cz, kde už nejsou. Ve zdrojovém kódu je
 *   blok 1 × 1 px s odkazy na padělky hodinek a e-cigarety (mění se s každým
 *   načtením). V Googlu je na „tt reality hradec králové“ druhá.
 * - ttreality.cz nemá <link rel="icon"> a /favicon.ico vrací 404.
 * - Mobil 390 px (iPhone): úvod 6 054 px, hlavička o 4 px širší než displej,
 *   v patičce jen osobní údaje a copyright. Detail bytu 4+kk (inzerát 407)
 *   6 066 px, hlavní fotka pruh 180 px, galerie od 3 684 px, půdorys
 *   oříznutý do čtverce (object-fit: cover), žádný formulář, mapa ani datum.
 *   Výpis nemovitostí 39 536 px.
 * - Facebook T&T Reality: 386 sledujících, 17 recenzí, 96 %, poslední viditelný
 *   příspěvek změna úvodní fotky 25. 4. 2025. Web na sítě neodkazuje.
 *   Knihovna reklam Meta: na „T&T Reality“ nic.
 * - Okolí v návrhu detailu je z OpenStreetMap (Overpass), vzdušnou čarou.
 *
 * Videa jsou Pavlova práce pro jinou realitku, sdílená z Disku.
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
                    '_after' => 'Kdo chce prodat, nemá kde nechat kontakt',
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Na staré adrese ttreal.cz běží starý web se skrytým spamem',
                    'body' => 'Když v Googlu hledáte „tt reality hradec králové“, druhý výsledek je www.ttreal.cz s popisem, že na trhu '
                        .'jste „více než 17 let“. Na téhle adrese pořád běží původní web. Zasekne se na hlášce „Vydržte… Ukládáme potřebná '
                        .'data“, nabízí byty, které už nejsou aktuální, a ve zdrojovém kódu má schovaný blok odkazů na padělky hodinek '
                        .'a e-cigarety. Návštěvník ho nevidí, Google ano. Pro Google je to typický znak napadeného webu, a ten web nese '
                        .'vaše jméno. Kdo napíše adresu bez zabezpečení, toho to na nový web přesměruje správně, zabezpečenou verzi ale '
                        .'nikdo nepřesměroval (ověřeno 8. 10. 2026).',
                ],
                [
                    '_after' => 'Cena a lokalita se ukážou až po najetí myší',
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Na telefonu je úvodní stránka dlouhá přes sedm obrazovek',
                    'body' => 'Z našich kampaní víme, že z telefonu přichází tři čtvrtiny návštěv i víc, z reklamy skoro všechny. Na '
                        .'iPhonu má úvodní stránka přes sedm obrazovek. Tři inzeráty zaberou skoro dvě a půl, protože každý ukazuje '
                        .'celou tabulku parametrů. Fotky jsou oříznuté nešikovně: Milanovi v úvodu chybí půlka obličeje, o kus níž '
                        .'zase hlava. Hlavička je o kousek širší než displej, takže stránka při posouvání ujíždí do strany. V patičce '
                        .'není telefon ani adresa, jen odkaz na zpracování osobních údajů.',
                ],
                [
                    '_after' => 'Na telefonu je úvodní stránka dlouhá přes sedm obrazovek',
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Detail bytu na mobilu ukáže fotky až po čtyřech obrazovkách',
                    'body' => 'U bytu 4+kk v Medium Parku je hlavní fotka na telefonu jen úzký pruh nad tabulkou parametrů. Pak přijde '
                        .'makléřka a dlouhý popis, galerie začíná až po čtyřech obrazovkách. Půdorys je oříznutý do čtverce, takže '
                        .'legenda s plochami místností není vidět. Stránka končí tlačítkem Zobrazit v PDF. Formulář na prohlídku, '
                        .'mapa ani přehled toho, co je v okolí, tu nejsou. Podlaží, výtah, parkování a měsíční náklady jsou jen '
                        .'v textu, i když je zájemci hledají v přehledu, jak jsou zvyklí ze Srealit nebo Bezrealitek.',
                ],
                [
                    '_after' => 'Detail bytu na mobilu ukáže fotky až po čtyřech obrazovkách',
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'U inzerátů není vidět, jestli jsou aktuální',
                    'body' => 'V nabídce ani u inzerátu není datum vložení nebo poslední úpravy. Lidé u realit hodně řeší, jestli je '
                        .'byt ještě volný, a bez data to nepoznají. Na mobilu je nabídka navíc dlouhá skoro 47 obrazovek, protože '
                        .'v ní jsou i prodané a pronajaté nemovitosti.',
                ],
                [
                    '_after' => 'Makléřka má u inzerátů kreslený avatar',
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Facebook působí, jako by kancelář skončila',
                    'body' => 'Stránka T&T Reality má 386 sledujících a 17 recenzí s doporučením od 96 % lidí. Poslední příspěvek, '
                        .'který je na ní vidět, je změna úvodní fotky z 25. dubna 2025. Kdo si vás tam před schůzkou dohledá, '
                        .'snadno nabude dojmu, že kancelář už nefunguje. Web na Facebook nikde neodkazuje a Instagram jsme nenašli.',
                ],
                [
                    '_after' => 'Facebook působí, jako by kancelář skončila',
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Reklamu na sociálních sítích zatím nevyužíváte',
                    'body' => 'V knihovně reklam Mety jsme pod jménem T&T Reality žádnou reklamu nenašli. Na Facebooku a Instagramu '
                        .'přitom jde za pár tisíc korun, někdy i zadarmo přes místní skupiny o bydlení, získat jednotky až desítky '
                        .'zájemců o prohlídku. U prodejů bytů z druhé ruky i developerských projektů jsme měli vyplněné poptávky '
                        .'od 200 Kč za kus. Kolik to bude u vás, záleží na nemovitosti a na tom, jak ji ukážeme.',
                ],
                [
                    '_after' => 'Stránku s kontakty mají vyhledávače zakázanou',
                    'priority' => 'later',
                    'tone' => 'problem',
                    'title' => 'Web nemá vlastní ikonu',
                    'body' => 'V záložce prohlížeče i ve výsledcích Googlu je u webu jen obecná zeměkoule, protože web nemá favicon, '
                        .'tedy malé logo, které prohlížeč ukazuje u adresy. Mezi otevřenými záložkami a ve výsledcích hledání se '
                        .'tak hůř pozná. Logo T&T máte, jde o pár souborů a řádek v kódu.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Jedna zabezpečená adresa',
                    'who' => 'Tom',
                    'title' => 'Stará adresa ttreal.cz pod kontrolou',
                    'body' => 'Celou starou adresu včetně zabezpečené verze přesměrujeme na ttreality.cz, a to každou starou stránku na '
                        .'tu, která jí dnes odpovídá. Starý web se spamem tím zmizí. V Search Console Googlu nahlásíme změnu adresy, '
                        .'aby výsledky přešly na nový web. Potřebujeme k tomu přístup k hostingu nebo doméně starého webu. Rovnou '
                        .'doplníme i ikonu webu.',
                ],
                [
                    '_after' => 'Lehčí úvodní stránka',
                    'who' => 'Tom',
                    'title' => 'Web postavený nejdřív pro telefon',
                    'body' => 'Úvodní stránku na mobilu zkrátíme: inzeráty jako menší karty s fotkou, cenou a lokalitou, fotky '
                        .'oříznuté tak, aby byl Milan vidět celý, a hlavička, která neujíždí do strany. Do patičky přijde telefon, '
                        .'adresa a odkaz na Facebook. Každou úpravu zkontrolujeme na skutečném telefonu.',
                ],
                [
                    '_after' => 'Nabídka, ve které je vidět, co je volné',
                    'who' => 'Tom',
                    'title' => 'Detail nemovitosti, jaký lidé znají z portálů',
                    'body' => 'Nahoře galerie, hned pod ní cena, cena za metr a tlačítko na prohlídku. Přehled parametrů včetně '
                        .'podlaží, parkování a měsíčních nákladů, datum vložení a poslední úpravy, mapa s tím, co je v okolí, a formulář '
                        .'„Chci tento byt vidět“ s fotkou makléřky. Půdorys celý a čitelný. Jak by to mohlo vypadat, ukazuje návrh níž.',
                ],
                [
                    '_after' => 'Stránka pro majitele, kteří chtějí prodat',
                    'who' => 'Pavel',
                    'title' => 'Být v Hradci vidět i mimo inzeráty',
                    'body' => 'Oživíme Facebook a začneme ukazovat, co řešíte: prodané byty, home staging, prohlídky, Milana a Terezu. '
                        .'Dvakrát až čtyřikrát do roka pustíme v Hradci a okolí krátké video s Milanem jako reklamu, klidně s obecnou '
                        .'nabídkou „Řešíme komisní prodej, výkupy a pronájmy“. Stojí to stovky korun měsíčně. Kdo o prodeji teprve '
                        .'přemýšlí, si pak vzpomene, na koho se obrátit.',
                ],
                [
                    '_after' => 'Být v Hradci vidět i mimo inzeráty',
                    'who' => 'Pavel',
                    'title' => 'Reklama na konkrétní nemovitosti',
                    'body' => 'Ke každé nemovitosti umíme připravit malý balíček: krátké video nebo bannery, reklamu na Facebooku '
                        .'a Instagramu, sdílení v místních skupinách o bydlení a podporu inzerátu na Srealitách. Vychází na jednotky '
                        .'tisíc za nemovitost. Když vám víc sedí stálá výpomoc nebo jen konzultace, domluvíme paušál. Ukázky videí, '
                        .'které Pavel dělal pro jinou realitku, jsou níž.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Zabezpečená adresa a kontakty pro Google',
                    'when' => '1. týden',
                    'title' => 'Stará adresa a ikona webu',
                    'body' => 'Přesměrování celého ttreal.cz, změna adresy v Search Console, favicon.',
                    'later' => false,
                ],
                [
                    '_after' => 'Nabídka a inzeráty',
                    'when' => '2.–3. týden',
                    'title' => 'Detail nemovitosti a mobil',
                    'body' => 'Nový detail s mapou, okolím, datem a formulářem. Kratší úvod a opravené ořezy fotek na telefonu.',
                    'later' => false,
                ],
                [
                    '_after' => 'Focení',
                    'when' => '4. týden',
                    'title' => 'Facebook a první reklama',
                    'body' => 'Oživený Facebook, první video s Milanem a reklama na jednu nemovitost, na které uvidíme čísla.',
                    'later' => false,
                ],
                [
                    '_after' => 'Rozvoj webu',
                    'when' => 'Průběžně',
                    'title' => 'Video s Milanem dvakrát až čtyřikrát do roka',
                    'body' => 'Reklama v Hradci a okolí a podpora nových nemovitostí podle toho, co se osvědčí.',
                    'later' => true,
                ],
            ],
            'examples' => [
                [
                    'placement' => 'after_findings',
                    'kind' => 'Dnes na mobilu',
                    'title' => 'Takhle web vidí většina návštěvníků',
                    'body' => 'Screenshoty z 8. 10. 2026 v rozlišení iPhonu. V telefonu se dá scrollovat, takže uvidíte, jak '
                        .'dlouho trvá dostat se k podstatnému. Pod nimi je starý web na ttreal.cz, kam vede druhý výsledek Googlu.',
                    'image' => null,
                    'scroll' => false,
                    'link_url' => null,
                    'link_label' => null,
                    'items' => [
                        [
                            'title' => 'Úvodní stránka',
                            'body' => 'Přes sedm obrazovek, Milan oříznutý, žádný formulář.',
                            'image' => 'spoluprace/tt-reality-mobil-uvod.jpg',
                            'image_alt' => 'Dnešní úvodní stránka TTreality na mobilu',
                            'video_url' => null,
                            'phone' => true,
                        ],
                        [
                            'title' => 'Detail bytu 4+kk',
                            'body' => 'Fotky až po čtyřech obrazovkách, na konci jen PDF.',
                            'image' => 'spoluprace/tt-reality-mobil-detail.jpg',
                            'image_alt' => 'Dnešní detail inzerátu TTreality na mobilu',
                            'video_url' => null,
                            'phone' => true,
                        ],
                        [
                            'title' => 'Starý web na www.ttreal.cz',
                            'body' => 'Zůstane viset na hlášce „Vydržte…“. Ve zdrojovém kódu má skryté odkazy na padělky hodinek.',
                            'image' => 'spoluprace/tt-reality-ttreal-cz.jpg',
                            'image_alt' => 'Starý web T&T Reality na adrese ttreal.cz s hláškou Vydržte',
                            'video_url' => null,
                        ],
                    ],
                ],
                [
                    'placement' => 'after_recommendations',
                    'kind' => 'Návrh s pomocí AI',
                    'title' => 'Takhle by mohl vypadat detail nemovitosti',
                    'body' => 'Návrh stojí na skutečném inzerátu bytu 4+kk v Medium Parku: vaše fotky, cena, parametry a náklady '
                        .'z popisu. Okolí a vzdálenosti jsou z OpenStreetMap, data vložení a úpravy jsou jen pro ukázku. Na telefonu '
                        .'má detail necelé čtyři obrazovky místo dnešních sedmi a cenu s tlačítkem na prohlídku vidí zájemce hned '
                        .'po otevření. Místo pro fotku Terezy zatím zůstává prázdné.',
                    'image' => null,
                    'scroll' => false,
                    'link_url' => null,
                    'link_label' => null,
                    'items' => [
                        [
                            'title' => 'Na telefonu',
                            'body' => 'Fotka, cena a prohlídka na první obrazovce, mapa a formulář bez dlouhého hledání.',
                            'image' => 'spoluprace/tt-reality-navrh-detail-mobil.jpg',
                            'image_alt' => 'Návrh detailu nemovitosti TTreality na mobilu',
                            'video_url' => null,
                            'phone' => true,
                        ],
                        [
                            'title' => 'Na počítači',
                            'body' => 'Galerie nahoře, vpravo cena, makléřka a formulář na prohlídku, níž okolí s mapou.',
                            'image' => 'spoluprace/tt-reality-navrh-detail.jpg',
                            'image_alt' => 'Návrh detailu nemovitosti TTreality na počítači',
                            'video_url' => null,
                        ],
                    ],
                ],
                [
                    'placement' => 'after_steps',
                    'kind' => 'Ukázky z praxe',
                    'title' => 'Videa, která Pavel připravoval pro jinou realitku',
                    'body' => 'Krátká videa na výšku pro Instagram a Facebook k bytům v Náchodě a Pardubicích. Po kliknutí se '
                        .'přehrají z Google Disku. Stejně bychom ukazovali vaše nemovitosti i Milana.',
                    'image' => null,
                    'scroll' => false,
                    'link_url' => null,
                    'link_label' => null,
                    'items' => [
                        [
                            'title' => 'Byt 3+kk v Náchodě',
                            'body' => '',
                            'image' => null,
                            'video_url' => 'https://drive.google.com/file/d/1KwGPI5SNtKR4ctkQlaU4VXTnnjUq4aeR/view',
                        ],
                        [
                            'title' => 'Byt 2+1 s průvodkyní',
                            'body' => '',
                            'image' => null,
                            'video_url' => 'https://drive.google.com/file/d/1FqR0VwcDfDPWObJVwghGsyfhUGPDW8_o/view',
                        ],
                        [
                            'title' => 'Cestou k bytu',
                            'body' => '',
                            'image' => null,
                            'video_url' => 'https://drive.google.com/file/d/1-U8ipKkfvLCDP85IWvf306Nknmvz4iMK/view',
                        ],
                        [
                            'title' => 'Byt 1+1',
                            'body' => '',
                            'image' => null,
                            'video_url' => 'https://drive.google.com/file/d/1FysPmyT6Ypkh6mckIfzjyLcwePmj6S8r/view',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        // Body mohli mezitím upravit v nástrojích, mažou se tam.
    }
};
