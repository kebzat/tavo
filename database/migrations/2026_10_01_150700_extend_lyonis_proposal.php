<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Lyonis: cookies a Leadhub před souhlasem, pochvala SEO s výjimkou starého
 * Universal Analytics, redesign jen jako možnost. Ověřeno 1. 10. 2026 na
 * lyonis.cz (Shoptet) v čistém prohlížeči bez kliknutí do lišty: lišta má
 * Odmítnout vedle Souhlasím, text ale mluví jen o fungování a zlepšování
 * e-shopu, o reklamě nic. Před souhlasem Leadhub Insights (lhinsights.com)
 * uloží cookie _lhic s platností 2 roky a pošle ji v požadavku /popup,
 * načtou se skripty Facebooku (sdk.js, bez pixelu). Google čeká (gcs=G100),
 * retargeting Seznamu jde s consent=0, Meta pixel, Google Ads a Seznam
 * běží až po souhlasu. Načítá se analytics.js a ec.js (Universal Analytics,
 * UA-125795881-1). SEO: ručně psané titulky a popisy (úvod, kategorie,
 * detail Chodím do školky), texty kategorií, na detailu Product +
 * AggregateRating + Review v microdata. Alt chybí jen u náhledů v menu.
 * Váha úvodní stránky na mobilu 5,2 až 6,6 MB, bez jednoho velkého viníka
 * (největší soubor CSS Pobo 0,8 MB), proto bez nálezu.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('lyonis', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Leadhub si návštěvníka označí ještě před souhlasem',
                    'body' => 'Hned při otevření webu, bez kliknutí v cookie liště, si Leadhub uloží do prohlížeče identifikátor návštěvníka '
                        .'s platností dva roky a pošle ho na svůj server. Načtou se i skripty Facebooku. Podle českého zákona o elektronických '
                        .'komunikacích a pravidel EU smí tohle začít až po souhlasu. Lišta má správně Odmítnout vedle Souhlasím, '
                        .'jen píše, že cookies pomáhají e-shopu fungovat a zlepšovat se. O reklamě ani slovo, přitom po souhlasu běží '
                        .'Meta pixel, Google Ads a retargeting Seznamu. Google a Seznam přitom máte nastavené správně, '
                        .'na souhlas čekají (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'later',
                    'tone' => 'strength',
                    'title' => 'Titulky a texty pro Google máte napsané ručně',
                    'body' => 'Titulky a popisy jsou psané ručně, třeba „Magnetický kalendář pro děti ve školce + 80 magnetek“. '
                        .'Kategorie mají vlastní texty a hodnocení u produktů jsou zapsaná tak, že je Google může ukázat jako hvězdičky '
                        .'přímo ve výsledcích. Uklidili bychom jen jednu věc: web pořád načítá starý Universal Analytics, '
                        .'který Google v roce 2024 vypnul. Nic už neměří, jen zdržuje.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Vánoční úvodní stránka do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Leadhub a skripty Facebooku spuštěné až po souhlasu, stejně jako už to funguje u Googlu. V textu lišty zmínka '
                        .'o reklamě a pryč se starým Universal Analytics. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                        .'aby odpovídala pravidlům a data z reklam byla čistá.',
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
                    '_after' => 'Vánoční uzávěrka',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a měření',
                    'body' => 'Leadhub a Facebook až po souhlasu, nový text lišty, pryč se starým Universal Analytics.',
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
