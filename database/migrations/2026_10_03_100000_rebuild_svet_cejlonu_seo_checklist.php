<?php

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Checklist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Checklist Světa Cejlonu přestavěný podle plánu v auditu (kapitola
 * „Plán a varianty spolupráce“): měsíc po měsíci od října do března,
 * v každém měsíci úkoly varianty A, úkoly navíc ve variantě B a co
 * potřebujeme od klienta.
 *
 * Nahrazuje obsah checklistu z migrací 2026_10_02_110000 a 150000.
 * Přestaví se, jen když v něm ještě nikdo nic neodškrtl.
 */
return new class extends Migration
{
    private const CHECKLIST_SLUG = 'svet-cejlonu-seo-2026';

    public function up(): void
    {
        DB::transaction(fn () => $this->rebuildChecklist());
    }

    public function down(): void
    {
        // Původní obsah se nevrací, checklist se dál upravuje v administraci.
    }

    private function rebuildChecklist(): void
    {
        $checklist = Checklist::where('slug', self::CHECKLIST_SLUG)->first();

        if (! $checklist || $checklist->items()->where('status', '!=', ChecklistItemStatus::Todo)->exists()) {
            return;
        }

        $checklist->categories()->delete();

        $checklist->update([
            'name' => 'SEO plán pro svetcejlonu.cz na říjen až březen',
            'intro' => 'Úkoly z plánu v auditu, měsíc po měsíci. V každém měsíci jsou nahoře úkoly varianty A, '
                .'pod nimi úkoly navíc ve variantě B a nakonec to, co potřebujeme od vás. '
                .'Hotové úkoly průběžně odškrtáváme, takže tu uvidíte, jak práce postupuje.',
        ]);

        foreach (self::structure() as $categoryOrder => $category) {
            $newCategory = $checklist->categories()->create([
                'title' => $category['title'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'order_column' => $categoryOrder + 1,
            ]);

            foreach ($category['sections'] as $sectionOrder => $section) {
                $newSection = $newCategory->sections()->create([
                    'title' => $section['title'],
                    'description' => $section['description'] ?? null,
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
    }

    /**
     * Měsíc → sekce → položky [název, priorita, popis pro klienta, interní poznámka?].
     */
    private static function structure(): array
    {
        $must = ChecklistPriority::Must;
        $should = ChecklistPriority::Should;
        $nice = ChecklistPriority::Nice;

        $report = fn (string $month): array => ['Měsíční přehled za '.$month, $must,
            'Co je hotové, co se změnilo v pozicích, indexaci a návštěvnosti a co je na řadě.',
            'Search Console (výkon, stránky), sledování pozic, počet URL v sitemapě, robots.txt, noindex a canonical na vzorku stránek.'];

        return [
            [
                'title' => 'Říjen: měření a úklid',
                'slug' => 'rijen',
                'description' => 'Zapnout měření a uklidit stránky, které ve vyhledávání nemají co dělat.',
                'sections' => [
                    [
                        'title' => 'Měření a výchozí stav',
                        'description' => 'Bez výchozího stavu nepůjde v březnu ukázat, co se zlepšilo.',
                        'items' => [
                            ['Založit Google Search Console a odeslat sitemapu', $must,
                                'Ukáže, které stránky Google zná, na jaké dotazy se web zobrazuje a jaké chyby hlásí.',
                                'Doménová služba přes TXT záznam v DNS. Sitemap: https://www.svetcejlonu.cz/sitemap.xml'],
                            ['Založit Seznam Webmaster a odeslat sitemapu', $must,
                                'Seznam má vlastní vyhledávání a vlastní index.',
                                'Ověření meta tagem v hlavičce šablony.'],
                            ['Přidat web do Bing Webmaster Tools', $nice,
                                'Jedním kliknutím ze Search Console. Z Bingu čerpá i Copilot.',
                                null],
                            ['Nastavit sledování pozic hlavních dotazů', $must,
                                'Podle něj budeme každý měsíc vidět, jestli se web v Googlu posouvá.',
                                'Dotazy z mapy klíčových slov v auditu (cejlonská skořice, cejlonský čaj, sypaný čaj, modrý čaj, kari listy, kurkuma mletá, dárkový balíček čaje…). Marketing Miner nebo Collabim.'],
                            ['Uložit výchozí stav', $must,
                                'Pozice hlavních dotazů, počet stránek v indexu, návštěvnost a objednávky z vyhledávání.',
                                'Export GSC a GA4 do tabulky. GSC má data až po ověření, výchozí stav tedy doplnit, jakmile naběhnou.'],
                        ],
                    ],
                    [
                        'title' => 'Úklid indexu a sitemapy',
                        'description' => 'Z 1 979 adres v sitemapě je skutečných produktů a stránek 71.',
                        'items' => [
                            ['Vyřadit textové parametry z filtrů', $must,
                                'Použití, Tip, Příprava, Tradiční využití, Zajímavosti, Chuťový profil a podobné. Podle nich nikdo nefiltruje a z každé věty vzniká stránka.',
                                'Upgates: nastavení filtrů v kategoriích (cestu ověřit v administraci).'],
                            ['Zrušit odkazy z hodnot parametrů na filtry', $must,
                                'Detail produktu dnes odkazuje z hodnot parametrů na stovky stránek filtrů.',
                                'Naše šablona detailu produktu: hodnoty vypsat jako text. Crawl 2. 10.: 808 odkazů z 52 produktů.'],
                            ['Nepotřebné filtry a štítky vyřadit z indexu a sitemapy', $must,
                                'Ve vyhledávání necháme jen štítky, které dostanou vlastní text.',
                                'Noindex hromadně, pak kontrola sitemapy.'],
                            ['Varianty vyřadit ze sitemapy a sjednotit canonical', $should,
                                'Varianty lišící se jen gramáží nemají soutěžit s hlavním produktem.',
                                'Pokud Upgates neumí canonical na hlavní produkt, napsat podpoře. Ověřit, že feedy dál vedou na správnou variantu.'],
                            ['Pomocné stránky vyřadit z indexu', $must,
                                'Oznámení, prázdná stránka Naše výhody, porovnání produktů a přihlášení k newsletteru.',
                                '/oznameni-dovolena, /oznameni-lista, /oznameni-kosik (nemazat, šablona z nich bere texty), /why-us, /compare, /newsletter.'],
                        ],
                    ],
                    [
                        'title' => 'Rychlé opravy',
                        'description' => null,
                        'items' => [
                            ['Odstranit ukázkový obsah Upgates', $must,
                                'Ukázkové aktuality, návod v Rádci, výrobce Upgates a odkazy na nápovědu Upgates z úvodní stránky.',
                                'Smazané adresy mají vracet 404 nebo 410, nepřesměrovávat na úvodku.'],
                            ['Opravit rozbitý odkaz na pepř', $must,
                                'V popisu celé cejlonské skořice vede odkaz na neexistující adresu.',
                                '/p/skorice-cela-cejlonska: href="svetcejlonu.cz/p/pepr-cely" → https://www.svetcejlonu.cz/p/pepr-cely'],
                            ['Opravit větu o výběru čaje v ostatních kategoriích', $must,
                                'Věta „Nevíte, který čaj vybrat?“ je i u Koření, Doplňků stravy, Ájurvédy a Ostatních.',
                                null],
                            ['Předat seznam rizikových zdravotních tvrzení', $must,
                                'Seznam produktů a formulací k posouzení odborníkem.',
                                'U 15 produktů, hlavně parametr Použití. Seznam z auditu 2. 10.'],
                        ],
                    ],
                    [
                        'title' => 'Měsíční přehled',
                        'description' => null,
                        'items' => [$report('říjen')],
                    ],
                    [
                        'title' => 'Navíc ve variantě B',
                        'description' => null,
                        'items' => [
                            ['Zapojit produktové feedy do Google Merchant Center a Zboží.cz', $should,
                                'Produkty se pak ukážou i v nákupních výsledcích Googlu a Seznamu.',
                                'Obchod na Zboží.cz už existuje. Ve feedu nesmí být zdravotní tvrzení.'],
                        ],
                    ],
                    [
                        'title' => 'Od vás',
                        'description' => null,
                        'items' => [
                            ['Přístupy do administrace, Google Analytics a k ověření Search Console', $must,
                                'Bez nich nejde začít.',
                                null],
                            ['Posouzení zdravotních tvrzení odborníkem', $should,
                                'Seznam dostanete od nás, samotné posouzení není součástí spolupráce.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Listopad: titulky a šablona před Vánoci',
                'slug' => 'listopad',
                'description' => 'Jak e-shop vypadá ve výsledcích hledání a co z něj Google vyčte.',
                'sections' => [
                    [
                        'title' => 'Titulky a popisky',
                        'description' => 'Titulek a popisek je první, co člověk ve výsledcích uvidí.',
                        'items' => [
                            ['Titulek a popisek úvodní stránky', $must,
                                'Se sortimentem a původem, ne jen s názvem značky.',
                                null],
                            ['Titulky a popisky 6 kategorií', $must,
                                'Popisek nesmí opakovat titulek.',
                                null],
                            ['Dárkové balíčky: titulek, popisek a odkazy z menu a úvodní stránky', $must,
                                'Dnes na stránku nevede žádný odkaz. Před Vánoci je to nejdůležitější stránka.',
                                'Crawl 2. 10.: /darkove-balicky a obě dárkové krabice mají 0 interních odkazů.'],
                            ['Upravit šablonu popisku, aby neopakovala titulek', $should,
                                'Pro stránky, u kterých popisek nikdo nevyplní.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Strukturovaná data',
                        'description' => 'Údaje ve zdrojovém kódu, podle kterých Google pozná firmu, cenu a hodnocení.',
                        'items' => [
                            ['Opravit název webu a údaje o firmě', $must,
                                'Dnes se web i firma jmenují „Marek Bezdíček“ a cenová hladina je „$$$$$$“.',
                                '<head itemscope WebSite> itemprop name; patička LocalBusiness name a priceRange.'],
                            ['Opravit zápis recenzí', $must,
                                'Kvůli chybnému zápisu se hvězdičky ve výsledcích nejspíš nezobrazí.',
                                'datePublished "2026-08-01CEST14:52" → ISO 8601; v Review reviewRating (Rating), ne AggregateRating.'],
                            ['Doplnit odkazy na profily, dopravu a vrácení zboží', $should,
                                'Facebook, Instagram, Heureka a Firmy.cz a pravidla dopravy a vrácení.',
                                'sameAs, hasShippingService a hasMerchantReturnPolicy na úrovni Organization.'],
                            ['Ověřit úpravy v Rich Results Testu', $must,
                                'Úvodní stránka, kategorie a dva produkty bez chyb.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Měsíční přehled',
                        'description' => null,
                        'items' => [$report('listopad')],
                    ],
                    [
                        'title' => 'Navíc ve variantě B',
                        'description' => null,
                        'items' => [
                            ['Zmenšit fotky u hlavních produktů', $should,
                                'Detail produktu má dnes na mobilu přes 4 MB, většinou obrázky.',
                                'WebP, správné rozměry, hlavní obrázek bez lazy loadu. Začít nejprodávanějšími. Přeměřit v Lighthouse.'],
                        ],
                    ],
                    [
                        'title' => 'Od vás',
                        'description' => null,
                        'items' => [
                            ['Varianta B: poslat text prvního článku', $should,
                                'Stačí samotný text, na web ho v prosinci vložíme a graficky upravíme.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Prosinec: sezóna a pravidla',
                'slug' => 'prosinec',
                'description' => 'V sezóně nic velkého neměníme. Hlídáme, jestli úklid zabírá, a připravíme pravidla.',
                'sections' => [
                    [
                        'title' => 'Kontrola a pravidla',
                        'description' => null,
                        'items' => [
                            ['Zkontrolovat, jestli Google vyřazuje filtry a varianty', $must,
                                'V Search Console postupně ubývá stránek, které tam nepatří.',
                                'GSC → Stránky: vyloučeno noindexem, počty podle typu URL.'],
                            ['Zkontrolovat pozice a dárkové balíčky', $must,
                                'Jak se v sezóně ukazují dárkové balíčky a hlavní kategorie.',
                                null],
                            ['Sepsat pravidla pro přidávání produktů', $must,
                                'Aby další import nepřidal tisíce nových stránek filtrů a nevrátil se starý stav.',
                                'Pravidla z kapitoly Správa e-shopu po úklidu: parametry, názvy, krátký popis, žádná léčebná tvrzení, fotky, varianty.'],
                        ],
                    ],
                    [
                        'title' => 'Měsíční přehled',
                        'description' => null,
                        'items' => [$report('prosinec')],
                    ],
                    [
                        'title' => 'Navíc ve variantě B',
                        'description' => null,
                        'items' => [
                            ['Vložit první článek do Rádce', $should,
                                'Graficky upravený a propojený s produkty.',
                                'Téma podle dat z GSC, kandidáti: jak louhovat sypaný čaj, co je modrý čaj.'],
                        ],
                    ],
                    [
                        'title' => 'Od vás',
                        'description' => null,
                        'items' => [
                            ['Poslat fakta ke kategoriím Čaje a Koření', $must,
                                'Odkud čaje a koření pocházejí, jak se zpracovávají, jak vybírat. Text z nich připravíme v lednu.',
                                null],
                            ['Varianta B: poslat fakta k ostatním kategoriím', $should,
                                'Doplňky stravy, Ájurvéda, Ostatní a Dárkové balíčky.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Leden: kategorie',
                'slug' => 'leden',
                'description' => 'Vyhodnotit Vánoce a dát hlavním kategoriím obsah, podle kterého je Google pozná.',
                'sections' => [
                    [
                        'title' => 'Vyhodnocení a kategorie',
                        'description' => null,
                        'items' => [
                            ['Vyhodnotit Vánoce proti výchozímu stavu', $must,
                                'Sezónní růst nebudeme automaticky připisovat SEO.',
                                'Porovnat s výchozím stavem z října, případně s loňským prosincem v GA4.'],
                            ['Text a FAQ pro kategorii Čaje', $must,
                                'Vlastní text a otázky a odpovědi, které lidé řeší před nákupem.',
                                null],
                            ['Text a FAQ pro kategorii Koření', $must,
                                'Vlastní text a otázky a odpovědi, které lidé řeší před nákupem.',
                                null],
                            ['Propojit kategorie s produkty a dalším obsahem', $should,
                                'Bloky pod výpisem přizpůsobit kategorii.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Měsíční přehled',
                        'description' => null,
                        'items' => [$report('leden')],
                    ],
                    [
                        'title' => 'Navíc ve variantě B',
                        'description' => null,
                        'items' => [
                            ['Text a FAQ pro ostatní kategorie', $should,
                                'Doplňky stravy, Ájurvéda, Ostatní a Dárkové balíčky.',
                                'U doplňků a ájurvédy hlídat zdravotní tvrzení.'],
                        ],
                    ],
                    [
                        'title' => 'Od vás',
                        'description' => null,
                        'items' => [
                            ['Poslat text článku o cejlonské skořici', $must,
                                'Stačí samotný text, na web ho v únoru vložíme a graficky upravíme.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Únor: první článek',
                'slug' => 'unor',
                'description' => 'Článek, který odpovídá na otázky lidí, a dokončení úklidu indexu.',
                'sections' => [
                    [
                        'title' => 'Článek a úklid',
                        'description' => null,
                        'items' => [
                            ['Vložit do Rádce článek o cejlonské skořici', $must,
                                'Graficky upravený, s titulkem a popiskem pro vyhledávání.',
                                null],
                            ['Propojit článek s produkty a kategorií Koření', $must,
                                'Z článku na mletou a celou skořici, z produktů a kategorie zpět na článek.',
                                null],
                            ['Zakázat filtry v robots.txt, až zmizí z indexu', $should,
                                'Teprve potom, jinak by vyhledávače neviděly, že je mají vyřadit.',
                                'Kontrola v GSC → Stránky. Pak Disallow pro /*/p-* a /*/m-*, štítky s vlastním textem povolit.'],
                        ],
                    ],
                    [
                        'title' => 'Měsíční přehled',
                        'description' => null,
                        'items' => [$report('únor')],
                    ],
                    [
                        'title' => 'Navíc ve variantě B',
                        'description' => null,
                        'items' => [
                            ['Založit podkategorie Černé čaje a Zelené čaje', $should,
                                'Lidé hledají černý a zelený sypaný čaj, e-shop na to má jen jednotlivé produkty.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Od vás',
                        'description' => null,
                        'items' => [
                            ['Poslat text druhého článku', $should,
                                'Stačí samotný text. Pokud bude lepší FAQ k hlavním produktům, domluvíme se podle dat.',
                                null],
                            ['Varianta B: poslat text dalšího článku', $should,
                                'Stačí samotný text, na web ho v březnu vložíme a graficky upravíme.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Březen: vyhodnocení',
                'slug' => 'brezen',
                'description' => 'Druhý článek, porovnání s říjnem a plán na další měsíce.',
                'sections' => [
                    [
                        'title' => 'Obsah a vyhodnocení',
                        'description' => null,
                        'items' => [
                            ['Vložit druhý článek nebo FAQ k hlavním produktům', $must,
                                'Podle toho, co ukážou data ze Search Console.',
                                null],
                            ['Vyhodnotit výsledky proti říjnu', $must,
                                'Pozice hlavních dotazů, indexace, návštěvnost a objednávky z vyhledávání.',
                                null],
                            ['Navrhnout plán na další měsíce', $must,
                                'Podle toho, co zabralo a co zbývá z auditu.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Navíc ve variantě B',
                        'description' => null,
                        'items' => [
                            ['Vložit další článek do Rádce', $should,
                                'Graficky upravený a propojený s produkty.',
                                null],
                        ],
                    ],
                ],
            ],
        ];
    }
};
