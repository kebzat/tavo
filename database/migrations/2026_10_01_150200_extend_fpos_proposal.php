<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * FPOS: cookie lišta, titulky pro Google a redesign jen jako možnost.
 * Ověřeno 1. 10. 2026 na fpos.cz v čistém prohlížeči: lišta FreePrivacyPolicy
 * má Odmítám vedle Souhlasím a píše o reklamách, GA4 (G-NSZ171ZY28) je na
 * lištu napojený, Google Ads (AW-746097909) ale ne: bez kliknutí i po
 * Odmítám pošle page_view a uloží _gcl_au, YouTube video na úvodní stránce
 * uloží YSC a VISITOR_INFO1_LIVE. Sedm prověřených stránek (úvod, stroje,
 * CNC, automobilový průmysl, reference, kontakt, přípravky) má stejný
 * title i description, podstránky bez H1, 75 z 89 obrázků bez alt.
 * Váha úvodní stránky kolem 5 MB, LCP pod 0,5 s, nezmiňujeme.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('fpos', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Reklamní měření Googlu běží i po odmítnutí cookies',
                    'body' => 'Samotná lišta je v pořádku: Odmítám je hned vedle Souhlasím a text otevřeně píše o reklamách. '
                        .'Google Analytics na souhlas správně čeká. Měřicí kód Google Ads ale lištu obchází. Spustí se hned při otevření '
                        .'webu, uloží reklamní cookie a pošle Googlu návštěvu, a to i když návštěvník klikne na Odmítám. Video na úvodní '
                        .'stránce si k tomu bez souhlasu uloží cookies YouTube. Podle českého zákona o elektronických komunikacích '
                        .'a pravidel EU smí reklamní měření začít až po souhlasu. Oprava je malá, kód Google Ads stačí napojit na lištu '
                        .'stejně jako Analytics (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Všechny stránky mají pro Google stejný titulek',
                    'body' => 'Úvodní stránka, CNC obrábění, Výroba přípravků, Reference i Kontakt mají stejný titulek „Fpos a.s. - Vývoj '
                        .'a výroba jednoúčelových strojů“ a stejný popis pro vyhledávače. Ve výsledcích pak vypadají všechny stejně '
                        .'a Google těžko pozná, která odpovídá na dotaz „CNC obrábění“ nebo „výroba přípravků“. Podstránky nemají hlavní '
                        .'nadpis a většina obrázků nemá popisek, na Referencích 20 z 22. Popis úvodní stránky je přitom napsaný dobře, '
                        .'stejnou péči si zaslouží i ostatní.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Nejnutnější úpravy do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Kód Google Ads napojíme na lištu stejně, jako už je napojený Google Analytics, a video z YouTube se načte '
                        .'až po kliknutí. Lištu samotnou měnit nemusíte. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                        .'aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Cookie lišta a měření podle pravidel',
                    'who' => 'Tom',
                    'title' => 'Vlastní titulek a popis u každé stránky',
                    'body' => 'Každá stránka dostane titulek, popis a hlavní nadpis podle toho, co nákupčí hledají: jednoúčelové stroje, '
                        .'výroba přípravků, CNC obrábění, drátové řezání. K tomu popisky obrázků. Pokud to administrace dovolí, '
                        .'uděláme to i na dnešním webu.',
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
                    '_after' => 'Rychlé úpravy',
                    'when' => '1.–2. týden',
                    'title' => 'Cookie lišta a titulky pro Google',
                    'body' => 'Google Ads a YouTube až po souhlasu, vlastní titulek a popis u každé stránky.',
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
