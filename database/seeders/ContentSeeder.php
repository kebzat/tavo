<?php

namespace Database\Seeders;

use App\Models\CaseStudy;
use App\Models\CaseStudyCategory;
use App\Models\Founder;
use App\Models\Page;
use App\Models\ProcessStep;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Startovní obsah webu. Idempotentní (updateOrCreate podle slugu), takže se dá
 * spustit opakovaně a přepíše texty na aktuální znění.
 *
 * Reference jsou weby, e-shopy a reklamní účty, které Pavel a Tom mají za sebou.
 * Neuvádíme u nich čísla, protože je nemáme od klientů ověřená; dokud nebudou,
 * radši tam nebudou žádná.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->categories();
        $this->services();
        $this->caseStudies();
        $this->processSteps();
        $this->founders();
        $this->pages();
    }

    private function categories(): void
    {
        // Původní kategorie z designu přejmenováváme, ať u nich zůstanou navázané
        // reference a nevznikne druhá sada. Po prvním běhu už staré slugy neexistují
        // a projde se rovnou na updateOrCreate níž.
        $renames = [
            'sluzby' => ['name' => 'Weby', 'slug' => 'weby'],
            'e-commerce' => ['name' => 'E-shopy', 'slug' => 'eshopy'],
            'novy-web' => ['name' => 'Reklama', 'slug' => 'reklama'],
        ];

        foreach ($renames as $oldSlug => $attributes) {
            CaseStudyCategory::where('slug', $oldSlug)->update($attributes);
        }

        $categories = [
            ['name' => 'Weby', 'slug' => 'weby'],
            ['name' => 'E-shopy', 'slug' => 'eshopy'],
            ['name' => 'Reklama', 'slug' => 'reklama'],
        ];

        foreach ($categories as $i => $category) {
            CaseStudyCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['order_column' => $i + 1],
            );
        }
    }

    private function services(): void
    {
        // Původní tři služby z designu nahradily čtyři konkrétnější, které míří
        // na to, co lidi opravdu hledají. Staré necháváme jen nezveřejněné.
        Service::whereIn('slug', [
            'weby-a-eshopy',
            'marketing',
            'rozvoj-a-automatizace',
        ])->update(['published' => false, 'has_detail_page' => false]);

        Service::updateOrCreate(['slug' => 'tvorba-webovych-stranek'], [
            'order_column' => 1,
            'number' => '01',
            'title' => 'Tvorba webových stránek',
            'excerpt' => 'Firemní weby, prezentace a stránky pro kampaně. Píšeme je od nuly v Laravelu, nebo stavíme na WordPressu, podle toho, kdo je bude spravovat.',
            'has_detail_page' => true,
            'published' => true,
            'hero_eyebrow' => 'Služba 01',
            'hero_headline' => 'Tvorba webových stránek',
            'hero_headline_accent' => 'z Hradce.',
            'hero_perex' => 'Postavíme web, který návštěvníkovi během pár vteřin řekne, co děláte, a dá mu snadný způsob, jak se ozvat. Žádná koupená šablona a žádný projekt táhnoucí se půl roku.',
            'target_group_title' => 'Kdy má smysl nám napsat',
            'target_groups' => [
                'Máte firmu a web z roku 2015, který se na mobilu nedá číst.',
                'Web nemáte a zakázky chodí jen z doporučení. To funguje, dokud to funguje.',
                'Chystáte kampaň a potřebujete jednu stránku, která z návštěvy udělá poptávku.',
                'Do webu neumí nikdo z firmy sáhnout, aniž by musel volat vývojáře.',
            ],
            'offerings_title' => 'Co pro vás postavíme',
            'offerings' => [
                ['title' => 'Firemní web', 'text' => 'Pět až patnáct stránek, srozumitelná nabídka a formulář, jehož obsah chodí do e-mailu i do administrace.'],
                ['title' => 'Landing page', 'text' => 'Jedna stránka, jeden cíl. Když reklama vede na podstránku plnou odkazů, rozpočet se rozpustí.'],
                ['title' => 'Předělání starého webu', 'text' => 'Necháme, co funguje, přepíšeme zbytek. Staré adresy přesměrujeme, ať nepřijdete o pozice ve vyhledávání.'],
                ['title' => 'Administrace, do které si troufnete', 'text' => 'Laravel s Filamentem nebo WordPress. Texty a fotky si měníte sami.'],
                ['title' => 'Rychlost a technické SEO', 'text' => 'Web se má načíst do vteřiny a půl i na mobilu na datech.'],
                ['title' => 'Napojení na to, co používáte', 'text' => 'Fakturoid, rezervační systém, sklad, CRM, tabulky. Když to má rozhraní, dá se to propojit.'],
            ],
            'process_title' => 'Jak to probíhá',
            'process_steps' => [
                ['number' => '01', 'title' => 'Schůzka', 'text' => 'Hodina hovoru o tom, komu prodáváte a jaké poptávky chcete dostávat.'],
                ['number' => '02', 'title' => 'Návrh', 'text' => 'Struktura webu a vizuál. Uvidíte to dřív, než napíšeme první řádek kódu.'],
                ['number' => '03', 'title' => 'Stavba', 'text' => 'Kód, texty, fotky, měření. Průběžně to sledujete na testovací adrese.'],
                ['number' => '04', 'title' => 'Spuštění', 'text' => 'Přepneme doménu, ohlídáme přesměrování a projdeme s vámi administraci.'],
            ],
            'seo_title' => 'Tvorba webových stránek Hradec Králové',
            'seo_description' => 'Postavíme firemní web nebo landing page na míru. Rychlý, srozumitelný a s administrací, do které si troufnete sáhnout. Hradec Králové a okolí.',
        ]);

        Service::updateOrCreate(['slug' => 'tvorba-eshopu'], [
            'order_column' => 2,
            'number' => '02',
            'title' => 'Tvorba e-shopů',
            'excerpt' => 'Nový e-shop i předělávka toho, co máte. Shoptet, WooCommerce, Shopify, Upgates, nebo řešení na míru v Laravelu, když se běžná krabice nehodí.',
            'has_detail_page' => true,
            'published' => true,
            'hero_eyebrow' => 'Služba 02',
            'hero_headline' => 'Tvorba e-shopu od Shoptetu',
            'hero_headline_accent' => 'po vlastní kód.',
            'hero_perex' => 'Nemáme jednu platformu, kterou tlačíme každému. U menšího sortimentu obvykle vyjde nejlíp Shoptet, u složitějších skladů a napojení dává smysl WooCommerce nebo řešení na míru. Řekneme rovnou, co se vám vyplatí a co vás bude stát měsíčně.',
            'target_group_title' => 'Kdy má smysl nám napsat',
            'target_groups' => [
                'Prodáváte přes Facebook a zprávy a přestává se to dát zvládat.',
                'E-shop běží na staré šabloně, která se na mobilu rozpadá.',
                'Objednávky ručně přepisujete do skladu nebo do fakturace.',
                'Sortiment narostl a vyhledávání ani filtry v e-shopu už nestačí.',
            ],
            'offerings_title' => 'Na čem e-shop postavíme',
            'offerings' => [
                ['title' => 'Shoptet', 'text' => 'Nejrychlejší cesta k prodeji. Doladíme šablonu, kategorie a košík, ať to nevypadá jako ukázková instalace.'],
                ['title' => 'WooCommerce a WordPress', 'text' => 'Když chcete mít e-shop i obsah na jednom místě a všechno u sebe.'],
                ['title' => 'Shopify a Upgates', 'text' => 'Když už na nich jedete. Upravíme šablonu i napojení, stěhovat se nikam nemusíte.'],
                ['title' => 'E-shop na míru v Laravelu', 'text' => 'Pro konfigurátory, B2B ceníky a věci, které se do krabicového řešení nevejdou.'],
                ['title' => 'Napojení na sklad a fakturaci', 'text' => 'Objednávka doputuje tam, kam má, bez ručního přepisování.'],
                ['title' => 'Měření a produktové feedy', 'text' => 'Nastavíme měření objednávek a feedy pro Google a Meta, ať je na čem stavět kampaně.'],
            ],
            'process_title' => 'Jak to probíhá',
            'process_steps' => [
                ['number' => '01', 'title' => 'Co prodáváte', 'text' => 'Sortiment, marže, doprava a kdo bude objednávky vyřizovat.'],
                ['number' => '02', 'title' => 'Výběr platformy', 'text' => 'Doporučíme jednu a napíšeme proč. Včetně měsíčních nákladů.'],
                ['number' => '03', 'title' => 'Stavba', 'text' => 'Šablona, kategorie, platby, doprava a import produktů z toho, co máte.'],
                ['number' => '04', 'title' => 'Spuštění', 'text' => 'Zkušební objednávka, kontrola měření, a pak se dá pustit reklama.'],
            ],
            'seo_title' => 'Tvorba e-shopu na míru i na Shoptetu',
            'seo_description' => 'Postavíme e-shop na Shoptetu, WooCommerce, Shopify nebo Upgates, případně na míru v Laravelu. Napojíme sklad, fakturaci a měření. Hradec Králové.',
        ]);

        Service::updateOrCreate(['slug' => 'reklama-a-marketing'], [
            'order_column' => 3,
            'number' => '03',
            'title' => 'Reklama a marketing',
            'excerpt' => 'Kampaně na Facebooku, Instagramu a v Google Ads, práce s nabídkou a měření. Osm let praxe u e-shopů i firem ve službách.',
            'has_detail_page' => true,
            'published' => true,
            'hero_eyebrow' => 'Služba 03',
            'hero_headline' => 'Reklama, která přivede',
            'hero_headline_accent' => 'lidi, co koupí.',
            'hero_perex' => 'Pavel dělá výkonnostní reklamu osm let a prošly mu rukou desítky firemních účtů. Od e-shopů s potravinami a doplňky přes firmy ve službách po nábor lidí do výroby.',
            'target_group_title' => 'Kdy má smysl nám napsat',
            'target_groups' => [
                'Reklama vám běží, ale nevíte, co z ní vlastně chodí.',
                'Propagujete příspěvky tlačítkem na Facebooku a doufáte.',
                'Máte e-shop a potřebujete se dostat přes bod, kdy reklama stojí víc, než přinese.',
                'Sháníte lidi do firmy a inzerát na pracovních portálech přestal stačit.',
            ],
            'offerings_title' => 'Co pro vás rozjedeme',
            'offerings' => [
                ['title' => 'Facebook a Instagram', 'text' => 'Nastavení účtu, publika, kreativa a průběžné ladění. Nejčastější věc, kterou po nás lidi chtějí.'],
                ['title' => 'Google Ads', 'text' => 'Vyhledávací a nákupní kampaně. Tam, kde už člověk ví, co hledá, a jen vybírá u koho.'],
                ['title' => 'Nábor lidí', 'text' => 'Náborové kampaně a employer branding. Chce to jiné texty i jiné cílení než prodej.'],
                ['title' => 'Práce s nabídkou', 'text' => 'Občas nejde o reklamu. Když se nabídka nedá pochopit za dvě věty, je zbytečné do ní lít peníze.'],
                ['title' => 'Měření a reporting', 'text' => 'Měření objednávek a poptávek v GA4. Report, ze kterého poznáte, jestli se to vyplatilo.'],
                ['title' => 'Konzultace na hodinu', 'text' => 'Když si chcete kampaně dělat sami a potřebujete jen ukázat směr.'],
            ],
            'process_title' => 'Jak to probíhá',
            'process_steps' => [
                ['number' => '01', 'title' => 'Audit', 'text' => 'Projdeme, co běží teď, a spočítáme, kolik stojí jedna objednávka nebo poptávka.'],
                ['number' => '02', 'title' => 'Plán', 'text' => 'Kam poteče rozpočet, na koho cílíme a co budeme měřit.'],
                ['number' => '03', 'title' => 'Spuštění', 'text' => 'Kreativy, texty, kampaně. První použitelná data máme obvykle do dvou týdnů.'],
                ['number' => '04', 'title' => 'Ladění', 'text' => 'Vypínáme, co nevydělává, a přiléváme tam, kde to jede.'],
            ],
            'seo_title' => 'Správa reklamy na Facebooku a Google Ads',
            'seo_description' => 'Nastavíme a spravujeme kampaně na Facebooku, Instagramu a v Google Ads. Osm let praxe u e-shopů i firem ve službách. Hradec Králové a okolí.',
        ]);

        Service::updateOrCreate(['slug' => 'sprava-a-rozvoj-webu'], [
            'order_column' => 4,
            'number' => '04',
            'title' => 'Správa a rozvoj webu',
            'excerpt' => 'Aktualizace, zálohy, drobné úpravy a průběžné zlepšování. Ať máte komu zavolat, když něco přestane fungovat.',
            'has_detail_page' => true,
            'published' => true,
            'hero_eyebrow' => 'Služba 04',
            'hero_headline' => 'Správa webu, ať máte',
            'hero_headline_accent' => 'komu zavolat.',
            'hero_perex' => 'Web není hotový spuštěním. Přebíráme i weby, které jsme nestavěli, pokud se k nim dostaneme přes FTP a kód není úplná džungle.',
            'target_group_title' => 'Kdy má smysl nám napsat',
            'target_groups' => [
                'Původní dodavatel skončil, nebo přestal odpovídat.',
                'Web běží na WordPressu, který se dva roky neaktualizoval.',
                'Potřebujete jednou za čas přidat stránku a nemá to kdo udělat.',
                'Chcete web zlepšovat podle toho, co ukazují data, ne podle nápadů z porady.',
            ],
            'offerings_title' => 'Co se dá domluvit',
            'offerings' => [
                ['title' => 'Aktualizace a zálohy', 'text' => 'WordPress, pluginy, verze PHP. Zálohy, ze kterých jde web opravdu obnovit.'],
                ['title' => 'Drobné úpravy', 'text' => 'Nová stránka, změna textu, banner, formulář. Do pár dní, ne do měsíce.'],
                ['title' => 'Převzetí cizího webu', 'text' => 'Projdeme kód a hosting a řekneme rovnou, jestli se vyplatí spravovat, nebo přestavět.'],
                ['title' => 'Zrychlení', 'text' => 'Obrázky, cache, hosting. Často jde stáhnout načtení na polovinu bez přepisování webu.'],
                ['title' => 'Postupné zlepšování', 'text' => 'Každý měsíc jedna věc, o které data říkají, že za změnu stojí.'],
                ['title' => 'Bezpečnost', 'text' => 'Certifikát, aktualizace, ochrana formulářů proti spamu.'],
            ],
            'process_title' => 'Jak to probíhá',
            'process_steps' => [
                ['number' => '01', 'title' => 'Průchod webem', 'text' => 'Co běží, kde to běží a co je rozbité.'],
                ['number' => '02', 'title' => 'Návrh rozsahu', 'text' => 'Co má smysl řešit hned a co klidně počká.'],
                ['number' => '03', 'title' => 'Převzetí', 'text' => 'Přístupy, zálohy, monitoring. Ať se dá web obnovit, když se něco stane.'],
                ['number' => '04', 'title' => 'Provoz', 'text' => 'Domluvíme rozsah hodin na měsíc a co v nich je. Zbytek řešíme podle potřeby.'],
            ],
            'seo_title' => 'Správa webu a údržba WordPressu',
            'seo_description' => 'Aktualizace, zálohy, drobné úpravy a zrychlení webu. Přebíráme i weby po jiném dodavateli. Hradec Králové, jednáte přímo s vývojářem.',
        ]);
    }

    private function caseStudies(): void
    {
        // Původní ukázkové reference z designu měly vymyšlená čísla i citace klientů.
        // „spravovane-eshopy" byl jen výčet účtů, které Pavel spravuje. Nahradily ho
        // konkrétní ukázky jeho práce (grafika, reels). Vše necháváme v DB nezveřejněné.
        CaseStudy::whereIn('slug', [
            'rodinny-eshop',
            'web-generujici-poptavky',
            'b2b-vyrobce-redesign',
            'sezonni-landing-page',
            'spravovane-eshopy',
        ])->update(['published' => false, 'is_featured' => false]);

        $weby = CaseStudyCategory::where('slug', 'weby')->value('id');
        $reklama = CaseStudyCategory::where('slug', 'reklama')->value('id');

        $solo = 'U tohoto projektu neuvádíme čísla o návštěvnosti a poptávkách. Patří klientovi a nemáme je pro web ověřená.';

        CaseStudy::updateOrCreate(['slug' => 'vcely-uhersko'], [
            'order_column' => 1,
            'case_study_category_id' => $weby,
            'title' => 'Včely Uhersko',
            'is_featured' => true,
            'published' => true,
            'eyebrow' => 'Včelařství · Značka a web',
            'thumb_label' => 'Web Včely Uhersko',
            'excerpt' => 'Rodinný chov včel v třešňovém sadu. Vizuální styl, etiketa na med a web, který kromě medu zve i na přespání karavanem přímo v sadu.',
            'tags' => [
                'Vizuální styl',
                'Web na míru',
            ],
            'hero_headline' => 'Značka a web pro',
            'hero_headline_accent' => 'Včely Uhersko.',
            'hero_perex' => 'Rodina, která chová včely v třešňovém sadu a rozhodla se ten sad otevřít lidem. Potřebovala značku, která unese med i to místo.',
            'client' => 'Včely Uhersko',
            'industry' => 'Včelařství a agroturistika',
            'scope' => 'Vizuální styl · Etiketa · Nový web',
            'problem_title' => 'Zadání',
            'problem_text' => 'Med se prodává i příběhem, jenže ten se dá zkazit lacinou grafikou. Včely Uhersko navíc nejsou jen med. V sadu se dá přespat karavanem a chystá se apidomek přímo u úlů. Značka tedy musela unést dvě věci naráz: sklenici na pultu a místo, kam člověk přijede na noc.',
            'problem_points' => [
                'Značka neexistovala, chyběl vizuál i etiketa na sklenici.',
                'Med a přespání v sadu jsou dvě nabídky pro dvě různá publika.',
                'Apidomek se teprve staví, takže web musel počítat s tím, že nabídka poroste.',
            ],
            'roles_title' => 'Co jsme na projektu dělali',
            'roles_perex' => 'Vizuální styl, etiketu i web jsme dělali od nuly.',
            'dev_title' => 'Značka a web',
            'dev_items' => [
                'Vizuální styl značky od loga po barvy a typografii.',
                'Návrh etikety na med, ať sklenice a web mluví stejným jazykem.',
                'Klidný web, kde fotky sadu nesou víc než text.',
                'Sekce pro přespání v sadu s odkazem na rezervaci přes Bezkempu.',
                'Galerie sadu a připravené místo pro apidomek, který se teprve staví.',
            ],
            'disclaimer' => $solo,
        ]);

        CaseStudy::updateOrCreate(['slug' => 'chrudimlab'], [
            'order_column' => 2,
            'case_study_category_id' => $weby,
            'title' => 'ChrudimLab',
            'is_featured' => true,
            'published' => true,
            'eyebrow' => 'Zubní protetika · Nový web',
            'thumb_label' => 'Web ChrudimLab',
            'excerpt' => 'Zubní laboratoř Dlouhá Brázda z Chrudimi. Web ukazuje zubařům, s jakými materiály a technologiemi laboratoř pracuje, a vede je k zadání zakázky.',
            'tags' => [
                'Web na míru',
                'Ceník a zakázky',
            ],
            'hero_headline' => 'Web pro zubní laboratoř',
            'hero_headline_accent' => 'ChrudimLab.',
            'hero_perex' => 'Laboratoř Dlouhá Brázda spojuje ruční protetiku s frézovacím centrem a školí zubní techniky. Web to všechno musel ukázat na jednom místě.',
            'client' => 'Zubní laboratoř Dlouhá Brázda',
            'industry' => 'Zubní protetika',
            'scope' => 'Nový web · Rezervace · Sekce pro lékaře',
            'problem_title' => 'Zadání',
            'problem_text' => 'Zubař si laboratoř nevybírá podle ceny, ale podle toho, čemu u ní věří. Zajímá ho vybavení, materiály a jestli mu práce přijde včas. Web tedy musel ukázat frézovací centrum, konkrétní práce a ceník, a k tomu ještě zvládnout školicí centrum, které míří na úplně jiné publikum.',
            'problem_points' => [
                'Vybavení a technologie byly to hlavní, čím se laboratoř liší, a nikde to nebylo vidět.',
                'Zakázky se domlouvaly telefonem a e-mailem bez jasného postupu.',
                'Ordinace potřebovaly místo, kam se přihlásí a vyřídí věci bez telefonátu.',
            ],
            'roles_title' => 'Co jsme na projektu dělali',
            'roles_perex' => 'Web i obsah má na starosti Tom.',
            'dev_title' => 'Tom: vývoj a obsah',
            'dev_items' => [
                'Prezentace frézovacího centra a technologií, kterými se laboratoř liší.',
                'Vlastní rezervační formulář místo domlouvání po telefonu.',
                'Uzamčená sekce pro lékaře pro komunikaci s ordinacemi.',
                'Fotogalerie hotových prací, protože ta přesvědčí zubaře nejrychleji.',
                'Ceník a kalendář školení pro zubní techniky v administraci, kterou si laboratoř spravuje sama.',
            ],
            'disclaimer' => $solo,
        ]);

        CaseStudy::updateOrCreate(['slug' => 'hopnjoy'], [
            'order_column' => 3,
            'case_study_category_id' => $weby,
            'title' => "Hop'n'Joy",
            'is_featured' => true,
            'published' => true,
            'eyebrow' => 'Půjčovna · Logo a web',
            'thumb_label' => "Web Hop'n'Joy",
            'excerpt' => 'Půjčovna skákacích hradů z Chrudimi. Hravé logo, katalog atrakcí a objednávka, která nahradila dohadování v soukromých zprávách.',
            'tags' => [
                'Logo',
                'Web na míru',
            ],
            'hero_headline' => 'Skákací hrady',
            'hero_headline_accent' => "Hop'n'Joy.",
            'hero_perex' => 'Půjčovna atrakcí, která do té doby domlouvala všechno přes zprávy na Facebooku. Web měl většinu dotazů zodpovědět dopředu.',
            'client' => "Hop'n'Joy, Chrudim",
            'industry' => 'Pronájem atrakcí',
            'scope' => 'Logo · Nový web · Katalog',
            'problem_title' => 'Zadání',
            'problem_text' => 'Objednávka skákacího hradu je vlastně sled stejných otázek. Kolik to zabere místa, vejde se to na zahradu, dovezete to, co to stojí. Když na ně odpoví web, zbyde na telefon jen domluva termínu.',
            'problem_points' => [
                'Každá poptávka začínala stejnými dotazy v soukromé zprávě.',
                'Nabídka atrakcí nebyla nikde pohromadě.',
                'Rodič potřebuje vidět rozměry a fotky, ne obecný popis.',
            ],
            'roles_title' => 'Co jsme na projektu dělali',
            'roles_perex' => 'Web i obsah má na starosti Tom.',
            'dev_title' => 'Tom: vývoj a obsah',
            'dev_items' => [
                'Logo, které sedí k dětským oslavám a dá se použít i na plachtu na hrad.',
                'Katalog atrakcí s fotkami a rozměry u každé položky.',
                'Popis toho, jak dovoz a instalace probíhá, aby se na to nikdo nemusel ptát.',
                'Objednávka rovnou z webu místo dohadování ve zprávách.',
                'Administrace, ve které si půjčovna přidává nové atrakce sama.',
            ],
            'disclaimer' => $solo,
        ]);

        CaseStudy::updateOrCreate(['slug' => 'ales-malinsky'], [
            'order_column' => 4,
            'case_study_category_id' => $weby,
            'title' => 'Aleš Malinský',
            'is_featured' => false,
            'published' => true,
            'eyebrow' => 'Reality · Logo a web',
            'thumb_label' => 'Web Aleš Malinský',
            'excerpt' => 'Nové logo a osobní web realitního makléře z Hradce Králové. Nabídka nemovitostí, popis služeb a odpovědi na to, co se lidé před prodejem bytu ptají nejčastěji.',
            'tags' => [
                'Logo',
                'Osobní web',
            ],
            'hero_headline' => 'Osobní web',
            'hero_headline_accent' => 'realitního makléře.',
            'hero_perex' => 'U prodeje bytu se člověk rozhoduje podle toho, komu věří. Web měl tu důvěru podpořit dřív, než dojde na první telefonát.',
            'client' => 'Aleš Malinský',
            'industry' => 'Reality',
            'scope' => 'Logo · Osobní web',
            'problem_title' => 'Zadání',
            'problem_text' => 'Makléřů je v každém městě několik desítek a na portálu vypadají všichni stejně. Prodej bytu je přitom rozhodnutí o milionech, u kterého klient potřebuje vědět, s kým bude půl roku v kontaktu. Vlastní web dává prostor, který se do profilu na realitce nevejde.',
            'problem_points' => [
                'Profil na realitním portálu vypadá stejně jako profil konkurence.',
                'Prodávající se ptají pořád na to samé, jen se nemají kde zeptat dopředu.',
                'Nabídku nemovitostí bylo potřeba mít i na vlastní adrese.',
            ],
            'roles_title' => 'Co jsme na projektu dělali',
            'roles_perex' => 'Web i obsah má na starosti Tom.',
            'dev_title' => 'Tom: vývoj a obsah',
            'dev_items' => [
                'Nové logo a vizuální styl, který drží od vizitky po web.',
                'Web postavený kolem konkrétního člověka, ne kolem realitní branže.',
                'Výpis nemovitostí, které má makléř zrovna v nabídce.',
                'Sekce s nejčastějšími dotazy, ať se prodávající zorientuje ještě před schůzkou.',
                'Kontakt dostupný z každé stránky.',
            ],
            'disclaimer' => $solo,
        ]);

        // Reálné ukázky Pavlovy práce stažené z jeho webu. Texty jsou zatím
        // pracovní, Pavel si je doupraví; obrázky jsou skutečné kreativy a snímky
        // z reels pro jeho klienty.
        $adDisclaimer = 'Ukázky jsou reálné kreativy pro klienty Pavlových reklamních účtů. Konkrétní čísla nezveřejňujeme, patří klientům.';

        CaseStudy::updateOrCreate(['slug' => 'reklamni-grafika'], [
            'order_column' => 5,
            'case_study_category_id' => $reklama,
            'title' => 'Reklamní grafika',
            'is_featured' => true,
            'published' => true,
            'eyebrow' => 'Grafika · Reklamní kreativa',
            'thumb_label' => 'Reklamní grafika',
            'excerpt' => 'Bannery a příspěvky pro e-shopy i firmy. Kreativa, která ve feedu zastaví palec a řekne to podstatné za vteřinu.',
            'tags' => [
                'Reklamní kreativa',
                'Sociální sítě',
            ],
            'hero_headline' => 'Grafika, která ve feedu',
            'hero_headline_accent' => 'zastaví palec.',
            'hero_perex' => 'Dobrá kreativa je dnes to hlavní cílení. Ve feedu má sekundu na to, aby člověka zastavila, a další dvě, aby mu řekla, proč zůstat. Proto se u kampaní netočí jedna grafika, ale celá série a testuje se, co zabírá.',
            'client' => 'Realimo, Svět cejlonu a další',
            'industry' => 'E-commerce a služby',
            'scope' => 'Reklamní grafika · Bannery',
            'problem_title' => 'Proč na kreativě záleží',
            'problem_text' => 'Zaplacená reklama je jen tak dobrá jako obrázek, na kterém stojí. Ať se cílí sebelíp, palec se zastaví jen na tom, co ho zaujme. Grafika proto musí unést nabídku, cenu i důvod ke kliknutí, a to na malém formátu, který člověk vidí pár vteřin.',
            'problem_points' => [
                'Nabídka a cena musí být čitelné na první pohled, i na mobilu.',
                'Každá kampaň chce vlastní vizuál, ne jednu grafiku dokola.',
                'Bez variant se nedá poznat, které sdělení zabírá.',
            ],
            'roles_title' => 'Co Pavel dělá',
            'roles_perex' => 'Grafiku i kampaně vede Pavel se svými parťáky.',
            'marketing_title' => 'Pavel: grafika a reklama',
            'marketing_items' => [
                'Návrh reklamních vizuálů podle nabídky a sezóny.',
                'Série variant pro testování, ne jeden banner.',
                'Sladění grafiky s tím, kam reklama vede.',
                'Vyhodnocení, co ve feedu funguje, a úprava podle toho.',
            ],
            'disclaimer' => $adDisclaimer,
        ]);

        CaseStudy::updateOrCreate(['slug' => 'reels-video'], [
            'order_column' => 6,
            'case_study_category_id' => $reklama,
            'title' => 'Reels pro sociální sítě',
            'is_featured' => false,
            'published' => true,
            'eyebrow' => 'Video · Reels',
            'thumb_label' => 'Reels',
            'excerpt' => 'Krátká svislá videa pro Instagram a Facebook. Pro značky jako Svět cejlonu, Fitmin nebo Českou louku.',
            'tags' => [
                'Reels',
                'Video',
            ],
            'hero_headline' => 'Reels, které lidi',
            'hero_headline_accent' => 'dokoukají.',
            'hero_perex' => 'Reels rozhodne první vteřina. Buď zaujme a člověk zůstane, nebo palcem sjede dál. Točíme krátká videa, která jdou rovnou k věci a ukážou produkt tak, jak ho zákazník potká doma.',
            'client' => 'Svět cejlonu, Fitmin, Česká louka',
            'industry' => 'E-commerce',
            'scope' => 'Reels · Krátká videa',
            'problem_title' => 'Proč reels',
            'problem_text' => 'Dosah na sítích dnes táhne video, ne statický příspěvek. Reels ale nestačí jen natočit, musí od první vteřiny držet pozornost a končit jasnou pobídkou. Pro každou značku to znamená jiný tón: čaj se ukazuje jinak než krmivo pro psy.',
            'problem_points' => [
                'První vteřina rozhoduje, jestli člověk zůstane, nebo odjede dál.',
                'Produkt musí být vidět v běžné situaci, ne jen v katalogu.',
                'Každá značka chce jiný tón i tempo střihu.',
            ],
            'roles_title' => 'Co Pavel dělá',
            'roles_perex' => 'Scénář, natáčení i střih vede Pavel se svými parťáky.',
            'marketing_title' => 'Pavel: video a reels',
            'marketing_items' => [
                'Náměty a scénáře podle produktu a značky.',
                'Natáčení a střih do svislého formátu pro reels.',
                'Titulky a pobídka, ať video funguje i bez zvuku.',
                'Nasazení do kampaní a sledování, co lidi dokoukají.',
            ],
            'disclaimer' => $adDisclaimer,
        ]);
    }

    private function processSteps(): void
    {
        $steps = [
            ['number' => '01', 'title' => 'Zavoláme si', 'text' => 'Půl hodiny o tom, co děláte a co od webu čekáte.'],
            ['number' => '02', 'title' => 'Napíšeme nabídku', 'text' => 'Rozsah, cena a termín. Písemně, ať se k tomu dá vrátit.'],
            ['number' => '03', 'title' => 'Postavíme', 'text' => 'Web nebo e-shop, texty a měření. Průběžně to vidíte.'],
            ['number' => '04', 'title' => 'Spustíme', 'text' => 'Přepnutí domény, kontrola měření a první kampaň.'],
            ['number' => '05', 'title' => 'Zlepšujeme', 'text' => 'Podle dat, ne podle pocitu z porady.', 'highlight' => true],
        ];

        foreach ($steps as $i => $step) {
            ProcessStep::updateOrCreate(
                ['number' => $step['number']],
                $step + ['order_column' => $i + 1, 'highlight' => $step['highlight'] ?? false],
            );
        }
    }

    private function founders(): void
    {
        Founder::updateOrCreate(['name' => 'Pavel'], [
            'order_column' => 1,
            'role_label' => 'Reklama a marketing',
            'bio' => 'Osm let dělá výkonnostní marketing. Kampaně na Facebooku a Instagramu, Google Ads, práce s nabídkou a nábor lidí. Prošly mu rukou desítky firemních účtů, od e-shopů s potravinami po výrobní firmy.',
            'tags' => [
                'Facebook a Instagram',
                'Google Ads',
                'Nábor lidí',
            ],
            'external_url' => 'https://pavelvcelis.cz/',
        ]);

        Founder::updateOrCreate(['name' => 'Tom'], [
            'order_column' => 2,
            'role_label' => 'Weby a e-shopy',
            'bio' => 'Osm let staví weby a e-shopy. Laravel a PHP, WordPress s WooCommerce, Shoptet, Shopify, Upgates. Nejradši má věci, které někomu ušetří ruční přepisování dat.',
            'tags' => [
                'Laravel a PHP',
                'WordPress a WooCommerce',
                'Shoptet a Upgates',
            ],
            'external_url' => 'https://juliatom.cz/',
        ]);
    }

    private function pages(): void
    {
        Page::updateOrCreate(['slug' => 'ochrana-osobnich-udaju'], [
            'title' => 'Ochrana osobních údajů',
            'perex' => 'Jak nakládáme s údaji, které nám pošlete přes poptávkový formulář.',
            'content' => <<<'HTML'
<h2>Kdo je správcem údajů</h2>
<p>Správcem osobních údajů je Taveo. Kontaktovat nás můžete na e-mailu uvedeném v patičce webu.</p>
<h2>Jaké údaje zpracováváme</h2>
<p>Zpracováváme údaje, které nám sami vyplníte v poptávkovém formuláři: jméno, firmu, e-mail, telefon a text zprávy. Dále technické údaje nutné k ochraně před spamem (IP adresa, prohlížeč).</p>
<h2>Proč je zpracováváme</h2>
<p>Výhradně proto, abychom mohli odpovědět na vaši poptávku a případně s vámi uzavřít smlouvu. Údaje nepředáváme třetím stranám ani je nepoužíváme k reklamnímu oslovování.</p>
<h2>Jak dlouho je uchováváme</h2>
<p>Poptávky uchováváme po dobu tří let od posledního kontaktu, pak je mažeme.</p>
<h2>Vaše práva</h2>
<p>Máte právo na přístup ke svým údajům, jejich opravu, výmaz i vznesení námitky proti zpracování. Stačí nám napsat.</p>
HTML,
            'published' => true,
        ]);

        Page::updateOrCreate(['slug' => 'cookies'], [
            'title' => 'Cookies',
            'perex' => 'Které cookies web používá a jak souhlas kdykoliv změnit.',
            'content' => <<<'HTML'
<h2>Nezbytné cookies</h2>
<p>Web používá technické cookies nutné pro jeho běh, tedy udržení relace a ochranu formuláře před zneužitím. Tyto cookies nelze vypnout a nesbírají údaje pro marketing.</p>
<h2>Analytické cookies</h2>
<p>Pokud udělíte souhlas, načteme měřicí kód, který nám anonymně ukazuje, jak se web používá. Bez souhlasu se nenačte vůbec.</p>
<h2>Změna souhlasu</h2>
<p>Souhlas můžete kdykoliv odvolat vymazáním úložiště webu ve svém prohlížeči. Banner se pak zobrazí znovu.</p>
HTML,
            'published' => true,
        ]);
    }
}
