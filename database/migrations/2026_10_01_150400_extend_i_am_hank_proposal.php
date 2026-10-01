<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * I am Hank: cookie lišta, nefunkční Google Analytics, SEO kategorií
 * a produktů a redesign jen jako možnost. Ověřeno 1. 10. 2026 na iamhank.cz
 * v čistém prohlížeči: bez kliknutí do lišty běží Hotjar ze dvou účtů
 * (838542 a 1512690, včetně nahrávání) a tracker Ecomailu (cookies _sp_).
 * Lišta má jen „Přijmout všechny cookies“ a „Personalizovat“. Po souhlasu
 * se načte Universal Analytics (analytics.js, ec.js), Google Ads
 * (AW-10790895925) a pixel Mety, Google Analytics 4 žádný. Kategorie mají
 * titulky „Dámské“, „Dětské“, „Poukazy“, bílý i černý rolák titulek
 * „Tričko s dlouhým rukávem“, 6 z 8 prošlých produktů popis „Český
 * výrobek“, pletený svetřík 22 z 25 obrázků bez alt, úvodní stránka bez H1.
 * Váha úvodní stránky na mobilu kolem 4,3 MB (dvě měření, z toho chat
 * Zendesku 1 MB), proto ji nezmiňujeme.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('i-am-hank', [
            'findings' => [
                [
                    '_after' => 'Vánoce bez termínu, přitom šijete na objednávku',
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Hotjar nahrává návštěvy ještě před souhlasem',
                    'body' => 'Hned při otevření webu, bez jakéhokoli kliknutí v cookie liště, se spustí Hotjar, a to rovnou ze dvou účtů. '
                        .'Zaznamenává, co návštěvník na webu dělá. Své cookies si uloží i měřicí skript Ecomailu. Podle českého zákona '
                        .'o elektronických komunikacích a pravidel EU smí tohle začít až po souhlasu. Lišta navíc nabízí jen Přijmout '
                        .'všechny cookies a Personalizovat, tlačítko pro odmítnutí chybí. Úřad pro ochranu osobních údajů i evropští '
                        .'regulátoři chtějí, aby odmítnutí bylo stejně snadné jako souhlas. Google a Meta přitom na souhlas správně '
                        .'čekají (ověřeno 1. 10. 2026).',
                ],
                [
                    '_after' => 'Hotjar nahrává návštěvy ještě před souhlasem',
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Google Analytics měří verzí, kterou Google vypnul',
                    'body' => 'Po souhlasu s cookies se na webu spustí Universal Analytics, starý Google Analytics, který Google v roce 2024 '
                        .'vypnul. Nový Google Analytics 4 se nenačítá, takže z webu teď do Analytics nechodí žádná data o návštěvách '
                        .'ani o nákupech. Měření pro reklamy Googlu a pixel Mety se po souhlasu načtou. Chybí ale přehled, odkud lidé '
                        .'přicházejí a kde z webu odcházejí, a bez něj nebude podle čeho web po sezoně ladit.',
                ],
                [
                    '_after' => 'Doručení a prodejna ve dvou verzích',
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Google o kategoriích a produktech skoro nic neví',
                    'body' => 'Kategorie mají ve výsledcích hledání titulky jen „Dámské“, „Dětské“ nebo „Poukazy“, bez zmínky o oblečení '
                        .'nebo o tom, že ho šijete v Liberci. Bílý i černý rolák mají stejný titulek „Tričko s dlouhým rukávem“ '
                        .'a většina triček a mikin, které jsme prošli, má jako popis pro Google jen „Český výrobek“. Fotky produktů '
                        .'nemají popisky (u pleteného svetříku chybí u 22 z 25 obrázků) a úvodní stránka nemá hlavní nadpis. '
                        .'Co lidé hledají, třeba dámská mikina s potiskem nebo oblečení z české výroby, v titulcích není.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Úvodní stránka připravená na Vánoce do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Tlačítko Odmítnout vedle Přijmout a Hotjar s Ecomailem spuštěné až po souhlasu, stejně jako už to funguje '
                        .'u Googlu a Mety. Místo vypnutého Universal Analytics nasadíme Google Analytics 4, ať jsou ze sezony data. '
                        .'Nejsme právníci, technickou stránku ale umíme nastavit tak, aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Jasné doručení a jedna adresa',
                    'who' => 'Tom',
                    'title' => 'Titulky, popisy a popisky fotek pro Google',
                    'body' => 'U kategorií a produktů napíšeme titulky a popisy podle toho, co lidé hledají, doplníme popisky fotek '
                        .'a hlavní nadpis na úvodní stránku. Začneme kategoriemi a poukazy, pak produkty s potisky.',
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
                    '_after' => 'Vánoční uzávěrka a doprava',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a měření',
                    'body' => 'Tlačítko pro odmítnutí, Hotjar a Ecomail až po souhlasu, Google Analytics 4 místo starého.',
                    'later' => false,
                ],
                [
                    '_after' => 'Kategorie a detail produktu na mobilu',
                    'when' => '2.–3. týden',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Kategorie, poukazy a produkty, popisky fotek.',
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
