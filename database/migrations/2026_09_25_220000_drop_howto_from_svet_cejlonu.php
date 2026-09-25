<?php

use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Z auditu a checklistu Světa Cejlonu mizí návody, kde a jak se co opravuje
 * (cesty v administraci Upgates, „v editoru přepnout na Nadpis 2“), a z auditu
 * hotové titulky a popisky pro všechny stránky. Klient z nich má vidět, co je
 * špatně a co s tím uděláme, ne postup, podle kterého si to udělá sám.
 * Z návrhů titulků zůstává jen ukázka pro úvodku.
 *
 * Audit se přepíše celý, jen když je beze změny od minulé migrace. Položka
 * checklistu se změní, jen když má pořád původní text.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    /** Otisk textu auditu po migraci 2026_09_25_190000. */
    private const OLD_BODY_SHA1 = 'ff5574523a986b9f9ac71a7867ec22d5e4cb7ae9';

    private const CLIENT_SLUG = 'svet-cejlonu';

    /**
     * [název položky, původní vysvětlivka, nová vysvětlivka]
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const ITEMS = [
        [
            'Přidat web do Bing Webmaster Tools',
            'Po založení Search Console se web do Bingu naimportuje jedním kliknutím. Z Bingu čerpá ChatGPT Search i Copilot, takže pro AI je stejně důležitý jako Google.',
            'Z Bingu čerpá ChatGPT Search i Copilot, takže pro AI je stejně důležitý jako Google.',
        ],
        [
            'Filtrační stránky označit jako „neindexovat“',
            'V administraci: Nastavení → Produkty → Filtry a řazení → označit vše → hromadná akce „Označit jako neindexovat“. Zmizí tím i ze sitemapy. Jde o 1 786 adres jako /caje/p-tip/s-citronem-zvyrazni-svezest-nalevu, které vznikly po hromadném importu parametrů produktů.',
            'Jde o 1 786 adres jako /caje/p-tip/s-citronem-zvyrazni-svezest-nalevu, které vznikly po hromadném importu parametrů produktů. Po úpravě zmizí z indexu i ze sitemapy.',
        ],
        [
            'Vyřadit varianty produktů ze sitemapy',
            'V administraci: Nastavení → Rozšířené → SEO → nastavení sitemap → vyloučit varianty. Jde o 104 adres typu /p/lotovovy-kvet/203. Ve výsledcích vyhledávání se už taková varianta objevuje místo hlavního produktu.',
            'Jde o 104 adres typu /p/lotovovy-kvet/203. Ve výsledcích vyhledávání se už taková varianta objevuje místo hlavního produktu.',
        ],
        [
            'Pomocné stránky /oznameni-* a /why-us nastavit jako „neindexovat“',
            'Stránky /oznameni-dovolena, /oznameni-kosik a /oznameni-lista jsou jen zdroj textů pro vyskakovací okno a lištu. Nemazat, skript je potřebuje, jen v jejich SEO záložce zapnout „neindexovat“. /why-us je prázdná stránka „Naše výhody“: buď ji naplnit, nebo taky neindexovat.',
            'Stránky /oznameni-dovolena, /oznameni-kosik a /oznameni-lista jsou jen zdroj textů pro vyskakovací okno a lištu. Smazat je nejde, web je potřebuje. /why-us je prázdná stránka „Naše výhody“.',
        ],
        [
            'Vyřadit textové parametry z filtrů kategorií',
            'Tip, Složení, Použití, Upozornění, Skvělé kombinace a Doporučujeme. Zákazník podle nich nefiltruje a z každé hodnoty vzniká další stránka. V administraci: Kategorie → Seznam kategorií → kategorie → Filtry.',
            'Tip, Složení, Použití, Upozornění, Skvělé kombinace a Doporučujeme. Zákazník podle nich nefiltruje a z každé hodnoty vzniká další stránka.',
        ],
        [
            'Smazat 4 ukázkové aktuality',
            'V administraci: Obsah → Aktuality. „Ke každému produktu dárek“, „Rozšířili jsme nabídku látek“, „Objednávejte nyní i přes Zásilkovnu“ a „Připravujeme novou kolekci!“. Text je generovaný nesmysl a Google kvůli němu hodnotí hůř celý web.',
            '„Ke každému produktu dárek“, „Rozšířili jsme nabídku látek“, „Objednávejte nyní i přes Zásilkovnu“ a „Připravujeme novou kolekci!“. Text je generovaný nesmysl a Google kvůli němu hodnotí hůř celý web.',
        ],
        [
            'Smazat návod na brož z Rádce',
            'V administraci: Obsah → Rádce → „Ozdobte se vlastnoručně dělanou broží!“. Místo něj můžou přijít skutečné návody, viz poslední část checklistu.',
            '„Ozdobte se vlastnoručně dělanou broží!“ je ukázka ze šablony Upgates. Místo něj můžou přijít skutečné návody, viz poslední část checklistu.',
        ],
        [
            'Smazat výrobce „Upgates“',
            'V administraci: Produkty → Výrobci. Stránka /m/upgates je dnes v sitemapě.',
            'Stránka /m/upgates je dnes v sitemapě.',
        ],
        [
            'Povolit AI roboty v robots.txt',
            'V administraci: Nastavení → Rozšířené → SEO. Dnes je robots.txt neblokuje, jasné pravidlo ale pomůže, až Upgates blokaci zruší.',
            'Dnes je robots.txt neblokuje, jasné pravidlo ale pomůže, až Upgates blokaci zruší.',
        ],
        [
            'Změnit šablonu popisku, aby neopakovala titulek',
            'V administraci: Nastavení → Rozšířené → SEO. Dnes z ní vzniká třeba „Podpora tamilské školy na Srí Lance. Podpora tamilské školy na Srí Lance :: Svět Cejlonu“.',
            'Dnes z ní vzniká třeba „Podpora tamilské školy na Srí Lance. Podpora tamilské školy na Srí Lance :: Svět Cejlonu“.',
        ],
        [
            'Doplnit popisek u 6 produktů, které ho nemají',
            'Chilli, Dárková krabice velká, Dárková krabice malá, Přenosná čajová sada, Samahan a Skleněný louhovač. Popisek se bere z krátkého popisu produktu.',
            'Chilli, Dárková krabice velká, Dárková krabice malá, Přenosná čajová sada, Samahan a Skleněný louhovač.',
        ],
        [
            'Opravit vícenásobné hlavní nadpisy v popisech',
            'Stránka má mít jeden hlavní nadpis (H1). Ayush pleťový krém jich má pět, včetně řádků z podtržítek. Totéž u Kardamonu, Navratny a na stránce O nás. V editoru přepnout na „Nadpis 2“, podtržítka smazat.',
            'Stránka má mít jeden hlavní nadpis (H1). Ayush pleťový krém jich má pět, včetně řádků z podtržítek. Totéž u Kardamonu, Navratny a na stránce O nás.',
        ],
    ];

    public function up(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit && sha1((string) $audit->body) === self::OLD_BODY_SHA1) {
            $audit->update(['body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md'))]);
        }

        $this->items(fn (array $item): array => [$item[0], $item[1], $item[2]]);
    }

    public function down(): void
    {
        // Text auditu zpátky neskládáme, předchozí verze je v historii gitu.
        $this->items(fn (array $item): array => [$item[0], $item[2], $item[1]]);
    }

    private function items(callable $direction): void
    {
        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client) {
            return;
        }

        $checklistIds = $client->checklists()->pluck('id');

        foreach (self::ITEMS as $item) {
            [$title, $from, $to] = $direction($item);

            DB::table('checklist_items')
                ->whereIn('checklist_id', $checklistIds)
                ->where('title', $title)
                ->where('description', $from)
                ->update(['description' => $to, 'updated_at' => now()]);
        }
    }
};
