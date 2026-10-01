<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Zubař Pardubice: váha úvodní stránky, titulky pro Google, měření bez
 * cookie lišty a redesign jen jako možnost. Ověřeno 1. 10. 2026 na
 * zubarpardubice.cz (WordPress.com s ochranou proti robotům, měřeno
 * prohlížečem bez příznaku automatizace): na mobilu 8,9 MB hned při
 * otevření (dvě měření stejně), ~46 požadavků, z toho fotka
 * finc3a1lnc3ad-verze3.jpg 5,9 MB (6142 × 3565 px, pozadí sekce Máte
 * strach ze zubního ošetření?) a shutterstock_266698076.jpg 2 MB
 * (6016 × 4016 px, úvodní fotka). Titulek úvodní stránky 557 znaků
 * s výčtem 25 měst, meta description totéž. Stránky služeb mají H1
 * a text, popis se ale skládá automaticky z prvního odstavce. Site Kit
 * nastavuje Consent Mode na „zamítnuto“ (gcs=G100), cookie lišta ani
 * plugin pro souhlas na webu nejsou, tracking cookies se neukládají.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('zubar-pardubice', [
            'findings' => [
                [
                    '_after' => 'Telefon nejde vytočit a objednat se jde jen voláním',
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Dvě fotky dělají z úvodní stránky skoro 9 MB',
                    'body' => 'Na telefonu stáhne úvodní stránka hned při otevření kolem 9 MB. Skoro všechno jsou dvě fotky: obrázek '
                        .'pod textem „Máte strach ze zubního ošetření?“ má 5,9 MB, úvodní fotka 2 MB. '
                        .'Obě mají přes 6 000 px na šířku, víc, než potřebuje i velký monitor. Na mobilních datech se stránka načítá zbytečně dlouho. '
                        .'Zmenšení fotek je rychlá práce a vzhled se nezmění.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Titulek pro Google je výčet 25 měst',
                    'body' => 'Stránky jednotlivých služeb mají vlastní nadpis a text, to je dobrý základ. Titulek úvodní stránky má ale '
                        .'557 znaků: po názvu ordinace následuje seznam výkonů a 25 měst od Chrudimi po Hradec Králové. Google ukáže '
                        .'jen začátek a takové výčty si obvykle přepíše po svém. Popis pro vyhledávače je stejný seznam. '
                        .'U služeb si web popis bere sám z prvního odstavce, takže ve výsledcích je useknutá věta.',
                ],
                [
                    'priority' => 'later',
                    'tone' => 'problem',
                    'title' => 'Google Analytics čeká na souhlas, který nejde dát',
                    'body' => 'Google Analytics a Google Ads máte nastavené správně: bez souhlasu neukládají cookies a čekají. '
                        .'Cookie lišta, ve které by návštěvník souhlas dal, ale na webu není. Souhlas tak nedá nikdo a data '
                        .'o tom, odkud lidé na web chodí a co na něm dělají, budou hodně neúplná (ověřeno 1. 10. 2026).',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit úvodní stránku do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Lehčí úvodní stránka',
                    'body' => 'Obě velké fotky zmenšíme na rozměr, ve kterém se opravdu zobrazují, a převedeme do moderního formátu. '
                        .'Na současném WordPressu, vzhled zůstane.',
                ],
                [
                    '_after' => 'Formulář Zavoláme vám a čitelný ceník',
                    'who' => 'Tom',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Krátký titulek úvodní stránky s Pardubicemi a tím, co ordinace nabízí, místo výčtu měst. U služeb ručně '
                        .'psané popisy. Texty ke službám už máte, stavíme na nich.',
                ],
                [
                    '_after' => 'Cesta k ordinaci bez bloudění',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta, aby měření mělo data',
                    'body' => 'Lišta s tlačítky Souhlasím a Odmítnout vedle sebe, napojená na Google Site Kit, který už na souhlas čeká. '
                        .'Nejsme právníci, technickou stránku ale umíme nastavit tak, aby odpovídala pravidlům a data byla čistá.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Redesign jako možnost, ne podmínka',
                    'body' => 'Návrh nové homepage výš ukazuje, kam by se web mohl posunout, a nový vzhled by mu prospěl. '
                        .'Nic z toho, co tu píšeme, na něm ale nestojí. Začneme úpravami a o větší změně se pobavíme, '
                        .'až uvidíme, jak se pacienti objednávají.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Rychlé opravy úvodní stránky',
                    'when' => '1. týden',
                    'title' => 'Menší fotky a cookie lišta',
                    'body' => 'Obě velké fotky zmenšené, lišta se souhlasem a odmítnutím.',
                    'later' => false,
                ],
                [
                    '_after' => 'Snazší objednání',
                    'when' => '2. týden',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Úvodní stránka bez výčtu měst a popisy u služeb.',
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
