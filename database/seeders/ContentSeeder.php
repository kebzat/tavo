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
 * Startovní obsah přepsaný 1:1 z původního designu (design-source/*.dc.html).
 * Idempotentní — používá updateOrCreate podle slugu/pořadí, takže se dá
 * spustit opakovaně bez duplicit.
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
        $categories = [
            ['name' => 'E-commerce', 'slug' => 'e-commerce'],
            ['name' => 'Služby', 'slug' => 'sluzby'],
            ['name' => 'Redesign', 'slug' => 'redesign'],
            ['name' => 'Nový web', 'slug' => 'novy-web'],
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
        Service::updateOrCreate(['slug' => 'weby-a-eshopy'], [
            'order_column' => 1,
            'number' => '01',
            'title' => 'Weby & e-shopy',
            'excerpt' => 'Firemní a prodejní weby, landing pages, e-shopy, redesigny, integrace a měření. Postavené v Laravelu i na Shoptetu — rychlé, měřitelné, připravené na růst.',
            'has_detail_page' => true,
            'published' => true,
            'hero_eyebrow' => 'Služba 01',
            'hero_headline' => 'Weby & e-shopy, které',
            'hero_headline_accent' => 'prodávají.',
            'hero_perex' => 'Stavíme weby a e-shopy s marketingem v hlavě od první čáry. Ne šablona, ne nekonečný projekt — rychlý web, který umí přesvědčit a jehož výsledky jde změřit.',
            'target_group_title' => 'Kdy má smysl nám napsat',
            'target_groups' => [
                ['text' => 'Potřebujete nový firemní web, který bude generovat poptávky, ne jen existovat.'],
                ['text' => 'Máte zastaralý web nebo e-shop, který brzdí prodej a nejde rozumně rozvíjet.'],
                ['text' => 'Chystáte kampaň a potřebujete landing page, která z návštěvy udělá objednávku.'],
                ['text' => 'Chcete konečně vědět, co na webu funguje — a podle toho ho zlepšovat.'],
            ],
            'offerings_title' => 'Co konkrétně stavíme',
            'offerings' => [
                ['title' => 'Firemní & prodejní weby', 'text' => 'Web s jasnou nabídkou a strukturou, která vede návštěvníka k akci. Vlastní design, ne šablona.'],
                ['title' => 'Landing pages', 'text' => 'Jedno sdělení, jeden cíl. Rychlá stránka postavená přesně pod konkrétní kampaň.'],
                ['title' => 'E-shopy & redesigny', 'text' => 'Nové e-shopy i přestavby stávajících. Laravel i Shoptet — podle toho, co dává smysl.'],
                ['title' => 'Integrace & vývoj na míru', 'text' => 'Propojení s ERP, skladem, fakturací nebo CRM. Automatizace opakované ruční práce.'],
                ['title' => 'Měření & analytika', 'text' => 'GA4, konverze, události, napojení na reklamní systémy. Data, kterým rozumíte.'],
            ],
            'process_title' => 'Jak to probíhá',
            'process_steps' => [
                ['number' => '01', 'title' => 'Zadání & cíl', 'text' => 'Co má web přinášet a komu. Nabídka, sdělení, struktura.'],
                ['number' => '02', 'title' => 'Design', 'text' => 'Design v našem stylu, na míru značce — ne šablona.'],
                ['number' => '03', 'title' => 'Vývoj', 'text' => 'Rychlý web v Laravelu / Tailwindu, měření a integrace.'],
                ['number' => '04', 'title' => 'Spuštění & rozvoj', 'text' => 'Zapneme, změříme a dál zlepšujeme podle dat.'],
            ],
            'seo_title' => 'Weby a e-shopy na míru',
            'seo_description' => 'Firemní weby, landing pages a e-shopy postavené v Laravelu i na Shoptetu — rychlé, měřitelné a připravené na růst.',
        ]);

        Service::updateOrCreate(['slug' => 'marketing'], [
            'order_column' => 2,
            'number' => '02',
            'title' => 'Marketing & získávání zákazníků',
            'excerpt' => 'Strategie, výkonnostní reklama, kampaně, práce s nabídkou a sdělením, analytika a doporučení zaměřená na konverze. Přivedeme lidi, u kterých dává byznys smysl.',
            'has_detail_page' => false,
            'published' => true,
        ]);

        Service::updateOrCreate(['slug' => 'rozvoj-a-automatizace'], [
            'order_column' => 3,
            'number' => '03',
            'title' => 'Dlouhodobý rozvoj & automatizace',
            'excerpt' => 'Průběžný rozvoj webu i e-shopu, marketingové retainery, reporting, optimalizace konverzí a automatizace opakované práce. AI používáme interně, ať jsme rychlejší — není to náš „produkt".',
            'has_detail_page' => false,
            'published' => true,
        ]);
    }

    private function caseStudies(): void
    {
        $ecommerce = CaseStudyCategory::where('slug', 'e-commerce')->value('id');
        $sluzby = CaseStudyCategory::where('slug', 'sluzby')->value('id');
        $redesign = CaseStudyCategory::where('slug', 'redesign')->value('id');
        $novyWeb = CaseStudyCategory::where('slug', 'novy-web')->value('id');

        CaseStudy::updateOrCreate(['slug' => 'rodinny-eshop'], [
            'order_column' => 1,
            'case_study_category_id' => $ecommerce,
            'title' => 'Rodinný e-shop, který přestal růst',
            'is_featured' => true,
            'published' => true,
            'eyebrow' => 'E-commerce · Redesign + kampaně',
            'thumb_label' => 'Foto projektu — e-shop',
            'excerpt' => 'Zastaralý Shoptet, drahá reklama bez měření. Přestavěli jsme klíčové šablony, propojili data a přenastavili kampaně na skutečně ziskové produkty.',
            'headline_metric' => '+41 %',
            'tags' => [
                ['text' => 'Marketing: kampaně, měření'],
                ['text' => 'Vývoj: šablony, integrace'],
            ],
            'hero_headline' => 'Rodinný e-shop, který',
            'hero_headline_accent' => 'přestal růst.',
            'hero_perex' => 'Zastaralý Shoptet a drahá reklama bez měření. Přestavěli jsme klíčové šablony, propojili data a přenastavili kampaně na produkty, které skutečně vydělávají.',
            'client' => 'Rodinný e-shop *',
            'industry' => 'Domácnost & bydlení',
            'scope' => 'Redesign · Měření · Kampaně',
            'duration' => '10 týdnů + retainer',
            'problem_title' => 'Výchozí stav',
            'problem_text' => 'Tržby stagnovaly, přestože do reklamy tekly peníze. E-shop běžel na starém Shoptetu, byl pomalý na mobilu a nikdo nedokázal říct, které kampaně jsou ziskové a které jen spalují rozpočet.',
            'problem_points' => [
                ['text' => 'Pomalé načítání a zastaralé šablony produktů.'],
                ['text' => 'Chybějící měření, žádná návaznost reklamy na tržby.'],
                ['text' => 'Reklama vedla na produkty s nízkou marží.'],
            ],
            'roles_title' => 'Jak jsme to táhli spolu',
            'roles_perex' => 'Marketing a vývoj neběžely po sobě, ale vedle sebe — jedno rozhodnutí ovlivnilo druhé.',
            'marketing_title' => 'Role marketingu — Pavel',
            'marketing_items' => [
                ['text' => 'Analýza ziskovosti podle produktů a přesun rozpočtu na to, co vydělává.'],
                ['text' => 'Přestavba kampaní a práce se sdělením podle sezóny.'],
                ['text' => 'Průběžné vyhodnocování a doporučení k nabídce.'],
            ],
            'dev_title' => 'Role vývoje — Tom',
            'dev_items' => [
                ['text' => 'Přestavba klíčových šablon a zrychlení e-shopu na mobilu.'],
                ['text' => 'Nastavení měření a propojení dat z reklamy s objednávkami.'],
                ['text' => 'Automatizace opakovaných úprav feedu a reportů.'],
            ],
            'results' => [
                ['value' => '+41 %', 'label' => 'tržby z online za 4 měsíce'],
                ['value' => '−28 %', 'label' => 'cena za objednávku'],
                ['value' => '1,9×', 'label' => 'rychlejší načtení na mobilu'],
            ],
            'quote' => '„Poprvé jsme věděli, které kampaně nám skutečně vydělávají — a web konečně nebrzdil."',
            'quote_author' => 'Majitel e-shopu *',
            'disclaimer' => '* Konkrétní jméno klienta a reálná čísla doplníme po jeho souhlasu. Rozložení a struktura zůstávají stejné.',
        ]);

        CaseStudy::updateOrCreate(['slug' => 'web-generujici-poptavky'], [
            'order_column' => 2,
            'case_study_category_id' => $sluzby,
            'title' => 'Web, který začal sám generovat poptávky',
            'is_featured' => true,
            'published' => true,
            'eyebrow' => 'Služby · Nový web + akvizice',
            'thumb_label' => 'Foto projektu — web',
            'excerpt' => 'Firma spoléhala na doporučení a neměla web, který by prodával. Navrhli jsme jasnou nabídku, postavili rychlý web a spustili cílené kampaně.',
            'headline_metric' => '3,2×',
            'tags' => [
                ['text' => 'Marketing: strategie, kampaně'],
                ['text' => 'Vývoj: nový web, měření'],
            ],
            'hero_headline' => 'Web, který začal sám',
            'hero_headline_accent' => 'generovat poptávky.',
            'hero_perex' => 'Firma spoléhala na doporučení a neměla web, který by prodával. Navrhli jsme jasnou nabídku, postavili rychlý web a spustili cílené kampaně.',
            'client' => 'Firma ve službách *',
            'industry' => 'B2B služby',
            'scope' => 'Nový web · Strategie · Kampaně',
            'duration' => '6 týdnů + retainer',
            'problem_title' => 'Výchozí stav',
            'problem_text' => 'Zakázky chodily výhradně z doporučení. Web byl vizitka bez nabídky, bez důvodu poptat a bez jakéhokoliv měření — nešlo tedy ani zjistit, kolik příležitostí uniká.',
            'problem_points' => [
                ['text' => 'Nabídka nebyla srozumitelná pro nikoho zvenčí.'],
                ['text' => 'Web neměl jediný jasný cíl ani cestu k poptávce.'],
                ['text' => 'Žádná akvizice mimo doporučení.'],
            ],
            'roles_title' => 'Jak jsme to táhli spolu',
            'roles_perex' => 'Marketing a vývoj neběžely po sobě, ale vedle sebe — jedno rozhodnutí ovlivnilo druhé.',
            'marketing_title' => 'Role marketingu — Pavel',
            'marketing_items' => [
                ['text' => 'Vyjasnění nabídky a sdělení pro konkrétní segment.'],
                ['text' => 'Spuštění cílených kampaní na relevantní poptávky.'],
                ['text' => 'Vyhodnocení kvality leadů, ne jen jejich počtu.'],
            ],
            'dev_title' => 'Role vývoje — Tom',
            'dev_items' => [
                ['text' => 'Nový rychlý web s jasnou cestou k poptávkovému formuláři.'],
                ['text' => 'Měření konverzí a napojení na reklamní systémy.'],
                ['text' => 'Automatické notifikace poptávek do e-mailu.'],
            ],
            'results' => [
                ['value' => '3,2×', 'label' => 'více poptávek za měsíc'],
                ['value' => '6 týd.', 'label' => 'od zadání ke spuštění'],
                ['value' => '−41 %', 'label' => 'cena za poptávku'],
            ],
            'quote' => '„Poprvé nám poptávky chodí i odjinud než z doporučení."',
            'quote_author' => 'Jednatel *',
            'disclaimer' => '* Konkrétní jméno klienta a reálná čísla doplníme po jeho souhlasu. Rozložení a struktura zůstávají stejné.',
        ]);

        CaseStudy::updateOrCreate(['slug' => 'b2b-vyrobce-redesign'], [
            'order_column' => 3,
            'case_study_category_id' => $redesign,
            'title' => 'Zastaralý web B2B výrobce',
            'is_featured' => false,
            'published' => true,
            'eyebrow' => 'Redesign · B2B',
            'thumb_label' => 'Foto — redesign',
            'excerpt' => 'Nová struktura, jasná nabídka a měřitelné poptávky z organiky.',
            'headline_metric' => '+62 %',
            'tags' => [
                ['text' => 'Marketing: obsah, SEO'],
                ['text' => 'Vývoj: nový web, měření'],
            ],
            'hero_headline' => 'Zastaralý web',
            'hero_headline_accent' => 'B2B výrobce.',
            'hero_perex' => 'Nová struktura, jasná nabídka a měřitelné poptávky z organického vyhledávání.',
            'client' => 'Výrobní firma *',
            'industry' => 'Průmysl & výroba',
            'scope' => 'Redesign · Obsah · SEO',
            'duration' => '8 týdnů',
            'problem_title' => 'Výchozí stav',
            'problem_text' => 'Web deset let beze změny, obsah psaný interně pro interní publikum. Poptávky chodily jen telefonicky a nikdo nevěděl, odkud se zákazník vzal.',
            'problem_points' => [
                ['text' => 'Struktura odpovídala organigramu firmy, ne potřebám zákazníka.'],
                ['text' => 'Nulová viditelnost v organickém vyhledávání.'],
                ['text' => 'Žádné měření poptávek.'],
            ],
            'roles_title' => 'Jak jsme to táhli spolu',
            'roles_perex' => 'Marketing a vývoj neběžely po sobě, ale vedle sebe — jedno rozhodnutí ovlivnilo druhé.',
            'marketing_title' => 'Role marketingu — Pavel',
            'marketing_items' => [
                ['text' => 'Analýza vyhledávání a přestavba struktury podle poptávky trhu.'],
                ['text' => 'Přepis obsahu do jazyka zákazníka.'],
                ['text' => 'Nastavení dlouhodobého obsahového plánu.'],
            ],
            'dev_title' => 'Role vývoje — Tom',
            'dev_items' => [
                ['text' => 'Nový web s rychlým načtením a čistou technickou SEO základnou.'],
                ['text' => 'Editovatelné produktové stránky bez nutnosti volat vývojáře.'],
                ['text' => 'Měření poptávek a telefonátů.'],
            ],
            'results' => [
                ['value' => '+62 %', 'label' => 'návštěvnost z organiky'],
                ['value' => '2,4×', 'label' => 'poptávek z webu'],
                ['value' => '8 týd.', 'label' => 'od zadání ke spuštění'],
            ],
            'quote' => '„Konečně nás najdou i lidé, kteří nás předtím neznali."',
            'quote_author' => 'Obchodní ředitel *',
            'disclaimer' => '* Konkrétní jméno klienta a reálná čísla doplníme po jeho souhlasu. Rozložení a struktura zůstávají stejné.',
        ]);

        CaseStudy::updateOrCreate(['slug' => 'sezonni-landing-page'], [
            'order_column' => 4,
            'case_study_category_id' => $novyWeb,
            'title' => 'Landing page pro sezónní kampaň',
            'is_featured' => false,
            'published' => true,
            'eyebrow' => 'Nový web · Kampaň',
            'thumb_label' => 'Foto — landing',
            'excerpt' => 'Jedno sdělení, jeden cíl — a měřitelný náběh rezervací.',
            'headline_metric' => '−34 %',
            'tags' => [
                ['text' => 'Marketing: kampaň, sdělení'],
                ['text' => 'Vývoj: landing page, měření'],
            ],
            'hero_headline' => 'Landing page pro',
            'hero_headline_accent' => 'sezónní kampaň.',
            'hero_perex' => 'Jedno sdělení, jeden cíl — a měřitelný náběh rezervací během dvou týdnů.',
            'client' => 'Sezónní provoz *',
            'industry' => 'Služby & rezervace',
            'scope' => 'Landing page · Kampaň · Měření',
            'duration' => '2 týdny',
            'problem_title' => 'Výchozí stav',
            'problem_text' => 'Kampaň vedla na běžnou podstránku plnou odkazů. Lidé přišli, rozptýlili se a odešli — cena za rezervaci rostla každý týden.',
            'problem_points' => [
                ['text' => 'Vstupní stránka bez jasného cíle.'],
                ['text' => 'Rostoucí cena za rezervaci.'],
                ['text' => 'Chybějící měření kroků rezervace.'],
            ],
            'roles_title' => 'Jak jsme to táhli spolu',
            'roles_perex' => 'Marketing a vývoj neběžely po sobě, ale vedle sebe — jedno rozhodnutí ovlivnilo druhé.',
            'marketing_title' => 'Role marketingu — Pavel',
            'marketing_items' => [
                ['text' => 'Zúžení sdělení na jednu jedinou nabídku.'],
                ['text' => 'Přestavba kampaně a cílení na sezónní poptávku.'],
                ['text' => 'Průběžné testování variant sdělení.'],
            ],
            'dev_title' => 'Role vývoje — Tom',
            'dev_items' => [
                ['text' => 'Rychlá landing page s jediným cílem — rezervací.'],
                ['text' => 'Měření jednotlivých kroků a odpadů.'],
                ['text' => 'Napojení rezervací na interní systém.'],
            ],
            'results' => [
                ['value' => '−34 %', 'label' => 'cena za rezervaci'],
                ['value' => '2 týd.', 'label' => 'od zadání ke spuštění'],
                ['value' => '+58 %', 'label' => 'dokončených rezervací'],
            ],
            'quote' => '„Jedna stránka udělala víc než celý web."',
            'quote_author' => 'Provozní ředitelka *',
            'disclaimer' => '* Konkrétní jméno klienta a reálná čísla doplníme po jeho souhlasu. Rozložení a struktura zůstávají stejné.',
        ]);
    }

    private function processSteps(): void
    {
        $steps = [
            ['number' => '01', 'title' => 'Pochopíme', 'text' => 'Co má web nebo kampaň skutečně přinášet a komu.'],
            ['number' => '02', 'title' => 'Navrhneme', 'text' => 'Nabídku, sdělení, strukturu webu a plán akvizice.'],
            ['number' => '03', 'title' => 'Postavíme', 'text' => 'Rychlý web nebo e-shop a nastavíme měření.'],
            ['number' => '04', 'title' => 'Spustíme', 'text' => 'Zapneme kampaně a přivedeme první relevantní lidi.'],
            ['number' => '05', 'title' => 'Zlepšujeme', 'text' => 'Podle dat ladíme web i kampaně a rosteme dál.', 'highlight' => true],
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
            'role_label' => 'Marketing & růst',
            'bio' => 'Strategie, výkonnostní reklama, kampaně, práce s nabídkou a hledání obchodních příležitostí. Přivádí na web správné lidi.',
            'tags' => [
                ['text' => 'Strategie'],
                ['text' => 'Výkonnostní reklama'],
                ['text' => 'Akvizice'],
            ],
            'external_url' => 'https://pavelvcelis.cz/',
        ]);

        Founder::updateOrCreate(['name' => 'Tom'], [
            'order_column' => 2,
            'role_label' => 'Vývoj & technologie',
            'bio' => '~8 let vývoje, Laravel, PHP, JS, Tailwind, Shoptet, integrace a automatizace. Promění nápady v reálný, rychlý web a e-shop.',
            'tags' => [
                ['text' => 'Laravel / PHP'],
                ['text' => 'E-commerce'],
                ['text' => 'Automatizace'],
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
<p>Správcem osobních údajů je TAVO. Kontaktovat nás můžete na e-mailu uvedeném v patičce webu.</p>
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
<p>Web používá technické cookies nutné pro jeho běh — udržení relace a ochranu formuláře před zneužitím. Tyto cookies nelze vypnout a nesbírají údaje pro marketing.</p>
<h2>Analytické cookies</h2>
<p>Pokud udělíte souhlas, načteme měřicí kód, který nám anonymně ukazuje, jak se web používá. Bez souhlasu se nenačte vůbec.</p>
<h2>Změna souhlasu</h2>
<p>Souhlas můžete kdykoliv odvolat vymazáním úložiště webu ve svém prohlížeči — banner se pak zobrazí znovu.</p>
HTML,
            'published' => true,
        ]);
    }
}
