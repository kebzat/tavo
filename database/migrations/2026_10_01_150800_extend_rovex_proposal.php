<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Rovex: video na úvodní stránce, cookie lišta, titulky pro Google
 * a redesign jen jako možnost. Ověřeno 1. 10. 2026 na rovex.cz (Wix)
 * v čistém prohlížeči: video v záhlaví je 1080p mp4, 2:41 min, 96,7 MB,
 * spouští se samo i na mobilu (390 px); přenos změřený přes CDP kolem
 * 21 až 22 MB za první 3 s, 42 až 49 MB za 30 s. Pixel Facebooku
 * (facebook.com/tr), retargeting Seznamu (s consent=1 bez kliknutí)
 * a Google Ads (AW-10951688035, bez Consent Mode) běží před souhlasem
 * i po kliknutí na Vše odmítnout. Lišta má Vše odmítnout v první vrstvě,
 * text nezmiňuje reklamu. Titulky podstránek jen „GUARDLUX | Rovex“
 * apod., popis má jen úvod a Jak objednat, H1 „Produkty“ z menu na
 * každé stránce, na Clearlux 10 přes 20 H1 včetně tabulky rozměrů.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('rovex', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Video na úvodní stránce stahuje desítky megabajtů',
                    'body' => 'Video v záhlaví úvodní stránky má 2 minuty 41 vteřin v plném HD a 97 MB. Spustí se samo a stejné se stahuje '
                        .'i do telefonu. V našem měření stáhla stránka kolem 20 MB během prvních tří vteřin a přes 40 MB za půl minuty. '
                        .'Kdo nechá web otevřený déle, stáhne celé video. Na mobilních datech se stránka načítá pomalu a ukrojí '
                        .'návštěvníkovi kus tarifu. Stačí krátká smyčka na pár vteřin v menším rozlišení, celé video si pustí, kdo bude chtít.',
                ],
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Facebook, Seznam a Google Ads měří i po odmítnutí cookies',
                    'body' => 'Lišta má tlačítko Vše odmítnout hned vedle Přijmout, to je správně. Na volbě ale nezáleží. Pixel Facebooku, '
                        .'retargeting Seznamu a měření Google Ads se spustí hned při otevření webu a běží dál, i když návštěvník klikne '
                        .'na Vše odmítnout. Seznamu web navíc posílá, že návštěvník souhlasil, ještě než na lištu klikne. Text lišty přitom '
                        .'mluví jen o tom, jak s webem zacházíte, o reklamě v něm není nic. Podle českého zákona o elektronických '
                        .'komunikacích a pravidel EU smí reklamní měření začít až po souhlasu (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Produkty mají pro Google jen svoje značky',
                    'body' => 'Stránky produktů mají v titulku jen vaše názvy: „GUARDLUX | Rovex“, „FRAMELUX 04 | Rovex“, „CLEARLUX 10 | Rovex“. '
                        .'Kdo hledá hliníkové zábradlí nebo posuvné dveře na terasu, tahle slova nezná. Popis pro vyhledávače má jen úvodní '
                        .'stránka a Jak objednat. Hlavním nadpisem je na každé stránce slovo „Produkty“ z menu a na stránce Clearlux 10 '
                        .'je hlavních nadpisů přes dvacet, včetně řádků tabulky s rozměry. Úvodní stránka má titulek i popis napsané dobře, '
                        .'stačí v tom pokračovat.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit úvodní stránku a poptávku do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Lehčí video na úvodní stránce',
                    'body' => 'Místo 97MB videa krátká smyčka na pár vteřin, pro telefony v menším rozlišení, a celé video až po kliknutí. '
                        .'Jde to udělat ve Wixu a vzhled úvodní stránky zůstane.',
                ],
                [
                    '_after' => 'Lehčí video na úvodní stránce',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Facebook, Seznam a Google Ads spustíme až po souhlasu a do textu lišty doplníme, že web používá i reklamní '
                        .'nástroje. Nejsme právníci, technickou stránku ale umíme nastavit tak, aby odpovídala pravidlům a data z reklam '
                        .'byla čistá.',
                ],
                [
                    '_after' => 'Web, který se na mobilu dá číst',
                    'who' => 'Tom',
                    'title' => 'Titulky a popisy, podle kterých vás lidé najdou',
                    'body' => 'U každého produktu titulek a popis se slovy, která lidé opravdu hledají: posuvné dveře, hliníkové zábradlí, '
                        .'zasklení balkonu, pergola. K tomu jeden hlavní nadpis na stránku. Všechno se nastavuje přímo ve Wixu.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Redesign jako možnost, ne podmínka',
                    'body' => 'Návrh nové úvodní stránky výš ukazuje, kam by se web mohl posunout. Ze všeho, co tu navrhujeme, by vám nový '
                        .'web pomohl nejvíc, hlavně kvůli telefonům. První kroky na něm ale nestojí: video, cookie lištu, poptávku i titulky opravíme ve Wixu. '
                        .'Začneme úpravami a o větší změně se pobavíme, až uvidíme, jaké poptávky z webu chodí.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Rychlé opravy úvodní stránky',
                    'when' => '1. týden',
                    'title' => 'Lehčí video a cookie lišta',
                    'body' => 'Krátké video v menším rozlišení, Facebook, Seznam a Google Ads až po souhlasu.',
                    'later' => false,
                ],
                [
                    '_after' => 'Úvodní stránka o vás',
                    'when' => '3. týden',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'U všech produktů, jeden hlavní nadpis na stránku.',
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
