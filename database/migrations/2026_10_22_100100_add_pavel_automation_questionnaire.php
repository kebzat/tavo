<?php

use App\Models\Questionnaire;
use Illuminate\Database\Migrations\Migration;

/**
 * Dotazník pro Pavla: jak vypadá jeho práce, co se opakuje a kde jsou
 * hranice pro AI asistenta. Podklad pro automatizaci marketingu v Taveo.
 * Rovnou sdílený, odkaz je v nástrojích → Dotazníky.
 */
return new class extends Migration
{
    private const TITLE = 'Co ti můžeme zautomatizovat';

    public function up(): void
    {
        if (Questionnaire::query()->where('title', self::TITLE)->exists()) {
            return;
        }

        Questionnaire::create([
            'title' => self::TITLE,
            'respondent' => 'Pavel Včeliš',
            'is_public' => true,
            'intro' => 'Pavle, chci nám oběma ubrat opakovanou práci. Na serveru by nám mohl běžet Claude jako asistent, kterému zadáme úkol u konkrétního klienta. Než něco postavím, potřebuju vědět, jak tvoje práce opravdu vypadá, co tě zdržuje a co by AI dělat nikdy neměla. Piš klidně heslovitě, přesnost je důležitější než sloh. Nemusíš to vyplnit najednou.',
            'sections' => [
                [
                    'title' => 'Tvůj běžný týden',
                    'questions' => [
                        ['key' => 'tyden', 'type' => 'long', 'label' => 'Jak vypadá tvůj běžný pracovní týden?', 'help' => 'Zhruba v hodinách nebo procentech: kampaně, komunikace s klienty, obsah, obchod, administrativa.'],
                        ['key' => 'opakovane', 'type' => 'long', 'label' => 'Co děláš každý týden nebo měsíc pořád stejně?', 'help' => 'Klidně i drobnosti: kontrola účtů, exporty, faktury, přeposílání podkladů.'],
                        ['key' => 'zdrzuje', 'type' => 'long', 'label' => 'Které tři věci tě nejvíc zdržují nebo otravují?'],
                        ['key' => 'nedelegovat', 'type' => 'long', 'label' => 'Co bys naopak nikomu nepředal, protože na tom stojí kvalita tvé práce?'],
                    ],
                ],
                [
                    'title' => 'Klienti a kampaně',
                    'questions' => [
                        ['key' => 'klienti', 'type' => 'long', 'label' => 'Kolik klientů teď vedeš a co pro ně děláš?', 'help' => 'Stačí seznam: klient, platforma, co přesně máš na starosti.'],
                        ['key' => 'nastroje', 'type' => 'multi', 'label' => 'Které nástroje používáš aspoň jednou týdně?', 'options' => [
                            'Meta Ads Manager', 'Meta Business Suite', 'Google Ads', 'Google Analytics 4', 'Google Tag Manager',
                            'Looker Studio', 'Sklik', 'TikTok Ads', 'Canva', 'CapCut', 'Google Tabulky nebo Excel', 'Notion',
                            'ClickUp', 'ChatGPT', 'Claude', 'Administrace Shoptetu', 'Administrace Upgates',
                            'E-mailingový nástroj (Ecomail, SmartEmailing, Mailchimp…)',
                        ]],
                        ['key' => 'nastroje_jine', 'type' => 'short', 'label' => 'Něco dalšího, co v seznamu chybí?'],
                        ['key' => 'kontrola_uctu', 'type' => 'long', 'label' => 'Když otevřeš reklamní účet klienta, na co se díváš jako první a kdy zbystříš?', 'help' => 'Tohle by se dalo naučit asistenta hlídat každé ráno. Čím konkrétněji, tím líp, třeba „frekvence nad 3“ nebo „cena za nákup o 30 % nad cílem“.'],
                        ['key' => 'novy_klient', 'type' => 'long', 'label' => 'Jak probíhá rozjezd nového klienta od domluvy po první kampaň?', 'help' => 'Krok po kroku, včetně přístupů a podkladů, které od klienta potřebuješ.'],
                        ['key' => 'reporty', 'type' => 'long', 'label' => 'Jak teď vzniká report pro klienta a kolik času ti zabere?', 'help' => 'Používáš report v našich nástrojích? Co mu chybí, aby šel poslat bez úprav?'],
                    ],
                ],
                [
                    'title' => 'Obsah a kreativa',
                    'questions' => [
                        ['key' => 'obsah_kdo', 'type' => 'long', 'label' => 'Kdo píše texty reklam a příspěvků a jak vzniká první verze?'],
                        ['key' => 'obsah_koncept', 'type' => 'multi', 'label' => 'Co bys rád dostával napůl hotové, abys to jen upravil?', 'options' => [
                            'Varianty textů do reklam', 'Nadpisy a úvodní věty', 'Plán příspěvků na měsíc', 'Scénáře krátkých videí',
                            'Texty na landing page', 'Odpovědi na komentáře a zprávy', 'Shrnutí pro klienta po schůzce', 'E-maily a newslettery',
                        ]],
                        ['key' => 'hlas_znacky', 'type' => 'long', 'label' => 'Máš u klientů sepsaný tón komunikace, zakázaná slova nebo brand manuál? Kde je najdu?'],
                    ],
                ],
                [
                    'title' => 'Obchod a nabídky',
                    'questions' => [
                        ['key' => 'nabidky', 'type' => 'long', 'label' => 'Jak teď vzniká nabídka nebo audit pro nového klienta a co v ní nesmí chybět?'],
                        ['key' => 'prvni_hovor', 'type' => 'long', 'label' => 'Na co se klienta ptáš na prvním hovoru?', 'help' => 'Z toho můžeme složit dotazník, který klient vyplní ještě před schůzkou.'],
                        ['key' => 'nabidky_pocet', 'type' => 'short', 'label' => 'Kolik nabídek měsíčně připravuješ a jak dlouho ti jedna trvá?'],
                    ],
                ],
                [
                    'title' => 'Spolupráce se mnou',
                    'questions' => [
                        ['key' => 'zadani_webu', 'type' => 'long', 'label' => 'Jak mi teď zadáváš úpravy webu nebo e-shopu a co se v zadání nejčastěji ztratí?'],
                        ['key' => 'sam_na_webu', 'type' => 'multi', 'label' => 'Které úpravy na webech klientů bys chtěl dělat sám, kdyby to šlo bezpečně a se schválením?', 'options' => [
                            'Texty a nadpisy', 'Bannery a obrázky', 'Nová landing page z hotových bloků', 'Akční nabídky a slevové kódy',
                            'UTM odkazy a měřicí kódy', 'Produktové popisky v e-shopu', 'Nic, raději to zadám Tomovi',
                        ]],
                        ['key' => 'schvalovani', 'type' => 'long', 'label' => 'Jak by mělo fungovat schvalování? Kdo má změnu vidět, než půjde ven?'],
                    ],
                ],
                [
                    'title' => 'Data klientů a hranice pro AI',
                    'intro' => 'Tady jde o to, co asistent smí vidět a co smí udělat sám. Podle toho nastavím přístupy.',
                    'questions' => [
                        ['key' => 'data_pristup', 'type' => 'multi', 'label' => 'K jakým datům klientů máš přístup?', 'options' => [
                            'Reklamní účty', 'Google Analytics a statistiky webu', 'Administrace e-shopu', 'Exporty objednávek nebo zákazníků',
                            'E-mailové databáze zákazníků', 'Sdílené disky s podklady', 'Přístupové údaje (hesla)',
                        ]],
                        ['key' => 'data_zakazane', 'type' => 'long', 'label' => 'Která data by AI neměla vidět vůbec?', 'help' => 'Typicky jména, e-maily a telefony zákazníků, objednávky nebo individuální ceny pro odběratele.'],
                        ['key' => 'smlouvy', 'type' => 'single', 'label' => 'Máš s klienty sepsáno, kam smíš jejich data dávat (zpracovatelská smlouva, NDA)?', 'options' => [
                            'Ano, se všemi', 'S některými', 'Ne', 'Nevím',
                        ]],
                        ['key' => 'bez_potvrzeni', 'type' => 'multi', 'label' => 'Co by asistent nikdy neměl udělat sám, bez tvého potvrzení?', 'options' => [
                            'Spustit nebo zastavit kampaň', 'Změnit rozpočet', 'Napsat nebo odpovědět klientovi', 'Zveřejnit příspěvek',
                            'Změnit cokoli na webu nebo v e-shopu', 'Smazat data', 'Poslat report',
                        ]],
                        ['key' => 'ai_dnes', 'type' => 'long', 'label' => 'Používáš teď AI? Na co a pod jakým účtem?', 'help' => 'Osobní nebo firemní účet, placená verze? Vkládal jsi do ní někdy data klientů?'],
                    ],
                ],
                [
                    'title' => 'Co nejdřív',
                    'questions' => [
                        ['key' => 'jedna_vec', 'type' => 'long', 'label' => 'Kdybych ti za měsíc mohl zautomatizovat jen jednu věc, co by to bylo?'],
                        ['key' => 'uspora', 'type' => 'short', 'label' => 'Kolik hodin týdně by ti to ušetřilo, odhadem?'],
                        ['key' => 'obavy', 'type' => 'long', 'label' => 'Čeho se u AI asistenta bojíš nebo co by tě od něj odradilo?'],
                        ['key' => 'neco_dalsiho', 'type' => 'long', 'label' => 'Na co jsem se nezeptal a měl jsem?'],
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        Questionnaire::query()->where('title', self::TITLE)->delete();
    }
};
