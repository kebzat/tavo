<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Koblížek: cookies a videa na detailu produktu, SEO kolekcí a redesign jen
 * jako možnost. Ověřeno 1. 10. 2026 na koblizekstore.cz (Shopify) v čistém
 * prohlížeči bez kliknutí do lišty: lišta Shopify má Přijmout a Odmítnout
 * vedle sebe a píše o marketingu, Google Analytics, Meta pixel a Bing čekají
 * na souhlas. Microsoft Clarity ale posílá data hned při otevření (cookies
 * _clck/_clsk až po souhlasu) a na detailu produktu (Mikina Longo Čokoláda,
 * Mikina Fluffy Latte, Mikina Fluffy Čokoláda) jsou tři videa YouTube, která
 * před souhlasem uloží cookies YSC, VISITOR_INFO1_LIVE a další. Detail
 * produktu na mobilu kolem 7 MB hned při otevření, 13 MB po projetí, z toho
 * YouTube 3,2 MB a hCaptcha formuláře KLUBU kolem 3,5 MB. Úvodní stránka
 * 2,6 až 2,7 MB, bez nálezu. Kolekce Legíny, Mikiny, Tepláková souprava
 * Kakao a velikosti (Vel. 86) mají prázdný meta popis a titulek jen názvem
 * kolekce. Produkty mají Product + AggregateRating v JSON-LD.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('koblizek', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Videa z YouTube a nahrávání návštěv běží ještě před souhlasem',
                    'body' => 'Cookie lištu máte dobře: Přijmout a Odmítnout jsou vedle sebe a text mluví i o marketingu. '
                        .'Google Analytics a Meta pixel na souhlas čekají. Microsoft Clarity, který nahrává, co návštěvník na webu dělá, '
                        .'ale posílá data hned při otevření stránky. Na detailu mikiny jsou navíc tři videa z YouTube, která se načtou, '
                        .'i když je nikdo nepustí. Uloží cookies YouTube a stáhnou přes 3 MB, takže detail produktu má na telefonu '
                        .'hned při otevření kolem 7 MB. Podle českého zákona o elektronických komunikacích a pravidel EU smí nahrávání '
                        .'návštěv i cookies YouTube začít až po souhlasu (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Kolekce mají pro Google jen holý název',
                    'body' => 'Kolekce Legíny, Mikiny nebo Tepláková souprava Kakao nemají popis pro vyhledávače vůbec a v titulku je jen '
                        .'„Legíny“ nebo „Mikiny“. Že jde o dětské oblečení šité v Česku, se z výsledku hledání nikdo nedozví. '
                        .'Stejně jsou na tom stránky podle velikostí, třeba „Vel. 86“. Produkty to mají lépe: hodnocení od zákaznic '
                        .'jsou zapsaná tak, že je Google může ukázat jako hvězdičky přímo ve výsledcích.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Videa a nahrávání návštěv až po souhlasu',
                    'body' => 'Místo tří přehrávačů YouTube dáme na detail produktu náhled, video se načte až po kliknutí. '
                        .'Clarity napojíme na lištu Shopify stejně, jako už je napojený Google a Meta. Detail mikiny zhubne o víc než 3 MB. '
                        .'Nejsme právníci, technickou stránku ale umíme nastavit tak, aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Přehlednější detail produktu na mobilu',
                    'who' => 'Tom',
                    'title' => 'Titulky a popisy kolekcí pro Google',
                    'body' => 'U hlavních kolekcí, jako jsou soupravy, mikiny, legíny a sety, napíšeme titulek a popis podle toho, '
                        .'co maminky hledají, třeba dětská tepláková souprava. Na Shopify se to dělá v nastavení kolekce, bez zásahu do vzhledu.',
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
                    '_after' => 'Refresh problémových míst',
                    'when' => '1. týden',
                    'title' => 'Videa a Clarity až po souhlasu',
                    'body' => 'Náhledy místo přehrávačů YouTube, Clarity napojený na cookie lištu.',
                    'later' => false,
                ],
                [
                    '_after' => 'Úprava detailu produktu',
                    'when' => '3. týden',
                    'title' => 'Titulky a popisy kolekcí',
                    'body' => 'Soupravy, mikiny, legíny a sety.',
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
