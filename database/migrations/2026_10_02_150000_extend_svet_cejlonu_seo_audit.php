<?php

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Audit;
use App\Models\Checklist;
use App\Support\AuditMarkdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kompletní SEO audit Světa Cejlonu doplněný o souhrn technického crawlu,
 * interní odkazy, strukturovaná data, rozbor konkurence, nákupní cestu
 * a pravidla správy e-shopu. Tabulka „Stav podle oblastí“ odpovídá obsahu.
 *
 * Audit Tom mezitím upravoval v administraci. Nový text vychází z té verze
 * (stažené 2. 10. 2026) a přepíše se, jen když audit pořád vypadá stejně.
 * Porovnává se vykreslené HTML bez mezer, ne Markdown, protože přesné
 * znění z administrace známe jen ve vykreslené podobě.
 *
 * Do checklistu se úkoly jen přidají, nic se nemaže.
 */
return new class extends Migration
{
    private const AUDIT_SLUG = 'svet-cejlonu-seo-2026';

    private const CHECKLIST_SLUG = 'svet-cejlonu-seo-2026';

    /**
     * Otisky vykresleného textu, který se smí přepsat: verze z administrace
     * na ostrém webu a původní text z migrace 2026_10_02_110000 (lokálně).
     */
    private const ALLOWED_FINGERPRINTS = [
        '3a176375bdcd419c138a21139a3d137e0cbfc05b',
        'cc8481bf34079ddf4382d5d5d9d5d0f3aac110ea',
    ];

    private const CATEGORY_SLUG = 'doplneni-auditu';

    public function up(): void
    {
        $audit = Audit::where('slug', self::AUDIT_SLUG)->first();

        if (! $audit) {
            return;
        }

        DB::transaction(function () use ($audit): void {
            if (in_array(self::fingerprint((string) $audit->body), self::ALLOWED_FINGERPRINTS, true)) {
                $audit->update([
                    'body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-10-02-doplneni.md')),
                ]);
            }

            $this->addChecklistTasks();
        });
    }

    public function down(): void
    {
        // Text auditu se nevrací, předchozí verze žila jen v administraci.
        Checklist::where('slug', self::CHECKLIST_SLUG)->first()
            ?->categories()->where('slug', self::CATEGORY_SLUG)->get()
            ->each(fn ($category) => $category->delete());
    }

    private static function fingerprint(string $markdown): string
    {
        return sha1(preg_replace('/\s+/u', '', AuditMarkdown::render($markdown)['html']));
    }

    private function addChecklistTasks(): void
    {
        $checklist = Checklist::where('slug', self::CHECKLIST_SLUG)->first();

        if (! $checklist || $checklist->categories()->where('slug', self::CATEGORY_SLUG)->exists()) {
            return;
        }

        $category = $checklist->categories()->create([
            'title' => 'Doplnění auditu: crawl, odkazy a konkurence',
            'slug' => self::CATEGORY_SLUG,
            'description' => 'Úkoly z kapitol doplněných 2. 10. 2026. Patří do měsíců podle plánu v auditu.',
            'order_column' => ($checklist->categories()->max('order_column') ?? 0) + 1,
        ]);

        foreach (self::sections() as $sectionOrder => $section) {
            $newSection = $category->sections()->create([
                'title' => $section['title'],
                'description' => null,
                'order_column' => $sectionOrder + 1,
            ]);

            foreach ($section['items'] as $itemOrder => $item) {
                $newSection->items()->create([
                    'checklist_id' => $checklist->getKey(),
                    'title' => $item[0],
                    'priority' => $item[1],
                    'description' => $item[2],
                    'internal_note' => $item[3] ?? null,
                    'status' => ChecklistItemStatus::Todo,
                    'order_column' => $itemOrder + 1,
                ]);
            }
        }
    }

    /**
     * Sekce → položky [název, priorita, popis pro klienta, interní poznámka?].
     */
    private static function sections(): array
    {
        $must = ChecklistPriority::Must;
        $should = ChecklistPriority::Should;
        $nice = ChecklistPriority::Nice;

        return [
            [
                'title' => 'Technický crawl a interní odkazy',
                'items' => [
                    ['Prolinkovat Dárkové balíčky z menu a úvodní stránky', $must,
                        'Dnes na stránku nevede odkaz z menu ani z žádné procházené stránky. Totéž platí pro obě dárkové krabice.',
                        'Crawl 2. 10.: /darkove-balicky, /p/darkova-krabice-velka-30-30-cm a /p/darkova-krabicka mají 0 interních odkazů. Na úvodce slovo „dárkov“ není ani ve vykresleném DOM.'],
                    ['Opravit rozbitý odkaz na pepř v popisu celé skořice', $must,
                        'Odkaz je zapsaný bez https:// a vede na neexistující adresu.',
                        '/p/skorice-cela-cejlonska: <a href="svetcejlonu.cz/p/pepr-cely"> → https://www.svetcejlonu.cz/p/pepr-cely'],
                    ['Odstranit odkazy na nápovědu Upgates z úvodní stránky', $must,
                        'Čtyři odkazy na nápovědu Upgates zůstaly z ukázkového obsahu.',
                        'upgates.cz/a/designer-modul, nahrani-videa-na-eshop, obrazky-a-fotogalerie, pridani-a-nastaveni-nove-aktuality a další; odkazují i ukázkové aktuality a Rádce.'],
                    ['Rozhodnout o adresách, které jsou jen v sitemapě', $should,
                        '1 099 adres ze sitemapy nemá na webu žádný odkaz: filtry, varianty, štítky a několik stránek.',
                        'Crawl 2. 10.: 913 filtrů, 104 variant, 68 štítků, 2 filtry výrobce a 11 stránek (dárkové balíčky a krabice, oznámení, /why-us, Rádce, výrobci).'],
                    ['Přizpůsobit bloky pod výpisem kategorie', $should,
                        'Pět kategorií má pod výpisem stejné bloky. Odkazovat z nich na související návody a štítky.',
                        null],
                    ['Zopakovat crawl po větších změnách', $should,
                        'Po úklidu filtrů, po importu a po změně šablony.',
                        'Stejný postup jako 2. 10.: od úvodky po odkazech s robots.txt + všechny URL ze sitemapy.'],
                ],
            ],
            [
                'title' => 'Strukturovaná data',
                'items' => [
                    ['Opravit název webu a firmy ve strukturovaných datech', $must,
                        'Dnes se web i firma jmenují „Marek Bezdíček“ a cenová hladina je „$$$$$$“.',
                        '<head itemscope WebSite> itemprop name; patička LocalBusiness name, priceRange.'],
                    ['Opravit zápis recenzí', $must,
                        'Neplatný formát data a hodnocení recenze zapsané jako souhrnné.',
                        'datePublished "2026-08-01CEST14:52" → ISO 8601; uvnitř Review reviewRating (Rating), ne AggregateRating.'],
                    ['Doplnit profily, značku, dopravu a vrácení', $should,
                        'Odkazy na Facebook, Instagram, Heureku a Firmy.cz, značka u produktů, pravidla dopravy a vrácení.',
                        'sameAs, brand, hasMerchantReturnPolicy a hasShippingService na úrovni Organization.'],
                    ['Opravit og:type na úvodní stránce', $nice,
                        'Hodnota „web“ neexistuje, správně je „website“.',
                        null],
                ],
            ],
            [
                'title' => 'Konkurence',
                'items' => [
                    ['Projít s klientem rozdíly proti konkurenci', $should,
                        'Co mají konkurenční e-shopy a Svět Cejlonu ne. Seznam je v auditu, kapitola Konkurence.',
                        null],
                ],
            ],
            [
                'title' => 'Správa e-shopu',
                'items' => [
                    ['Sepsat pravidla pro přidávání produktů', $must,
                        'Aby další import nepřidal do sitemapy nové filtry z textových parametrů.',
                        null],
                    ['Domluvit postup pro vyprodané a vyřazené produkty', $should,
                        'Kdy stránku nechat, kdy přesměrovat na nástupce a kdy vrátit 404 nebo 410.',
                        null],
                    ['Zařadit technickou kontrolu do měsíčního přehledu', $should,
                        'Robots.txt, počet adres v sitemapě, noindex a canonical na vzorku stránek, stavové kódy a chyby v Search Console.',
                        null],
                    ['Opravit označení provozovatele v obchodních podmínkách', $nice,
                        'Podmínky píšou „obchodní společnost“, podle ARES jde o fyzickou osobu podnikatele.',
                        'IČO 06451420, právní forma 101. V textu je i překlep „živnosténském“.'],
                ],
            ],
            [
                'title' => 'Nákupní cesta',
                'items' => [
                    ['Po zprovoznění nákupních událostí zjistit, kde lidé odcházejí', $should,
                        'Teprve podle dat měnit pořadí prvků na detailu produktu a v košíku.',
                        'Tlačítko do košíku je na mobilu (390×844) na y≈1430 px, pod prvním zobrazením.'],
                    ['Ověřit počítadlo pro tamilskou školu v košíku', $nice,
                        'Při kontrole ukazovalo 0 Kč z cíle 10 000 Kč.',
                        null],
                ],
            ],
        ];
    }
};
