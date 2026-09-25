<?php

use App\Models\Audit;
use App\Models\ChecklistCategory;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * Z auditu a checklistu Světa Cejlonu mizí odhady času u jednotlivých úkolů
 * (sloupec „Práce“ v Top 12, „15 min“ u oprav, „asi 3 hodiny práce“ u fází).
 *
 * Proč: klient si podle minut vybírá jen to nejrychlejší, nebo si je sčítá
 * a přepočítává proti ceně. Cenu dáváme za celý balíček, ne za položky.
 *
 * Každá náhrada proběhne, jen když je původní text beze změny. Co už někdo
 * upravil v administraci, zůstane, jak je.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    private const CLIENT_SLUG = 'svet-cejlonu';

    /** @var list<array{0: string, 1: string}> */
    private const AUDIT = [
        [
            'Seřazeno podle poměru dopad / práce. Sloupec „Kdo“',
            'Seřazeno podle dopadu a náročnosti. Sloupec „Kdo“',
        ],
        [
            '| # | Úkol | Dopad | Práce | Kdo |
|---|---|---|---|---|',
            '| # | Úkol | Dopad | Kdo |
|---|---|---|---|',
        ],
        [
            '| 1 | **Založit Google Search Console, Bing Webmaster Tools a Seznam Webmaster** a odeslat sitemapu | [[kritický]] | 30 min | klient + my |',
            '| 1 | **Založit Google Search Console, Bing Webmaster Tools a Seznam Webmaster** a odeslat sitemapu | [[kritický]] | klient + my |',
        ],
        [
            '| 2 | **Filtrační stránky označit jako „neindexovat“** (1 786 URL). Výjimku dostane jen pár štítků s vlastním textem | [[kritický]] | 15 min | administrace |',
            '| 2 | **Filtrační stránky označit jako „neindexovat“** (1 786 URL). Výjimku dostane jen pár štítků s vlastním textem | [[kritický]] | administrace |',
        ],
        [
            '| 3 | **Vypnout varianty v sitemapě** (104 URL, například `/p/lotovovy-kvet/203`, kterou už Bing zaindexoval) | [[kritický]] | 5 min | administrace |',
            '| 3 | **Vypnout varianty v sitemapě** (104 URL, například `/p/lotovovy-kvet/203`, kterou už Bing zaindexoval) | [[kritický]] | administrace |',
        ],
        [
            '| 4 | **Smazat ukázkový obsah Upgates**: 4 aktuality, rádce „brož“, výrobce „Upgates“, stránku `/why-us` a pomocné `/oznameni-*` vyřadit z indexu | [[kritický]] | 20 min | administrace |',
            '| 4 | **Smazat ukázkový obsah Upgates**: 4 aktuality, rádce „brož“, výrobce „Upgates“, stránku `/why-us` a pomocné `/oznameni-*` vyřadit z indexu | [[kritický]] | administrace |',
        ],
        [
            '| 5 | **Zeptat se podpory Upgates na odblokování GPTBot a ClaudeBot** (dnes vrací 503) a na kanonické URL u variant a filtrů | [[vysoký]] | 1 e-mail | podpora Upgates |',
            '| 5 | **Zeptat se podpory Upgates na odblokování GPTBot a ClaudeBot** (dnes vrací 503) a na kanonické URL u variant a filtrů | [[vysoký]] | podpora Upgates |',
        ],
        [
            '| 6 | **Titulek a popisek úvodky, 5 kategorií a obsahových stránek** (hotové návrhy v příloze) | [[vysoký]] | 1–2 h | administrace |',
            '| 6 | **Titulek a popisek úvodky, 5 kategorií a obsahových stránek** (hotové návrhy v příloze) | [[vysoký]] | administrace |',
        ],
        [
            '| 7 | **JSON-LD Organization / OnlineStore** se `sameAs` (Instagram, Facebook, Heureka, Firmy.cz) a opravit LocalBusiness („Marek Bezdíček“, `$$$$$$`) | [[vysoký]] | 1 h | my (šablona) |',
            '| 7 | **JSON-LD Organization / OnlineStore** se `sameAs` (Instagram, Facebook, Heureka, Firmy.cz) a opravit LocalBusiness („Marek Bezdíček“, `$$$$$$`) | [[vysoký]] | my (šablona) |',
        ],
        [
            '| 8 | **Opravit mikrodata recenzí** (`reviewRating`, formát data), aby Google mohl ukazovat hvězdičky | [[vysoký]] | 1 h | my (šablona) |',
            '| 8 | **Opravit mikrodata recenzí** (`reviewRating`, formát data), aby Google mohl ukazovat hvězdičky | [[vysoký]] | my (šablona) |',
        ],
        [
            '| 9 | **Vlastní úvodní text kategorií** (150–300 slov) + FAQ blok v každé kategorii | [[vysoký]] | 1 den | klient (text) + my |',
            '| 9 | **Vlastní úvodní text kategorií** (150–300 slov) + FAQ blok v každé kategorii | [[vysoký]] | klient (text) + my |',
        ],
        [
            '| 10 | **Rádce: 6–10 odborných návodů** (skořice cejlonská vs. kasie, jak louhovat, co je Pekoe/BOP, moringa…), které cituje AI | [[vysoký]] | průběžně | klient |',
            '| 10 | **Rádce: 6–10 odborných návodů** (skořice cejlonská vs. kasie, jak louhovat, co je Pekoe/BOP, moringa…), které cituje AI | [[vysoký]] | klient |',
        ],
        [
            '| 11 | **Sjednotit Firmy.cz** (dnes dva záznamy: „Svetcejlonu.cz“ a „Svět Cejlonu“) a založit Google Business Profile | [[střední]] | 30 min | klient |',
            '| 11 | **Sjednotit Firmy.cz** (dnes dva záznamy: „Svetcejlonu.cz“ a „Svět Cejlonu“) a založit Google Business Profile | [[střední]] | klient |',
        ],
        [
            '| 12 | **Projít zdravotní tvrzení** u ájurvédy a doplňků („bolest hlavy“, „plísňové potíže“, „zubní první pomoc“) | [[střední]] | 2 h | klient |',
            '| 12 | **Projít zdravotní tvrzení** u ájurvédy a doplňků („bolest hlavy“, „plísňové potíže“, „zubní první pomoc“) | [[střední]] | klient |',
        ],
        [
            'Oprava zabere pár minut v administraci, viz Indexace.',
            'Oprava je v administraci, viz Indexace.',
        ],
        [
            '> **Oprava (administrace, 15 min):**',
            '> **Oprava (administrace):**',
        ],
        [
            '> **Oprava:** v šabloně (`logo.phtml`, pozor, má vlastní odkaz), 5 minut.',
            '> **Oprava:** v šabloně (`logo.phtml`, pozor, má vlastní odkaz).',
        ],
        [
            'připravíme ho za 15 minut.',
            'připravíme ho.',
        ],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const CATEGORIES = [
        [
            'Zapnout nástroje, které ukážou, co Google vidí, a uklidit tisíce zbytečných adres. Celkem asi 3 hodiny práce.',
            'Zapnout nástroje, které ukážou, co Google vidí, a uklidit tisíce zbytečných adres.',
        ],
        [
            'Jak e-shop vypadá ve výsledcích hledání a podle čeho ho Google a AI poznají jako firmu. Asi 2 dny práce.',
            'Jak e-shop vypadá ve výsledcích hledání a podle čeho ho Google a AI poznají jako firmu.',
        ],
    ];

    public function up(): void
    {
        $this->apply(fn (array $pair): array => $pair);
    }

    public function down(): void
    {
        $this->apply(fn (array $pair): array => [$pair[1], $pair[0]]);
    }

    private function apply(callable $direction): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit) {
            $body = (string) $audit->body;

            foreach (self::AUDIT as $pair) {
                [$from, $to] = $direction($pair);

                if (substr_count($body, $from) === 1) {
                    $body = str_replace($from, $to, $body);
                }
            }

            $audit->update(['body' => $body]);
        }

        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client) {
            return;
        }

        $checklistIds = $client->checklists()->pluck('id');

        foreach (self::CATEGORIES as $pair) {
            [$from, $to] = $direction($pair);

            ChecklistCategory::whereIn('checklist_id', $checklistIds)
                ->where('description', $from)
                ->update(['description' => $to]);
        }
    }
};
