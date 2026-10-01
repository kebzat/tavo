<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Emm's play: cookie lišta, SEO kategorií podle věku a redesign jen jako
 * možnost. Ověřeno 1. 10. 2026 na emmsplay.cz v čistém prohlížeči s běžným
 * user agentem (stránka vypadala normálně) bez kliknutí do lišty: Google
 * Analytics a Google Ads bez Consent Mode (gcd se samými „l“, cookies _ga
 * a _gcl_au, ga-audiences), retargeting Seznamu a cookie _fbp od Mety.
 * Lišta Complianz má jen „Příjmout“, Nastavení a křížek. Kategorie Miminka
 * až Školáci mají titulky „X - emmsplay.cz“ a popisy 270 až 470 znaků,
 * úvodní stránka tři H1, Miminka žádné. Hlavní kategorie mají ručně psané
 * titulky, popisy i texty. Váha úvodní stránky v pořádku (mobil 1,7 až
 * 2,5 MB, žádný obří soubor), proto ji nezmiňujeme.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('emms-play', [
            'findings' => [
                [
                    '_after' => 'Vánoce na webu zatím nejsou',
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Google, Seznam a Meta měří ještě před souhlasem',
                    'body' => 'Hned při otevření webu, bez jakéhokoli kliknutí v cookie liště, se spustí Google Analytics a Google Ads '
                        .'včetně remarketingu a retargeting Seznamu. Web si zároveň uloží cookies Googlu a identifikátor Mety pro reklamy. '
                        .'Google nemá nastavený režim, ve kterém na souhlas počká (Consent Mode). Podle českého zákona o elektronických '
                        .'komunikacích a pravidel EU smí tohle všechno začít až po souhlasu. Lišta navíc nabízí jen Příjmout (s překlepem) '
                        .'a Nastavení, odmítnout jde jen malým křížkem v rohu. Úřad pro ochranu osobních údajů i evropští regulátoři chtějí, '
                        .'aby odmítnutí bylo stejně snadné jako souhlas (ověřeno 1. 10. 2026).',
                ],
                [
                    '_after' => 'V kategoriích se špatně vybírá',
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Kategorie podle věku mají titulky z automatu',
                    'body' => 'Hlavní kategorie máte pro Google připravené dobře: Smyslové hraní, Hračky pro děti nebo Dřevěné hračky mají '
                        .'ručně psané titulky, popisy i dlouhé texty, které radí. Kategorie podle věku, ve kterých se před Vánoci vybírají '
                        .'dárky, ale mají titulky typu „Tříleťáci (3+ roky) - emmsplay.cz“. Hračky pro tříleté dítě, které rodiče hledají, '
                        .'v nich nejsou, a popisy mají 270 až 470 znaků, takže je Google ořízne. Úvodní stránka má tři hlavní nadpisy '
                        .'a žádný neříká, co prodáváte. Kategorie Miminka nemá ani jeden.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Tlačítko Odmítnout vedle Přijmout a Google, Seznam i Meta spuštěné až po souhlasu. Google přes Consent Mode, '
                        .'aby měření o odmítnuté návštěvy nepřišlo úplně. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                        .'aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Vánoce na úvodní stránce',
                    'who' => 'Tom',
                    'title' => 'Kategorie podle věku pro Google',
                    'body' => 'Titulky a krátké popisy u kategorií Miminka až Školáci podle toho, co rodiče hledají, a jeden hlavní nadpis '
                        .'na úvodní stránce. Texty hlavních kategorií máte dobré, stavíme na nich.',
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
                    '_after' => 'Úklid úvodní stránky',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a měření',
                    'body' => 'Tlačítko pro odmítnutí, Google, Seznam a Meta až po souhlasu.',
                    'later' => false,
                ],
                [
                    '_after' => 'Vánoční nabídka',
                    'when' => '2. týden',
                    'title' => 'Kategorie podle věku pro Google',
                    'body' => 'Titulky a popisy od Miminek po Školáky, jeden hlavní nadpis na úvodní stránce.',
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
