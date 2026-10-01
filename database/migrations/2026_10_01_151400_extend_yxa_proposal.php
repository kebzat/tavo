<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * YXA: cookie lišta, konverze Google Ads, texty stránek plotů a redesign
 * jen jako možnost. Ověřeno 1. 10. 2026 na yxa.cz v čistém prohlížeči:
 * web nemá cookie lištu ani Consent Mode, Google Ads (AW-11283312090)
 * se spustí hned a uloží cookie _gcl_au. Na úvodní stránce, v Kontaktech
 * a na stránkách Lamelové, Designové a Ploty z tahokovu je v hlavičce
 * snippet konverze „Kliknutí na telefon“ bez podmínky, při načtení odejdou
 * požadavky pagead/conversion a 1p-conversion. Stránky typů plotů mají
 * ručně psaný titulek, popis i H1, text ale jen pár vět (~120 slov
 * včetně menu a patičky). Váha úvodní stránky v pořádku (mobil 2,3 MB),
 * reference už řeší původní nabídka.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('yxa', [
            'findings' => [
                [
                    '_after' => 'Reference se na mobilu načítají desítky megabajtů',
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Měření zavolání se odešle při každém otevření stránky',
                    'body' => 'Na úvodní stránce, v Kontaktech a na stránkách všech tří typů plotů je měření „Kliknutí na telefon“ pro Google Ads '
                        .'vložené tak, že se odešle hned při otevření stránky. Nikdo nemusí na telefon kliknout. Jestli ho účet započítává, '
                        .'zvenku nevidíme. Pokud ano, čísla o tom, kolik lidí z reklamy opravdu volá, nesedí a reklama se učí '
                        .'na špatných datech. Stačí měření navázat až na kliknutí na telefon.',
                ],
                [
                    '_after' => 'Měření zavolání se odešle při každém otevření stránky',
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Reklamní měření běží bez cookie lišty',
                    'body' => 'Web nemá cookie lištu. Značka Google Ads se spustí hned při otevření a uloží si cookie, a protože chybí '
                        .'i režim souhlasu od Googlu, nečeká na nic. Podle českého zákona o elektronických komunikacích a pravidel EU '
                        .'smí reklamní měření začít až po souhlasu. Návštěvník dnes nemá kde souhlasit ani odmítnout '
                        .'(ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Stránky plotů mají pro Google jen pár vět',
                    'body' => 'Titulky a popisy pro vyhledávače máte napsané ručně a každá stránka má hlavní nadpis, to je dobrý základ. '
                        .'Na stránce Lamelové ploty je ale kromě názvů motivů jen to, že jde o žaluziové ploty, že máte 12 motivů '
                        .'a že připravíte návrh na míru. Designové ploty a ploty z tahokovu na tom jsou stejně. Nic o materiálu, '
                        .'montáži ani o tom, proč hliník. Hradec Králové je jen v patičce. Google pak nemá podle čeho stránku ukázat '
                        .'lidem, kteří hledají hliníkový plot.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit poptávku a rychlost webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření, kterému jde věřit',
                    'body' => 'Konverzi Kliknutí na telefon navážeme na skutečné klepnutí na číslo. Přidáme lištu s tlačítky Souhlasím '
                        .'a Odmítnout vedle sebe a Google Ads necháme čekat na souhlas. Nejsme právníci, technickou stránku ale umíme '
                        .'nastavit tak, aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Orientační ceny a oblast, kde stavíte',
                    'who' => 'Tom',
                    'title' => 'Texty ke každému typu plotu',
                    'body' => 'Na stránky lamelových, designových a tahokovových plotů doplníme, z čeho se plot skládá, jak probíhá '
                        .'zaměření a montáž, kam jezdíte a od kolika korun začíná. Titulky a popisy máte dobré, stavíme na nich.',
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
                    'title' => 'Měření a cookie lišta',
                    'body' => 'Konverze telefonu jen po klepnutí, lišta s odmítnutím, Google Ads až po souhlasu.',
                    'later' => false,
                ],
                [
                    '_after' => 'Orientační ceny a oblast působnosti',
                    'when' => '2.–3. týden',
                    'title' => 'Texty k typům plotů',
                    'body' => 'Lamelové, designové a tahokovové ploty, s cenou od a oblastí, kam jezdíte.',
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
