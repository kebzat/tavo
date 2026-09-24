<?php

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Checklist;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Checklist pro Svět Cejlonu: úkoly ze SEO a GEO auditu e-shopu
 * svetcejlonu.cz z 24. 9. 2026, přepsané pro klienta. Audit sám je
 * technický podklad pro nás, tohle je seznam, podle kterého se dá pracovat.
 *
 * Nevychází ze šablony. Univerzální šablona hlídá spuštění nového webu,
 * tady jde o úklid a rozvoj e-shopu, který už běží na Upgates.
 *
 * Proč migrace a ne seeder, viz 2026_08_06_120000_add_atriamo_checklist.php.
 * Stejně jako tam: založí se, jen když klient ještě neexistuje. Další úpravy
 * patří do administrace nástrojů, ne sem.
 */
return new class extends Migration
{
    private const CLIENT_SLUG = 'svet-cejlonu';

    // Pevný token, ať je odkaz pro klienta stejný lokálně i na ostrém webu
    // a dá se poslat hned po nasazení bez lovení v administraci.
    private const PUBLIC_TOKEN = 'sFDKpEjdYRVJrgU4fP73RRgAj3fRdenlkG8vLOcH';

    public function up(): void
    {
        if (Client::where('slug', self::CLIENT_SLUG)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $client = Client::create([
                'name' => 'Svět Cejlonu',
                'slug' => self::CLIENT_SLUG,
                'website_url' => 'https://www.svetcejlonu.cz',
                'note' => 'E-shop na Upgates: čaj, koření a ájurvéda ze Srí Lanky. Zakladatel Marek Bezdíček. '
                    .'SEO a GEO audit 24. 9. 2026 (navazuje na dílčí audit z 22. 9.). '
                    .'Odhad fáze 1 a 2: 20 400–25 600 Kč bez DPH.',
            ]);

            $checklist = Checklist::create([
                'client_id' => $client->getKey(),
                'is_template' => false,
                'public_token' => self::PUBLIC_TOKEN,
                'is_public' => true,
                'name' => 'SEO a GEO checklist pro svetcejlonu.cz',
                'intro' => 'Úkoly ze SEO a GEO auditu z 24. 9. 2026 v jednom seznamu. GEO znamená viditelnost '
                    .'v AI asistentech jako ChatGPT, Perplexity nebo Gemini. Úkoly jsou seřazené podle toho, '
                    .'co nejvíc pomůže za nejmíň práce. Pod odkazem „proč“ najdete, co přesně udělat a kde. '
                    .'Hotové si odškrtněte, uvidíme to i my.',
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
                        'description' => $section['description'],
                        'order_column' => $sectionOrder + 1,
                    ]);

                    foreach ($section['items'] as $itemOrder => $item) {
                        $newSection->items()->create([
                            'checklist_id' => $checklist->getKey(),
                            'title' => $item[0],
                            'priority' => $item[1],
                            'description' => $item[2],
                            'status' => $item[3] ?? ChecklistItemStatus::Todo,
                            'order_column' => $itemOrder + 1,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client) {
            return;
        }

        // Vazba checklistu na klienta je nullOnDelete, viz migrace Atriamo.
        $client->checklists()->each(fn (Checklist $checklist) => $checklist->delete());
        $client->delete();
    }

    /**
     * Kategorie → sekce → položky. Položka je [název, priorita, vysvětlivka, stav?].
     * Pořadí v poli je pořadí na stránce.
     */
    private static function structure(): array
    {
        $must = ChecklistPriority::Must;
        $should = ChecklistPriority::Should;
        $nice = ChecklistPriority::Nice;

        return [
            [
                'title' => 'Tento týden: úklid a měření',
                'slug' => 'tento-tyden',
                'description' => 'Zapnout nástroje, které ukážou, co Google vidí, a uklidit tisíce zbytečných adres. Celkem asi 3 hodiny práce.',
                'sections' => [
                    [
                        'title' => 'Nástroje pro měření',
                        'description' => 'Dnes se o chybách webu nikdo nedozví, protože chybí Google Search Console. Všechny tři nástroje jsou zdarma.',
                        'items' => [
                            ['Založit Google Search Console a odeslat do ní sitemapu', $must,
                                'Search Console ukáže, které stránky Google zná, na jaké dotazy se web zobrazuje a jaké chyby hlásí. '
                                .'Ověření jde přes záznam TXT u registrátora domény, takže potřebujeme váš přístup nebo 10 minut společně. '
                                .'Pak odešleme https://www.svetcejlonu.cz/sitemap.xml.'],
                            ['Přidat web do Bing Webmaster Tools', $must,
                                'Uděláme my, po založení Search Console jedním kliknutím. Z Bingu čerpá ChatGPT Search i Copilot, '
                                .'takže pro AI je stejně důležitý jako Google.'],
                            ['Založit Seznam Webmaster a odeslat sitemapu', $must,
                                'Seznam v Česku používá hlavně starší a movitější publikum, tedy lidi, kteří kupují sypaný čaj a ájurvédu. '
                                .'Ověřovací kód vložíme do šablony.'],
                            ['Propojit Google Analytics se Search Console a měřit návštěvy z AI', $should,
                                'Uděláme my. GA4 na webu už běží. Po propojení uvidíte dotazy z Googlu přímo v Analytics. '
                                .'Návštěvy z ChatGPT nebo Perplexity dnes padají do obecné kategorie „Referral“, vlastní skupina je oddělí.'],
                        ],
                    ],
                    [
                        'title' => 'Vymazat zbytečné adresy ze sitemapy',
                        'description' => 'E-shop dnes posílá Googlu 1 974 adres. Smysl ve vyhledávání jich má kolem 80. Zbytek jsou filtry a varianty produktů, na kterých robot zbytečně tráví čas a nové produkty se kvůli tomu indexují pomaleji.',
                        'items' => [
                            ['Filtrační stránky označit jako „neindexovat“', $must,
                                'V administraci: Nastavení → Produkty → Filtry a řazení → označit vše → hromadná akce „Označit jako neindexovat“. '
                                .'Zmizí tím i ze sitemapy. Jde o 1 786 adres jako /caje/p-tip/s-citronem-zvyrazni-svezest-nalevu, '
                                .'které vznikly po hromadném importu parametrů produktů.'],
                            ['Vyřadit varianty produktů ze sitemapy', $must,
                                'V administraci: Nastavení → Rozšířené → SEO → nastavení sitemap → vyloučit varianty. '
                                .'Jde o 104 adres typu /p/lotovovy-kvet/203. Bing už takovou variantu ukazuje místo hlavního produktu.'],
                            ['Pomocné stránky /oznameni-* a /why-us nastavit jako „neindexovat“', $must,
                                'Stránky /oznameni-dovolena, /oznameni-kosik a /oznameni-lista jsou jen zdroj textů pro vyskakovací okno a lištu. '
                                .'Nemazat, skript je potřebuje, jen v jejich SEO záložce zapnout „neindexovat“. '
                                .'/why-us je prázdná stránka „Naše výhody“: buď ji naplnit, nebo taky neindexovat.'],
                            ['Vyřadit textové parametry z filtrů kategorií', $should,
                                'Tip, Složení, Použití, Upozornění, Skvělé kombinace a Doporučujeme. Zákazník podle nich nefiltruje '
                                .'a z každé hodnoty vzniká další stránka. V administraci: Kategorie → Seznam kategorií → kategorie → Filtry.'],
                            ['Nechat v indexu jen pár štítků s vlastním textem', $should,
                                'Uděláme my. Kandidáti jsou /caje/t-cele-listy, „bez kofeinu“ a „cejlonská skořice Alba“. '
                                .'Dostanou vlastní titulek, popisek a odstavec textu. Kdo text nedostane, zůstane „neindexovat“.'],
                        ],
                    ],
                    [
                        'title' => 'Smazat ukázkový obsah Upgates',
                        'description' => 'Při zakládání e-shopu vložil Upgates ukázková data a část z nich na webu zůstala. Seznam už má ve výsledcích článek o hodinkách Citizen s nesmyslným textem.',
                        'items' => [
                            ['Smazat 4 ukázkové aktuality', $must,
                                'V administraci: Obsah → Aktuality. „Ke každému produktu dárek“, „Rozšířili jsme nabídku látek“, '
                                .'„Objednávejte nyní i přes Zásilkovnu“ a „Připravujeme novou kolekci!“. '
                                .'Text je generovaný nesmysl a Google kvůli němu hodnotí hůř celý web.'],
                            ['Smazat návod na brož z Rádce', $must,
                                'V administraci: Obsah → Rádce → „Ozdobte se vlastnoručně dělanou broží!“. '
                                .'Rádce pak naplníme skutečnými návody, viz poslední část checklistu.'],
                            ['Smazat výrobce „Upgates“', $must,
                                'V administraci: Produkty → Výrobci. Stránka /m/upgates je dnes v sitemapě.'],
                            ['Odstranit blok aktualit z úvodní stránky', $must,
                                'Uděláme my v šabloně. Blok je dnes schovaný jen pro oko. Roboti a AI ho čtou dál, '
                                .'včetně věty o tom, že se aktuality dají upravit v modulu Designer.'],
                            ['Požádat Seznam o odstranění smazaných adres', $should,
                                'Uděláme my v Seznam Webmasteru, ať stránka o hodinkách zmizí z výsledků co nejdřív.'],
                        ],
                    ],
                    [
                        'title' => 'AI roboti a podpora Upgates',
                        'description' => 'Server Upgates vrací robotům OpenAI a Anthropic chybu. ChatGPT a Claude se tak o e-shopu nemají odkud dozvědět.',
                        'items' => [
                            ['Napsat podpoře Upgates kvůli blokaci GPTBot a ClaudeBot', $must,
                                'Tohle je na vás, text e-mailu vám připravíme. Oba roboti dostávají chybu 503 dřív, než přečtou robots.txt. '
                                .'Ostatních 12 robotů, které jsme zkoušeli, projde. Ve stejném e-mailu se zeptáme, '
                                .'jestli jde u variant a filtrů nastavit kanonickou adresu na hlavní produkt a kategorii.'],
                            ['Povolit AI roboty v robots.txt', $should,
                                'Uděláme my v Nastavení → Rozšířené → SEO. Dnes je robots.txt neblokuje, jasné pravidlo ale pomůže, '
                                .'až Upgates blokaci zruší.'],
                            ['Poslat podpoře Upgates seznam pomalých stránek', $should,
                                'Uděláme my. Většina stránek odpoví do 2 sekund, některé ale trvají přes 8 s a /p/cerny-caj 22,9 s. '
                                .'Google pomalé weby prochází méně často.'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Do měsíce: texty, šablona a firma',
                'slug' => 'do-mesice',
                'description' => 'Jak e-shop vypadá ve výsledcích hledání a podle čeho ho Google a AI poznají jako firmu. Asi 2 dny práce.',
                'sections' => [
                    [
                        'title' => 'Titulky a popisky ve výsledcích hledání',
                        'description' => 'Titulek a popisek je první, co člověk v Googlu uvidí. Úvodní stránka má dnes titulek jen „Svět Cejlonu“ a popisky kategorií jsou kopie titulku, třeba „ČAJE :: Svět Cejlonu“.',
                        'items' => [
                            ['Přepsat titulek a popisek úvodní stránky', $must,
                                'Návrh titulku: „Cejlonský čaj, koření a ájurvéda ze Srí Lanky | Svět Cejlonu“. '
                                .'Návrh popisku je v auditu. Dnešní popisek zní „Vítejte na e-shopu Svět Cejlonu! :: Svět Cejlonu“.'],
                            ['Vyplnit titulky a popisky kategorií a obsahových stránek', $must,
                                'Hotové návrhy máme pro 6 kategorií a 4 stránky (O nás, škola, velkoobchod, vše o nákupu). '
                                .'Stačí je vložit do SEO záložky každé stránky.'],
                            ['Změnit šablonu popisku, aby neopakovala titulek', $should,
                                'V administraci: Nastavení → Rozšířené → SEO. Dnes z ní vzniká třeba '
                                .'„Podpora tamilské školy na Srí Lance. Podpora tamilské školy na Srí Lance :: Svět Cejlonu“.'],
                            ['Doplnit popisek u 6 produktů, které ho nemají', $must,
                                'Chilli, Dárková krabice velká, Dárková krabice malá, Přenosná čajová sada, Samahan a Skleněný louhovač. '
                                .'Popisek se bere z krátkého popisu produktu.'],
                            ['Přepsat první větu krátkého popisu u produktů', $should,
                                'Z ní Google skládá popisek ve výsledcích. U 41 produktů je moc dlouhá a Google ji ořízne, 16 jich začíná emoji. '
                                .'Prvních asi 155 znaků má dávat smysl samo o sobě.'],
                            ['Názvy produktů psát normálně, ne velkými písmeny', $should,
                                '35 z 59 produktů má název verzálkami, třeba „KURKUMA - MLETÁ“. Ve výsledcích hledání to působí jako křik. '
                                .'Pokud design verzálky chce, nastavíme je v šabloně a v administraci zůstane normální text.'],
                        ],
                    ],
                    [
                        'title' => 'Opravy v popisech produktů',
                        'description' => null,
                        'items' => [
                            ['Opravit vícenásobné hlavní nadpisy v popisech', $should,
                                'Stránka má mít jeden hlavní nadpis (H1). Ayush pleťový krém jich má pět, včetně řádků z podtržítek. '
                                .'Totéž u Kardamonu, Navratny a na stránce O nás. V editoru přepnout na „Nadpis 2“, podtržítka smazat.'],
                            ['Opravit překlep v názvu „RARANAVARA“', $should,
                                'Správně Ranavara.'],
                            ['Opravit nesedící adresy produktů', $nice,
                                'Uděláme my. /p/lotovovy-kvet místo lotosový, /p/cerny-caj-earl-gray místo Grey a /p/mangostan-zeleny-caj, '
                                .'přitom jde o černý čaj. Adresu jde měnit jen s přesměrováním ze staré, jinak se ztratí, co o ní Google ví.'],
                        ],
                    ],
                    [
                        'title' => 'Úpravy šablony',
                        'description' => 'Tohle uděláme my v kódu šablony. Od vás potřebujeme jen potvrdit údaje o firmě.',
                        'items' => [
                            ['Doplnit údaje o firmě pro Google a AI', $must,
                                'Strukturovaná data s názvem, IČO, kontakty a odkazy na Instagram, Facebook, Heureku a Firmy.cz. '
                                .'AI podle nich pozná, že jde o jednu firmu. Potřebujeme vědět, jestli v nich chcete uvést adresu, '
                                .'když ji na webu schováváte.'],
                            ['Opravit chybné údaje o firmě ve strukturovaných datech', $must,
                                'Dnes se v nich firma jmenuje „Marek Bezdíček“ a cenová hladina je „$$$$$$“.'],
                            ['Opravit data recenzí, aby Google mohl ukázat hvězdičky', $must,
                                'Hodnocení v datech nepatří k žádné recenzi a datum má neplatný formát. '
                                .'Ibišek má 25 recenzí s průměrem 5,0 a ve výsledcích hledání to není vidět.'],
                            ['Drobné opravy: náhled při sdílení, popis loga, drobečková navigace', $should,
                                'Náhled odkazu na Facebooku má neplatný typ a chybí mu popis. Logo má jako popis titulek stránky. '
                                .'Na detailu produktu jsou drobečky dvakrát a nadpisy na kartách produktů přeskakují úrovně.'],
                            ['Doplnit popisky (alt) k obrázkům', $should,
                                'Hotovo od 22. 9. Zbývá jeden technický obrázek šablony, ten opravíme spolu s ostatním.',
                                ChecklistItemStatus::Done],
                        ],
                    ],
                    [
                        'title' => 'Firma a důvěryhodnost',
                        'description' => 'Google i AI si ověřují, kdo za e-shopem stojí. U doplňků stravy a ájurvédy přísněji než u jiného zboží.',
                        'items' => [
                            ['Založit stránku Kontakt', $must,
                                'Adresa /kontakt dnes vrací chybu 404. Patří tam firma, IČO, e-mail, telefon a fakturační adresa. '
                                .'Dnes je to jen v obchodních podmínkách a v okně v patičce.'],
                            ['Sloučit dva záznamy na Firmy.cz', $should,
                                'Tohle je na vás. Existuje „Svetcejlonu.cz“ i „Svět Cejlonu“, oba s IČO 06451420. '
                                .'Jeden zrušit, druhý doplnit o popis, fotky a odkaz na web.'],
                            ['Založit Google Business Profile', $should,
                                'Tohle je na vás, rádi pomůžeme. Jako firma bez provozovny s působností v celé ČR. '
                                .'Recenze odtamtud vidí Google i Gemini.'],
                            ['Projít zdravotní tvrzení u ájurvédy a doplňků', $should,
                                'Názvy jako „Ájurvédský balzám (Bolest hlavy)“ nebo mast na „plísňové potíže“ jsou u doplňků a kosmetiky '
                                .'regulované a AI takové zdroje necituje. Bezpečnější je popsat tradiční použití na Srí Lance a diagnózu '
                                .'z názvu vynechat. Ideálně s odborníkem na regulaci, tohle není právní posudek.'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Do čtvrt roku: obsah a AI',
                'slug' => 'obsah-a-ai',
                'description' => 'Texty, které Google a AI můžou citovat. Na obecné dotazy dnes AI doporučuje konkurenci, tady se to dá změnit.',
                'sections' => [
                    [
                        'title' => 'Kategorie a produkty',
                        'description' => 'Kategorie nemají vlastní text, jen karty produktů. Google ani AI tak nemají z čeho poznat, že /caje je stránka o cejlonském čaji.',
                        'items' => [
                            ['Napsat úvodní text ke každé kategorii', $must,
                                'Stačí 150 až 300 slov: co je cejlonský čaj nebo koření, z jaké oblasti přesně a čím se liší od běžného zboží. '
                                .'Vy dodáte fakta, text připravíme.'],
                            ['Přidat ke kategoriím 3 až 5 otázek a odpovědí', $should,
                                'AI odpovídá na otázky, takže obsah ve tvaru otázka a odpověď cituje nejsnáz.'],
                            ['Doplnit fakta a otázky u 15 nejprodávanějších produktů', $should,
                                'První věta má říct, co produkt je: „Ibišek je sušený květ ze Srí Lanky, ze kterého vzniká červený kyselkavý čaj bez kofeinu.“ '
                                .'Na konec popisu pár otázek jako „Obsahuje kofein?“ nebo „Kolik gramů na šálek?“.'],
                            ['Vypsat u produktů tabulku faktů', $should,
                                'Uděláme my v šabloně. Původ, oblast, třída, kofein, teplota a doba louhování. '
                                .'Parametry už v administraci máte, jen se dnes vypisují jako chuťový profil.'],
                            ['Vyplnit SEO titulky u 15 až 20 hlavních produktů', $should,
                                'Vzor: „Ibiškový čaj – sušený květ ibišku ze Srí Lanky | Svět Cejlonu“. Dnes je titulek jen název produktu.'],
                            ['Nahrát optimalizované fotky s popisnými názvy souborů', $nice,
                                'Uděláme my, fotky jsou připravené. Detail produktu bude o 1,5 MB lehčí a místo 9.jpg se soubor bude jmenovat '
                                .'třeba cejlonska-skorice-cela-alba.jpg, což pomáhá v Google Obrázcích.'],
                        ],
                    ],
                    [
                        'title' => 'Rádce: články, které AI cituje',
                        'description' => 'Na dotaz „kde koupit pravou cejlonskou skořici“ dnes AI doporučí cejlonskekoreni.cz. Nemají lepší zboží, jen napsali, kolik kumarinu jejich skořice obsahuje.',
                        'items' => [
                            ['Článek: Cejlonská skořice vs. kasie, jak poznat pravou skořici', $must,
                                'Obsah kumarinu, třídy Alba, C5 a M5, odkazy na skořici celou i mletou. Tohle AI dnes cituje od konkurence.'],
                            ['Článek: Cejlonský čaj podle oblastí', $should,
                                'Nuwara Eliya, Uva, Dimbula, Kandy a Ruhuna: nadmořská výška a chuť. Na dotaz „cejlonský čaj“ dnes vyhrává Wikipedie.'],
                            ['Článek: Jak louhovat sypaný čaj', $should,
                                'Teplota, čas a gramy v tabulce pro každý druh. Pro AI ideální formát.'],
                            ['Další články podle seznamu z auditu', $nice,
                                'Zkratky na čaji (Pekoe, BOP, GS-1), proč modrý čaj mění barvu, srílanské kari doma, moringa a ashwagandha '
                                .'se zdroji, co přivézt ze Srí Lanky, návštěva u pěstitele. Pod článek patří podpis, třeba „Text: Marek Bezdíček, zakladatel“.'],
                            ['Přepsat stránku O nás jako zdroj faktů o firmě', $should,
                                'AI si fakta o firmě bere odtud a z katalogů. Jeden odstavec bez metafor: kdo a od kdy, co prodáváte, '
                                .'z jakých oblastí a farem, jak balíte, a čísla, která jdou ověřit.'],
                        ],
                    ],
                    [
                        'title' => 'Mimo web',
                        'description' => 'Google i AI víc věří tomu, co o e-shopu píšou ostatní, než tomu, co píše sám o sobě.',
                        'items' => [
                            ['Zapojit feed do Google Merchant Center a Zboží.cz', $should,
                                'Feed je v Upgates připravený. Produkty se pak zdarma ukážou v Google Nákupech a v AI odpovědích Googlu.'],
                            ['Nahrát videa ze Srí Lanky na YouTube', $nice,
                                'Videa už se natáčejí do reklam. Google i AI videa z YouTube citují.'],
                            ['Rozeslat příběh tamilské školy a oslovit blogy', $nice,
                                'Tisková zpráva o škole, cestovatelské blogy o Srí Lance, food blogy s recepty na kari. '
                                .'Odkazy z nich zvyšují důvěru Googlu v celý web.'],
                        ],
                    ],
                    [
                        'title' => 'Kontrola výsledků',
                        'description' => null,
                        'items' => [
                            ['Po 4 až 6 týdnech zkontrolovat, co se změnilo', $must,
                                'Uděláme my. V Search Console projdeme, kolik filtrů a variant Google vyřadil, v Analytics návštěvy z AI '
                                .'a zopakujeme testovací dotazy v AI vyhledávání. Pošleme krátký report.'],
                        ],
                    ],
                ],
            ],
        ];
    }
};
