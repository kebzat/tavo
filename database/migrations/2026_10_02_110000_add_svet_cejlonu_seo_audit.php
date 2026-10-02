<?php

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Audit;
use App\Models\Checklist;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kompletní SEO audit Světa Cejlonu z 2. 10. 2026 a k němu checklist
 * rozdělený po měsících (říjen 2026 až březen 2027).
 *
 * Navazuje na audit z 24. 9., ten zůstává beze změny jako obchodní podklad.
 * Text auditu leží vedle v database/content/audits/, ať se dá číst.
 *
 * Audit ani checklist nejsou po založení veřejné. Tom je zkontroluje
 * a zveřejní v administraci nástrojů. Postupy v administraci Upgates jsou
 * v interní poznámce u položky, klient je na sdílené stránce nevidí.
 *
 * Založí se jen jednou. Další úpravy patří do administrace, ne sem.
 */
return new class extends Migration
{
    private const CLIENT_SLUG = 'svet-cejlonu';

    private const AUDIT_SLUG = 'svet-cejlonu-seo-2026';

    private const CHECKLIST_SLUG = 'svet-cejlonu-seo-2026';

    private const INTRO = 'Jak e-shop vidí Google, Seznam a AI asistenti, co ho brzdí a v jakém pořadí to opravit do března 2027.';

    private const HIGHLIGHTS = [
        ['value' => '1 979', 'label' => 'adres v sitemapě, skutečných produktů a stránek je 71'],
        ['value' => '1 721', 'label' => 'stránek filtrů vzniklých z parametrů produktů'],
        ['value' => '15', 'label' => 'produktů slibuje účinek na nemoc'],
        ['value' => '0 z 10', 'label' => 'otázek, u kterých AI vyhledávání citovalo e-shop'],
    ];

    public function up(): void
    {
        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client || Audit::where('slug', self::AUDIT_SLUG)->exists()) {
            return;
        }

        DB::transaction(function () use ($client): void {
            Audit::create([
                'client_id' => $client->getKey(),
                'title' => 'Kompletní SEO audit e-shopu svetcejlonu.cz',
                'slug' => self::AUDIT_SLUG,
                'audited_at' => '2026-10-02',
                'intro' => self::INTRO,
                'highlights' => self::HIGHLIGHTS,
                'body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-10-02.md')),
                'is_public' => false,
                'is_teaser' => false,
            ]);

            $checklist = Checklist::create([
                'client_id' => $client->getKey(),
                'is_template' => false,
                'is_public' => false,
                'slug' => self::CHECKLIST_SLUG,
                'name' => 'SEO plán pro svetcejlonu.cz na říjen až březen',
                'intro' => 'Úkoly z kompletního SEO auditu z 2. 10. 2026, rozdělené po měsících. '
                    .'Nahoře je to, co nejvíc brzdí, dole obsah a odkazy, které pracují dlouhodobě. '
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
                        'description' => $section['description'],
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
        });
    }

    public function down(): void
    {
        Audit::where('slug', self::AUDIT_SLUG)->delete();
        Checklist::where('slug', self::CHECKLIST_SLUG)->get()->each(fn (Checklist $checklist) => $checklist->delete());
    }

    /**
     * Kategorie (měsíc) → sekce (oblast) → položky.
     * Položka je [název, priorita, popis pro klienta, interní poznámka?].
     */
    private static function structure(): array
    {
        $must = ChecklistPriority::Must;
        $should = ChecklistPriority::Should;
        $nice = ChecklistPriority::Nice;

        return [
            [
                'title' => 'Říjen: měření a úklid indexu',
                'slug' => 'rijen',
                'description' => 'Zapnout nástroje, které ukážou, co vyhledávače vidí, a uklidit stránky, které tam nepatří.',
                'sections' => [
                    [
                        'title' => 'Nástroje pro měření',
                        'description' => 'Bez nich nejde zjistit výchozí stav ani ověřit, jestli úklid zabral.',
                        'items' => [
                            ['Založit Google Search Console a odeslat sitemapu', $must,
                                'Search Console ukáže, které stránky Google zná, na jaké dotazy se web ukazuje a jaké chyby hlásí.',
                                'Ověření doménové služby přes TXT záznam v DNS. Sitemap: https://www.svetcejlonu.cz/sitemap.xml'],
                            ['Přidat web do Bing Webmaster Tools', $must,
                                'Z Bingu čerpá i ChatGPT a Copilot. Bing navíc ukazuje, kdy web citovala AI.',
                                'Import webu ze Search Console.'],
                            ['Založit Seznam Webmaster a odeslat sitemapu', $must,
                                'Seznam má v Česku vlastní vyhledávání a vlastní index.',
                                'Ověřovací meta tag vložit do hlavičky šablony.'],
                            ['Propojit Search Console s Google Analytics', $should,
                                'Dotazy z Googlu pak budou vidět i v Analytics.',
                                null],
                            ['Oddělit v Analytics návštěvy z AI nástrojů', $should,
                                'Návštěvy z ChatGPT, Perplexity nebo Copilotu dnes padají mezi běžné odkazy.',
                                'Vlastní skupina kanálů v GA4, zdroj podle regexu: chatgpt|openai|perplexity|copilot|gemini|claude'],
                            ['Uložit výchozí stav', $must,
                                'Počet stránek v indexu, hlavní dotazy a stránky, organická návštěvnost a objednávky za 12 měsíců. V březnu s nimi porovnáme výsledky.',
                                'Export z GSC (Výkon, Stránky) a GA4 do sdílené tabulky klienta.'],
                        ],
                    ],
                    [
                        'title' => 'Úklid indexu a sitemapy',
                        'description' => 'Z 1 979 adres v sitemapě je skutečných produktů a stránek 71.',
                        'items' => [
                            ['Vyřadit textové parametry z filtrů', $must,
                                'Použití, Tip, Příprava, Tradiční využití, Zajímavosti, Chuťový profil, Složení, Upozornění, Skvělé kombinace a Doporučujeme. Podle nich nikdo nefiltruje a z každé věty vzniká stránka.',
                                'Kategorie → seznam kategorií → kategorie → Filtry (cesta podle zářijového auditu, ověřit v administraci).'],
                            ['Filtry a štítky označit jako „neindexovat“', $must,
                                'Zmizí tím i ze sitemapy. Jde o 1 721 filtrů podle parametru, 2 podle výrobce a 68 štítků.',
                                'Nastavení → Produkty → Filtry a řazení → označit vše → hromadně „neindexovat“ (cesta podle zářijového auditu, ověřit).'],
                            ['Zrušit odkazy z hodnot parametrů na filtry', $must,
                                'Detail produktu dnes odkazuje z každé hodnoty parametru na stránku filtru. Z 52 produktů tak vede 808 různých odkazů na filtry.',
                                'Úprava v naší šabloně detailu produktu: hodnoty parametrů vypsat jako text, ne <a>.'],
                            ['Vyřadit varianty produktů ze sitemapy', $must,
                                '104 adres, které se liší jen hmotností nebo velikostí.',
                                'Nastavení → Rozšířené → SEO → sitemap → vyloučit varianty (ověřit).'],
                            ['Nastavit canonical variant na hlavní produkt', $should,
                                'Dnes každá varianta označuje jako hlavní sama sebe.',
                                'Pokud to Upgates v nastavení neumí, napsat podpoře. Kontrola: curl -s URL varianty | grep canonical'],
                            ['Pomocné stránky nastavit jako „neindexovat“', $must,
                                'Oznámení (dovolená, lišta, košík), prázdná stránka Naše výhody, porovnání produktů a přihlášení k newsletteru.',
                                '/oznameni-dovolena, /oznameni-lista, /oznameni-kosik: jen SEO záložka, nemazat (šablona z nich bere texty). /why-us, /compare, /newsletter.'],
                            ['Zkrátit přesměrování na https://www', $should,
                                'Starý tvar adresy dnes vede přes dva kroky.',
                                'http://svetcejlonu.cz → http://www → https://www. Je to na serveru, napsat podpoře Upgates.'],
                            ['Zeptat se podpory Upgates na blokaci GPTBot a ClaudeBot', $should,
                                'Oba roboti dostávají od serveru chybu 503 ještě před robots.txt. Stejně odpovídá i web upgates.cz.',
                                'Jeden e-mail podpoře: 503 pro GPTBot a ClaudeBot (i na robots.txt), přesměrování ve dvou krocích, canonical variant. Test: curl -A "GPTBot/1.1" -I https://www.svetcejlonu.cz/'],
                        ],
                    ],
                    [
                        'title' => 'Ukázkový obsah Upgates',
                        'description' => 'Text vložený šablonou při zakládání e-shopu, který na webu zůstal.',
                        'items' => [
                            ['Smazat 4 ukázkové aktuality', $must,
                                '„Ke každému produktu dárek“, „Rozšířili jsme nabídku látek“, „Objednávejte nyní i přes Zásilkovnu“ a „Připravujeme novou kolekci!“.',
                                'Obsah → Aktuality.'],
                            ['Smazat návod na brož v Rádci', $must,
                                'Rádce pak naplníme skutečnými návody.',
                                'Obsah → Rádce.'],
                            ['Smazat výrobce „Upgates“', $must,
                                'Stránka /m/upgates je v sitemapě.',
                                'Produkty → Výrobci.'],
                            ['Odstranit nebo naplnit blok aktualit na úvodní stránce', $must,
                                'Dnes odkazuje na ukázkové aktuality.',
                                'Šablona úvodní stránky. Až budou skutečné novinky, blok vrátit.'],
                            ['Požádat o odstranění smazaných adres z výsledků', $should,
                                'Aby ukázkové stránky zmizely z výsledků co nejdřív.',
                                'GSC → Odstranění a Seznam Webmaster.'],
                        ],
                    ],
                    [
                        'title' => 'Zdravotní tvrzení',
                        'description' => 'U potravin a doplňků stravy nejde slibovat léčbu ani prevenci nemocí.',
                        'items' => [
                            ['Projít zdravotní tvrzení u 15 produktů', $must,
                                'Hlavně parametry Použití, Tradiční využití a Upozornění. U čajů a doplňků nechat jen to, co zákon dovoluje, tradiční použití na Srí Lance popsat bez slibu léčby.',
                                'Amla, Beli mal, Černý čaj Pekoe, Ibišek, Masala čaj, Moringa, Modrý čaj, Ranavara, Samahan, Zelený čaj GS-1; kosmetika: Ájurvédský balzám, Beamfiel, Bylinná mast, Bylinný balzám, Supirivicky pasta.'],
                            ['Nechat tvrzení posoudit odborníkem', $should,
                                'Konečné znění by měl potvrdit odborník na potravinové právo.',
                                null],
                            ['Upravit názvy produktů s diagnózou', $should,
                                'Názvy jako „Ájurvédský balzám (Bolest hlavy)“ slibují účinek už ve výsledcích hledání.',
                                'Při změně názvu nechat adresu, nebo přesměrovat.'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Listopad: šablona, titulky a Vánoce',
                'slug' => 'listopad',
                'description' => 'Jak e-shop vypadá ve výsledcích hledání a příprava na vánoční hledání.',
                'sections' => [
                    [
                        'title' => 'Titulky a popisky',
                        'description' => 'Titulek a popisek je první, co člověk ve výsledcích hledání uvidí.',
                        'items' => [
                            ['Titulek a popisek úvodní stránky', $must,
                                'Dnes v nich chybí čaj, koření, ájurvéda i Srí Lanka.',
                                null],
                            ['Titulky a popisky 6 kategorií a obsahových stránek', $must,
                                'Dnes je popisek kopie titulku.',
                                'Kategorie, O nás, škola, velkoobchod, vše o nákupu, chybí vám něco.'],
                            ['Upravit šablonu popisku, aby neopakovala titulek', $should,
                                'Platí pro stránky, u kterých popisek nikdo nevyplní.',
                                'Nastavení → Rozšířené → SEO (ověřit).'],
                            ['Přepsat úvod krátkého popisu u produktů', $should,
                                'U 41 produktů je popisek delší než 160 znaků, u 15 s emoji, 6 jich má méně než 70 znaků.',
                                'Krátké: Chilli, obě dárkové krabice, Přenosná čajová sada, Samahan, Skleněný louhovač.'],
                            ['Názvy produktů a kategorií psát normálně, ne verzálkami', $should,
                                '35 z 59 produktů má v názvu slovo velkými písmeny. Pokud design verzálky chce, nastaví se stylem v šabloně.',
                                'text-transform: uppercase v šabloně, v administraci normální text.'],
                            ['Opravit nadbytečné hlavní nadpisy v popisech', $nice,
                                'Ayush pleťový krém má pět hlavních nadpisů, Kardamon a O nás dva.',
                                'V editoru popisu změnit H1 na H2, smazat řádky z podtržítek.'],
                            ['Opravit překlep „RARANAVARA“', $should,
                                'Správně Ranavara.',
                                'Adresa /p/ranavara je správně, mění se jen název.'],
                        ],
                    ],
                    [
                        'title' => 'Šablona a strukturovaná data',
                        'description' => 'Údaje ve zdrojovém kódu, podle kterých Google pozná firmu, cenu a hodnocení.',
                        'items' => [
                            ['Opravit název webu a údaje o firmě', $must,
                                'Dnes se web i firma ve strukturovaných datech jmenují „Marek Bezdíček“ a cenová hladina je „$$$$$$“.',
                                '<head itemscope WebSite> itemprop name, patička LocalBusiness. Přidat alternateName, priceRange smazat.'],
                            ['Opravit formát recenzí', $must,
                                'Datum recenze má neplatný formát a hodnocení je zapsané jako souhrnné, takže hvězdičky se nejspíš neukážou.',
                                'datePublished "2026-08-01CEST14:52" → ISO 8601. Uvnitř Review reviewRating (Rating), ne AggregateRating. Ověřit v Rich Results Test.'],
                            ['Doplnit odkazy na profily firmy', $should,
                                'Instagram, Facebook, Heureka a Firmy.cz. Google a AI podle nich spojí web s profily.',
                                'sameAs v Organization.'],
                            ['Doplnit značku a EAN u produktů', $nice,
                                'Google je u produktů doporučuje.',
                                'brand, gtin13 pokud jsou EAN v administraci.'],
                            ['Doplnit dopravu a vrácení zboží', $should,
                                'Google je umí ukázat přímo ve výsledcích.',
                                'Organization: hasMerchantReturnPolicy + shippingDetails (viz audit, kapitola On-page).'],
                            ['Opravit og:type na úvodní stránce', $nice,
                                'Hodnota „web“ neexistuje, správně je „website“.',
                                null],
                            ['Založit stránku Kontakt', $must,
                                '/kontakt dnes vrací chybu. Provozovatel, IČO, e-mail, telefon a adresa pro vrácení zboží.',
                                'Odkaz do hlavního menu a patičky.'],
                            ['Rozhodnout, jestli uvést adresu firmy', $should,
                                'Od vás potřebujeme vědět, jestli adresu na webu a ve strukturovaných datech chcete mít.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Rychlost',
                        'description' => 'Server je rychlý, brzdí obrázky a měření na některých stránkách.',
                        'items' => [
                            ['Najít, proč Chrome na úvodní stránce a v kategoriích nezměří LCP', $should,
                                'Bez toho Google rychlost těchto stránek nevyhodnotí.',
                                'Lighthouse NO_LCP, PerformanceObserver prázdný i s prefers-reduced-motion, bez obrázků i bez externích skriptů. Detail produktu LCP má. Hledat v šabloně úvodky a kategorie (sc-hero, p-l-boxes).'],
                            ['Zmenšit produktové fotky a převést je do WebP', $should,
                                'Detail produktu má přes 4 MB, jeden obrázek PNG má 1,2 MB.',
                                'Např. chatgpt-image-22-9-2026-18-54-52.png na /p/skorice-mleta-cejlonska. Lighthouse odhad úspory ~2 MB.'],
                            ['Opravit posouvání obsahu v kategoriích', $should,
                                'CLS 0,15 až 0,21, Google chce pod 0,1.',
                                'Lighthouse mobil /caje, 2 běhy. Rezervovat výšku obrázků a bloků filtrů.'],
                            ['Nové fotky nahrávat s popisným názvem souboru', $nice,
                                'Pomáhá v Google Obrázcích. Staré fotky přejmenovávat nemusíme.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Vánoce',
                        'description' => 'Aby se dárkové balíčky dostaly do výsledků včas, musí být hotové v listopadu.',
                        'items' => [
                            ['Připravit stránku dárkových balíčků na vánoční hledání', $must,
                                'Titulek, popisek, text a odkazy z úvodní stránky a kategorií.',
                                null],
                            ['Zapojit feed do Google Merchant Center a Zboží.cz', $should,
                                'Produkty se pak zdarma ukážou v nákupních výsledcích Googlu a Seznamu. Obchod na Zboží.cz už je založený.',
                                'Zboží.cz obchod 277839. Feedy v Upgates (ověřit, jestli už běží).'],
                            ['Návod „dárek pro milovníka čaje“', $should,
                                'Na tohle lidé před Vánoci hledají a dnes na to e-shop nemá stránku.',
                                'Odkazy na hotové balíčky a konfigurátor.'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Prosinec: kategorie a produkty',
                'slug' => 'prosinec',
                'description' => 'Text, podle kterého Google a AI poznají, o čem kategorie a produkty jsou.',
                'sections' => [
                    [
                        'title' => 'Kategorie',
                        'description' => 'Dnes mají kategorie dvě věty a jinak stejný obsah.',
                        'items' => [
                            ['Opravit větu „Nevíte, který čaj vybrat?“ mimo čaje', $must,
                                'Je u Koření, Doplňků stravy, Ájurvédy i Ostatních.',
                                null],
                            ['Napsat delší text ke každé kategorii', $must,
                                'Co v kategorii je, odkud to pochází a jak vybrat.',
                                null],
                            ['Přidat ke kategoriím otázky a odpovědi', $should,
                                'Krátké odpovědi na to, na co se lidé ptají. Používají je Google i AI asistenti.',
                                null],
                            ['Přizpůsobit bloky pod výpisem kategorii', $should,
                                'Dnes jsou Nejprodávanější, Náš příběh a recenze všude stejné.',
                                null],
                            ['Založit podkategorie Černé čaje a Zelené čaje', $should,
                                'Lidé hledají černý a zelený sypaný čaj, e-shop na to má jen jednotlivé produkty.',
                                null],
                            ['Založit stránku o pravé cejlonské skořici', $should,
                                'Jedna stránka pro mletou i celou skořici a třídu Alba.',
                                'Štítky /koreni/t-nejvyssi-kvalita-alba a /darkove-balicky/t-nejvyssi-kvalita-alba neindexovat a odkázat na ni.'],
                            ['Vybrat štítky, které zůstanou v indexu, a napsat jim text', $should,
                                'Jen ty, na které lidé opravdu hledají. Ostatní zůstanou „neindexovat“.',
                                'Kandidáti podle hledání v auditu, kapitola Klíčová slova.'],
                        ],
                    ],
                    [
                        'title' => 'Produkty',
                        'description' => null,
                        'items' => [
                            ['Doplnit fakta u nejprodávanějších produktů', $should,
                                'Původ, oblast, sklizeň, kofein, teplota a doba louhování. Konkrétní fakta Google i AI citují nejčastěji.',
                                'Parametry už v administraci jsou, po vyřazení z filtrů je jen vypsat jako tabulku.'],
                            ['Přidat k hlavním produktům otázky a odpovědi', $nice,
                                'Na konec popisu.',
                                null],
                            ['Používat slova, která lidé hledají', $should,
                                'Lidé hledají „kardamom“, web píše „kardamon“. Lidé hledají „moringa prášek“ a „amla prášek“, web píše „mletá“.',
                                null],
                            ['Určit jednu hlavní stránku pro každé téma', $should,
                                'Dnes si o stejné hledání konkurují produkty, varianty a štítky ve více kategoriích, třeba u skořice nebo ájurvédského balzámu.',
                                'Mapa témat a stránek je v auditu, kapitola Klíčová slova.'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Leden: návody v Rádci',
                'slug' => 'leden',
                'description' => 'Odpovědi na otázky, které lidé kladou Googlu a AI, a odkazy z nich na produkty.',
                'sections' => [
                    [
                        'title' => 'Rádce',
                        'description' => 'Dnes je v Rádci jen ukázkový návod Upgates.',
                        'items' => [
                            ['Návod: cejlonská a čínská skořice', $should,
                                'Rozdíl, kumarin a jak poznat pravou skořici. Na Seznamu tu dnes vede konkurence s články.',
                                null],
                            ['Návod: jak louhovat sypaný čaj', $should,
                                'Teplota, čas a množství podle druhu čaje.',
                                null],
                            ['Návod: co je modrý čaj', $nice,
                                'Modrý čaj ze Srí Lanky lidé hledají v Googlu i na Seznamu.',
                                null],
                            ['Úvod do ájurvédy', $nice,
                                'Bez slibů léčby, jako vysvětlení tradice.',
                                null],
                            ['Recepty', $nice,
                                'Masala chai, garam masala, kari s kari listy.',
                                null],
                            ['Přepsat stránku O nás jako zdroj faktů o firmě', $should,
                                'Kdo za e-shopem stojí, od kdy, co prodává a odkud. AI si fakta o firmě bere odtud a z katalogů.',
                                null],
                            ['Pod návody uvádět autora', $should,
                                'Kdo text napsal a proč tomu rozumí. U zdraví a jídla to Google sleduje.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Únor: katalogy a odkazy',
                'slug' => 'unor',
                'description' => 'Co o e-shopu píšou ostatní weby a katalogy.',
                'sections' => [
                    [
                        'title' => 'Katalogy a profily',
                        'description' => null,
                        'items' => [
                            ['Sjednotit název firmy ve všech profilech', $should,
                                'Web a Heureka píšou „Svět Cejlonu“, Firmy.cz a Zboží.cz „Svetcejlonu.cz“, Facebook jinou variantu.',
                                'Firmy.cz detail 13935855, Zboží.cz obchod 277839, Facebook /svetcejlonu.'],
                            ['Opravit adresu a telefon na webtrziste.cz', $should,
                                'Profil uvádí jinou adresu a jiný telefon než všechny ostatní zdroje.',
                                'webtrziste.cz, profil stánkaře id 5453: Náchod 54701 a +420 775 104 889 místo Velké Poříčí 549 32 a +420 606 406 566. Taky uvádí „BIO“.'],
                            ['Sbírat hodnocení na Firmy.cz', $should,
                                'Dnes tam e-shop nemá žádné hodnocení. Seznam ho ukazuje přímo ve výsledcích.',
                                null],
                            ['Google Business Profile jen s výdejním místem', $nice,
                                'Čistý e-shop podle pravidel Googlu nárok nemá.',
                                null],
                        ],
                    ],
                    [
                        'title' => 'Dokončení úklidu indexu',
                        'description' => null,
                        'items' => [
                            ['Zakázat filtry v robots.txt, až zmizí z indexu', $should,
                                'Teprve potom, jinak by vyhledávače neviděly „neindexovat“ a stránky by v indexu zůstaly.',
                                'Kontrola v GSC → Stránky. Pak Disallow: /*/p-* a /*/m-* (štítky s vlastním textem povolit).'],
                        ],
                    ],
                    [
                        'title' => 'Odkazy z jiných webů',
                        'description' => null,
                        'items' => [
                            ['Rozeslat příběh tamilské školy', $nice,
                                'Médiím a blogům o Srí Lance a cestování. Přirozený důvod, proč na e-shop odkázat.',
                                null],
                            ['Oslovit čajovny a obchody, které od vás odebírají', $nice,
                                'Odkaz z jejich webu na e-shop nebo velkoobchod.',
                                null],
                            ['Nahrát videa ze Srí Lanky na YouTube', $nice,
                                'Videa z reklam se dají použít znovu. Google i AI videa z YouTube ukazují.',
                                null],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Březen: vyhodnocení',
                'slug' => 'brezen',
                'description' => 'Porovnání s výchozím stavem z října a plán na další měsíce.',
                'sections' => [
                    [
                        'title' => 'Vyhodnocení',
                        'description' => null,
                        'items' => [
                            ['Porovnat výsledky s výchozím stavem', $must,
                                'Index, dotazy, návštěvnost a objednávky z vyhledávání proti říjnu.',
                                null],
                            ['Zopakovat test v AI vyhledávání', $should,
                                'Stejné dotazy jako v auditu a porovnat, kde se e-shop objevuje.',
                                'Seznam dotazů v auditu, kapitola AI vyhledávání.'],
                            ['Naplánovat další kroky', $must,
                                'Podle toho, co zabralo.',
                                null],
                        ],
                    ],
                ],
            ],
        ];
    }
};
