<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Úsporná řešení: cookie lišta, SEO stránek služeb, váha úvodní stránky
 * a redesign jen jako možnost. Ověřeno 1. 10. 2026 na usporna-reseni.cz
 * v čistém prohlížeči: web nemá žádnou cookie lištu, hned při otevření
 * běží Facebook pixel (facebook.com/tr, cookie _fbp) a dvě videa z YouTube
 * s autoplay (cookies YSC, VISITOR_INFO1_LIVE), na Referencích widget
 * Firmy.cz spustí měření Seznamu (h.seznam.cz/hit). Žádná stránka nemá
 * meta description, Tepelná čerpadla, Fotovoltaika ani Klimatizace nemají
 * H1, v titulcích chybí Jičín. Úvodní stránka na mobilu 7,2 MB hned
 * po načtení (dvě měření), po projetí 12,3 MB, ~170 požadavků; YouTube
 * kolem 4,7 MB, PNG usporna-reseni-rekuperace-kmv1.png 2,3 MB.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('usporna-reseni', [
            'findings' => [
                [
                    '_after' => 'Na úvodní stránce chybí telefon i město',
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Facebook a YouTube běží bez jakékoli cookie lišty',
                    'body' => 'Web nemá cookie lištu vůbec. Hned při otevření se spustí Facebook pixel, který měří návštěvy pro reklamu '
                        .'a uloží si cookie, a dvě videa z YouTube, která se sama přehrají a uloží cookies YouTube. Na stránce Reference '
                        .'k tomu widget Firmy.cz spustí měření Seznamu. Podle českého zákona o elektronických komunikacích a pravidel EU '
                        .'smí tohle všechno začít až po souhlasu. V patičce máte stránku Cookies, návštěvník ale nemá kde souhlasit '
                        .'ani odmítnout (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Stránky služeb nemají pro Google popis ani Jičín',
                    'body' => 'Tepelná čerpadla, fotovoltaika i klimatizace mají vlastní stránky s textem, to je dobrý základ. Žádná stránka '
                        .'ale nemá popis pro vyhledávače, takže úryvek ve výsledcích si Google skládá sám. Stránky služeb nemají hlavní '
                        .'nadpis a v titulku je jen „Tepelná čerpadla – ÚSPORNÁ ŘEŠENÍ“. Jičín v něm není, takže při hledání '
                        .'„tepelné čerpadlo Jičín“ nemá Google z čeho poznat, že jste firma odtud.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Úvodní stránka stahuje přes 7 MB i bez kliknutí na video',
                    'body' => 'Na telefonu stáhne úvodní stránka hned při otevření kolem 7 MB a po projetí přes 12 MB. Skoro 5 MB z toho '
                        .'jsou dvě videa z YouTube, která se přehrávají sama, i když se na ně nikdo nedívá. Obrázek k rekuperaci má sám '
                        .'2,3 MB. Na mobilních datech se stránka načítá zbytečně dlouho. Vzhled se kvůli tomu měnit nemusí.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit kontakt, hodnocení a ceny do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Lišta s tlačítky Souhlasím a Odmítnout vedle sebe. Facebook pixel, videa z YouTube a widget Firmy.cz se spustí '
                        .'až po souhlasu. Nejsme právníci, technickou stránku ale umíme nastavit tak, aby odpovídala pravidlům '
                        .'a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Cookie lišta a měření podle pravidel',
                    'who' => 'Tom',
                    'title' => 'Lehčí úvodní stránka',
                    'body' => 'Videa z YouTube se načtou až po kliknutí na náhled, velké obrázky zmenšíme a převedeme do moderního formátu. '
                        .'V Elementoru, vzhled zůstane.',
                ],
                [
                    '_after' => 'Úvodní stránka, která odpoví na cenu a dotaci',
                    'who' => 'Tom',
                    'title' => 'Titulky, popisy a nadpisy pro Google',
                    'body' => 'U úvodní stránky a stránek služeb napíšeme titulky a popisy s Jičínem a tím, co lidé opravdu hledají, '
                        .'a doplníme hlavní nadpisy. Texty na stránkách služeb už máte, stavíme na nich.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Redesign jako možnost, ne podmínka',
                    'body' => 'Návrh nové homepage výš ukazuje, kam by se web mohl posunout, a nový vzhled by mu prospěl. '
                        .'Nic z toho, co tu píšeme, na něm ale nestojí. Začneme úpravami a o větší změně se pobavíme, '
                        .'až uvidíme, jaké poptávky z webu chodí.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Rychlé opravy webu',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a lehčí úvodní stránka',
                    'body' => 'Lišta s odmítnutím, Facebook a YouTube až po souhlasu, videa po kliknutí, menší obrázky.',
                    'later' => false,
                ],
                [
                    '_after' => 'Fotky a texty',
                    'when' => '3. týden',
                    'title' => 'Titulky a popisy pro Google',
                    'body' => 'Úvodní stránka a stránky služeb, s Jičínem.',
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
