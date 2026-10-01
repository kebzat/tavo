<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Snowbear: cookie lišta, váha úvodní stránky, SEO služeb a redesign jen
 * jako možnost. Ověřeno 1. 10. 2026 na snowbear.cz v čistém prohlížeči bez
 * kliknutí do lišty: běží pixel Facebooku (fbevents.js, facebook.com/tr,
 * cookie _fbp), retargeting Seznamu a Microsoft Clarity, Google Analytics
 * čeká (gcs=G100, bez _ga). Lišta má jen Souhlasím a Nastavení, text o
 * pohodlném prohlížení a analýze. Úvodní stránka na mobilu 6,9 a 7,7 MB
 * hned při otevření (dvě měření), 8,5 až 8,8 MB po projetí, ~130 požadavků,
 * obrázky 5,4 MB: about-image.png 1,2 MB (PNG 1920 × 1080), dlaždice
 * služeb eshop/pujcovna/servis/skola/akademie.png 0,4 až 0,7 MB (PNG
 * 1500 × 700). SEO služeb v pořádku (Půjčovna lyží Hradec Králové, Servis
 * lyží Hradec Králové, kategorie s texty, Product microdata), jen
 * celosezónní půjčovna má titulek „Výpůjčka lyží na celou sezonu“.
 * Titulek úvodní stránky už koncept řeší, neopakujeme.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('snowbear', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Pixel Facebooku a Seznam běží ještě před souhlasem',
                    'body' => 'Hned při otevření webu, než návštěvník v cookie liště cokoli klikne, se spustí pixel Facebooku, retargeting '
                        .'Seznamu a Microsoft Clarity, který nahrává, co na webu dělá. Facebook si rovnou uloží svou cookie. Podle českého '
                        .'zákona o elektronických komunikacích a pravidel EU smí tohle všechno začít až po souhlasu. Lišta navíc nabízí jen '
                        .'Souhlasím a Nastavení a píše o pohodlném prohlížení a analýze, o reklamě ani slovo. Úřad pro ochranu osobních '
                        .'údajů i evropští regulátoři chtějí, aby odmítnutí bylo stejně snadné jako souhlas. Google Analytics máte '
                        .'nastavené správně, na souhlas čeká (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Úvodní stránka stahuje kolem 8 MB, hlavně obrázky',
                    'body' => 'Na telefonu stáhne úvodní stránka hned při otevření 7 až 8 MB, z toho přes 5 MB jsou obrázky. Největší je '
                        .'obrázek u textu o vás dole na stránce, který má sám 1,2 MB. Pět velkých dlaždic se službami (půjčovna, servis, '
                        .'škola, akademie, e-shop) má každá 0,4 až 0,7 MB, protože jsou uložené jako PNG. Na wifi to skoro nepoznáte, '
                        .'na mobilních datech se ale stránka načítá zbytečně dlouho. Vzhled se kvůli tomu měnit nemusí, stačí obrázky '
                        .'zmenšit a převést do moderního formátu.',
                ],
                [
                    'priority' => 'later',
                    'tone' => 'strength',
                    'title' => 'Půjčovna a servis mají v Googlu vlastní stránky s Hradcem',
                    'body' => 'Půjčovna lyží Hradec Králové a Servis lyží Hradec Králové mají vlastní stránky s nadpisem i titulkem, '
                        .'jaký lidé hledají. Kategorie mají texty a lyže v e-shopu strukturovaná data s cenou. Tady máte náskok. '
                        .'Jen celosezónní půjčovně, která je teď nejdůležitější, chybí v titulku Hradec i Snowbear, stojí tam jen '
                        .'„Výpůjčka lyží na celou sezonu“.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Tlačítko Odmítnout hned vedle Souhlasím a text lišty, který říká, co na webu opravdu běží. Facebook, Seznam '
                        .'a Clarity spustíme až po souhlasu, stejně jako už to funguje u Googlu. Nejsme právníci, technickou stránku ale '
                        .'umíme nastavit tak, aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Cookie lišta a měření podle pravidel',
                    'who' => 'Tom',
                    'title' => 'Lehčí obrázky na úvodní stránce',
                    'body' => 'Dlaždice se službami a obrázek dole převedeme do moderního formátu a zmenšíme na velikost, ve které se '
                        .'opravdu zobrazují. K tomu Hradec Králové do titulku celosezónní půjčovny. Vzhled zůstane, stránka jen zhubne.',
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
                    '_after' => 'Úvodní stránka na zimu',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a lehčí obrázky',
                    'body' => 'Tlačítko pro odmítnutí, Facebook, Seznam a Clarity až po souhlasu, menší obrázky na úvodní stránce.',
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
