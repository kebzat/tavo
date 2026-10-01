<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Hračkolka: SEO úvodní stránky a produktů, váha hlavního banneru
 * a redesign jen jako možnost. Ověřeno 1. 10. 2026 na hrackolka.cz:
 * úvodní stránka na mobilu 3,3 MB hned při otevření a 4,0 MB po projetí
 * (dvě měření), z toho makedo-banner.png 1,7 MB. Titulek úvodní stránky
 * „Prodej hraček – hračky pro děti i hry pro kluky a holky“, H1 „Vítejte
 * v Hračkolce!“, popisy produktů pro Google jsou useknutý začátek textu
 * s „...“. Kategorie (Tuff tray, Open ended, Pomůcky pro sensory play)
 * mají ručně psané titulky a texty 2 300 až 3 500 znaků.
 * Cookie lišta v pořádku (odmítnutí v první vrstvě, Google a Bing čekají
 * v režimu „zamítnuto“, Meta pixel neodesílá), jen tracker Ecomailu
 * uloží dvě cookies _sp_ před souhlasem. Drobnost, proto ji nezmiňujeme.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('hrackolka', [
            'findings' => [
                [
                    '_after' => 'Úvodní stránka mluví obecně a školky na ní nemají vstup',
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Úvodní stránka a produkty se Googlu představují obecně',
                    'body' => 'Kategorie máte pro Google připravené výborně: Tuff tray, Open ended hračky nebo Pomůcky pro sensory play mají '
                        .'ručně psané titulky a dlouhé texty, které radí. Úvodní stránka ale ve výsledcích hledání ukazuje titulek '
                        .'„Prodej hraček – hračky pro děti i hry pro kluky a holky“, bez jména Hračkolky a bez smyslového hraní, '
                        .'kterým se lišíte. Hlavní nadpis je „Vítejte v Hračkolce!“. Popis produktu pro Google je jen začátek textu z produktu, '
                        .'takže u senzorické krajiny Sahara končí slovem „co nejskute ...“.',
                ],
                [
                    '_after' => 'Hlavní banner na mobilu je zmenšený obrázek',
                    'priority' => 'later',
                    'tone' => 'problem',
                    'title' => 'Banner s Makedo je polovina váhy úvodní stránky',
                    'body' => 'Úvodní stránka stáhne na telefonu hned při otevření kolem 3,3 MB, což je v pořádku. Skoro polovinu z toho ale '
                        .'tvoří jediný obrázek, banner s Makedo, který má 1,7 MB. Stahuje se celý i na mobil, kde z něj zbyde úzký proužek. '
                        .'Vlastní menší banner pro mobil vyřeší obojí najednou.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Vánoce a advent na úvodní stránce',
                    'who' => 'Tom',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Titulek a hlavní nadpis úvodní stránky podle toho, čím se lišíte, tedy smyslové hraní, open-ended hračky '
                        .'a Kamishibai. U bestsellerů ručně psané popisy pro vyhledávače místo useknutého začátku textu. '
                        .'Texty kategorií máte dobré, stavíme na nich.',
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
                    '_after' => 'Vánoční nabídka',
                    'when' => '2. týden',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Úvodní stránka a nejprodávanější produkty.',
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
