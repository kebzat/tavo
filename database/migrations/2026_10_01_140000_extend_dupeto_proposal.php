<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * DUPETO: váha úvodní stránky, SEO titulky, cookie lišta a redesign jen
 * jako možnost. Ověřeno 1. 10. 2026 na dupetoshop.cz: na mobilu kolem
 * 14 MB hned při otevření, při projetí stránky 22 až 45 MB (~450 souborů),
 * z toho facebookový widget skoro 19 MB videí, Mapy.cz přes 6 MB a fotka
 * Maminka-roku_2020_DUPETO.jpg 8,3 MB. Před souhlasem běží Smartlook,
 * Clarity, Seznam, Facebook a YouTube, Google čeká (Consent Mode).
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('dupeto', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Úvodní stránka stahuje desítky megabajtů',
                    'body' => 'Na telefonu stáhne úvodní stránka hned při otevření kolem 14 MB. Když ji návštěvník projede celou, '
                        .'bylo to v našich měřeních 22 až 45 MB ve zhruba 450 souborech. Nejvíc berou videa z facebookového widgetu '
                        .'dole na stránce (skoro 19 MB), mapa z Mapy.cz (přes 6 MB) a jedna fotka Maminka roku 2020, která má sama 8 MB. '
                        .'Na mobilních datech se stránka načítá pomalu a kdo nemá trpělivost, odejde dřív, než uvidí první produkt. '
                        .'Vzhled se kvůli tomu měnit nemusí: stačí widgety nahradit odkazem a fotky zmenšit.',
                ],
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Nahrávání návštěv a měření běží ještě před souhlasem',
                    'body' => 'Lišta má správně tlačítko Odmítnout hned vedle Souhlasím. Než ale návštěvník cokoli klikne, už běží Smartlook '
                        .'a Microsoft Clarity, které nahrávají, co na webu dělá, k tomu měření Seznamu a skripty Facebooku, '
                        .'a YouTube si uloží své cookies. Text lišty navíc říká, že procházením webu s cookies souhlasíte. '
                        .'Podle českého zákona o elektronických komunikacích a pravidel EU to nestačí, souhlas musí být kliknutím. '
                        .'Google Analytics a Google Ads přitom máte nastavené správně, na souhlas čekají (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Titulky pro Google skládá automat',
                    'body' => 'Kategorie mají dobré texty a obrázky mají popisky, v tom máte před většinou e-shopů náskok. Titulky a popisy '
                        .'pro vyhledávače ale skládá Shoptet sám: „Na zimu, 133 variant | DUPETOSHOP.CZ“ nebo „Bundy, overaly, vesty, '
                        .'36 variant“. To, co rodiče hledají, třeba zimní overal pro děti nebo dětské softshellové kalhoty, v nich není. '
                        .'U hlavních kategorií a nejprodávanějších produktů stačí titulky a popisy napsat ručně.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Lehká úvodní stránka',
                    'body' => 'Facebookový widget a mapu nahradíme odkazem nebo obrázkem, fotky zmenšíme a převedeme do moderního formátu '
                        .'a videa se budou načítat až po kliknutí. Vzhled zůstane, stránka jen zhubne.',
                ],
                [
                    '_after' => 'Lehká úvodní stránka',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Text lišty bez „procházením souhlasíte“ a Smartlook, Clarity, Seznam, Facebook i YouTube spuštěné až po souhlasu, '
                        .'stejně jako už to funguje u Googlu. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                        .'aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Zimní a vánoční stránka',
                    'who' => 'Tom',
                    'title' => 'Titulky a popisy pro Google ručně',
                    'body' => 'U hlavních kategorií a nejprodávanějších produktů napíšeme titulky a popisy podle toho, co rodiče opravdu hledají. '
                        .'Texty v kategoriích máte dobré, stavíme na nich.',
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
                    '_after' => 'Refresh úvodní stránky',
                    'when' => '1. týden',
                    'title' => 'Lehčí úvodní stránka a cookie lišta',
                    'body' => 'Widgety Facebooku a mapy pryč, menší fotky, nástroje třetích stran až po souhlasu.',
                    'later' => false,
                ],
                [
                    '_after' => 'Zimní a vánoční stránka',
                    'when' => '3. týden',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Hlavní kategorie a nejprodávanější produkty.',
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
