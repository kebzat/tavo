<?php

namespace App\Support\Marketing;

/**
 * Průvodce pro Pavla: co z jeho dotazníku (22. 10. 2026) je v nástrojích,
 * kde to najde a jak s tím pracovat. Stránka /pruvodce/{SLUG}.
 *
 * Obsah je tady, ne v šabloně, ať šablona jen vykresluje. Obrázky jsou
 * z ukázkových klientů (public/images/pruvodce), žádná skutečná data.
 * Když se nástroje změní, opravit text i obrázek.
 */
final class MarketingGuide
{
    /** Adresa má náhodnou část jako dotazník, stránka není v menu ani ve vyhledávačích. */
    public const SLUG = 'marketing-pavel-q7k3vd';

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $features = array_map(fn (array $feature): array => $feature + self::image($feature['image']), self::features());

        return [
            'title' => "Co je nového v\u{00A0}nástrojích Taveo",
            'eyebrow' => 'Aktualizováno 23. 10. 2026',
            'intro' => 'Podle tvého dotazníku jsem do nástrojů přidal, co šlo postavit hned. Nejdůležitější je agent, začni u něj. U každé další novinky je, na kterou tvoji odpověď reaguje, kde ji najdeš a jak s ní pracovat. Obrázky jsou z ukázkových klientů, ne z tvých.',
            'counts' => [
                ['value' => count($features) + 1, 'label' => 'novinek'],
                ['value' => count(self::partial()), 'label' => 'napůl'],
                ['value' => count(self::missing()), 'label' => 'zatím chybí'],
            ],
            'start' => [
                ['title' => 'Každé ráno', 'text' => 'Otevři Nástroje › Ranní přehled. V 7:15 ti navíc přijde e-mail, když se u klientů něco děje.'],
                ['title' => 'Tento týden', 'text' => 'Vyplň karty klientů, aspoň cíle, KPI, zdroj pravdy a tón. Bez nich agent jen napíše, co chybí.'],
                ['title' => 'Co nejdřív', 'text' => 'Nasdílej zbylé Meta účty Business Manageru Taveo. Napojení jsou zatím 2 klienti z 13.'],
            ],
            'agent' => self::agent(),
            'features' => $features,
            'partial' => self::partial(),
            'missing' => self::missing(),
            'safety' => [
                'Na Metu sahá jen ranní stažení v 6:00, a jen čte. Do reklamních účtů nic nezapisujeme, kampaně a rozpočty dál měníš ty.',
                'Čísla počítá systém. Agent je jen vykládá a u každého uvádí zdroj, období a čerstvost. Chybějící data nejsou nula.',
                'Klientovi nic neodejde bez tebe. Reporty jsou koncepty, úkoly od agenta návrhy, vyúčtování posíláš tlačítkem.',
                'Osobní údaje zákazníků do karty ani agentovi nedávej. Pracuje se souhrnnými čísly.',
            ],
            'asks' => [
                'Nasdílet zbylé Meta účty BM Taveo. Do nabídky v Reklamy › Přidat klienta se dostanou ráno, nebo hned po Nastavení reklam › Načíst účty znovu.',
                'Vyplnit karty klientů, u každého aspoň cíle, KPI, zdroj pravdy a tón komunikace.',
                'U leadových klientů domluvit, odkud a jak často dostaneme zpětnou vazbu k leadům.',
                'Změřit, kolik ti teď trvá jeden report a fakturace. Bez toho úsporu nevyhodnotíme.',
                'Sednout si se mnou nad hranice upozornění, ať odpovídají tomu, kdy ty zbystříš.',
            ],
        ];
    }

    /**
     * Obrázky jsou nafocené ve dvojnásobném rozlišení (Retina). Do <img>
     * jde poloviční rozměr, ať se stránka nepřeskládává a obrázek se neroztahuje.
     *
     * @return array{src: string, width: int, height: int}
     */
    private static function image(string $name): array
    {
        [$width, $height] = getimagesize(public_path('images/pruvodce/'.$name.'.webp')) ?: [0, 0];

        return ['src' => '/images/pruvodce/'.$name.'.webp', 'width' => intdiv($width, 2), 'height' => intdiv($height, 2)];
    }

    /** @return array<string, mixed> */
    private static function agent(): array
    {
        return [
            'id' => 'agent',
            'title' => 'Agent: asistent, který zná tvoje klienty',
            'intro' => 'Claude běží na našem serveru a vidí do nástrojů Taveo: karty klientů, čísla reklam spočítaná systémem, upozornění, úkoly, kvalitu leadů a kreativní testy. Poznámky ze schůzek si ukládá, takže kontext nemusíš pokaždé vkládat znovu. Řídí se pravidly z tvého dotazníku: fakta odděluje od hypotéz, u čísel píše zdroj a období a nic si nedomýšlí.',
            'asked' => 'Udržování kontextu a házení věcí do AI, protože nemám vše na jednom místě.',
            'question' => 3,
            'where' => ['Nástroje', 'Projekty', 'marketing'],
            'auto' => [
                'Každý všední den v 6:50 projde klienty a napíše Ranní přehled: co se změnilo, jestli jsou data spolehlivá, co vyžaduje pozornost a jaký krok navrhuje.',
                'Ke konceptům reportů doplní Co se stalo, Možné příčiny a Co doporučujeme z čísel za období reportu. Ty je jen upravíš.',
            ],
            'how' => [
                'Otevři Nástroje › Projekty › marketing. Konverzace se otevře rovnou, piš normálně česky.',
                'Řekni, o kterého klienta jde. Kartu a čísla si načte sám, nic nemusíš kopírovat.',
                'Na nové téma dej Nová konverzace. Ke starší se vrátíš v seznamu Konverzace, uspanou probudíš. Tu, kterou chceš mít pořád po ruce, si připni.',
                'Všechno, co navrhne, je návrh. Úkoly schvaluješ v Návrzích úkolů, texty si upravíš a pošleš sám.',
                'Úkol z ClickUpu mu předáš rovnou komentářem s @agent. Jak, je níž v Nově u agenta.',
            ],
            'asks' => [
                ['title' => 'Ze schůzky úkoly', 'prompt' => 'Tady je přepis dnešní schůzky s MIRNIX. Zapiš dohody a navrhni úkoly.', 'result' => 'Poznámku uloží ke klientovi, dohody zapíše do karty, úkoly připraví ke schválení. Napíše, co chybí doplnit.'],
                ['title' => 'Texty do reklam', 'prompt' => 'Napiš 5 variant textu a 5 hooků na Black Friday pro Svět Cejlonu. Drž se tónu z karty.', 'result' => 'Varianty podle karty klienta a historie kreativních testů, ať neopakuje, co nefungovalo.'],
                ['title' => 'Plán obsahu a scénáře', 'prompt' => 'Navrhni plán příspěvků na listopad a 3 scénáře reels do 20 vteřin.', 'result' => 'Plán podle sezony, marží a priorit z karty. Scénáře si doladíš.'],
                ['title' => 'Proč se čísla změnila', 'prompt' => 'Proč u Naturalek minulý týden vyskočila cena za nákup?', 'result' => 'Čísla se zdrojem a obdobím. Nejdřív zkontroluje měření, příčiny napíše jako hypotézy, chybějící data přizná.'],
                ['title' => 'Kontrola před kampaní', 'prompt' => 'Projdi kampaň na Black Friday podle kontroly před spuštěním: cíl, rozpočet, odkaz, texty jsou tady.', 'result' => 'Projde body checklistu, napíše, co sedí, co chybí a co ověřit neumí.'],
                ['title' => 'Shrnutí a odpovědi', 'prompt' => 'Připrav shrnutí po schůzce pro klienta. A navrhni odpovědi na tyhle komentáře pod postem.', 'result' => 'Hotový text k úpravě. Odesíláš ho ty, agent nikomu nic nepošle.'],
            ],
            'never' => [
                'Nespustí, nevypne ani nezmění kampaň či rozpočet. Do reklamních účtů přístup nemá.',
                'Nic nepošle klientovi a nic nezveřejní.',
                'Termíny, ceny a závazky si nedomýšlí, napíše „k doplnění“.',
                'Osobní údaje zákazníků do poznámek nezapisuje.',
            ],
            'example' => [
                'you' => 'Schůzka s MIRNIX 23. 10.: do Vánoc chtějí kampaň na brusle, od listopadu navýšit rozpočet na 30 000 Kč, fotky dodají příští týden. Zapiš to.',
                'agent_lead' => 'Uloženo u klienta MIRNIX:',
                'agent' => [
                    'Poznámka ze schůzky 23. 10.',
                    'Do karty rozhodnutí: rozpočet 30 000 Kč měsíčně od listopadu (zdroj: schůzka 23. 10.).',
                    'Dva návrhy úkolů ke schválení: Připravit kampaň na brusle, termín k doplnění. Dodat fotky, čeká se na klienta.',
                    'K doplnění: přesný den dodání fotek a kdo u klienta navýšení schválil.',
                ],
            ],
            'news' => self::agentNews(),
        ];
    }

    /**
     * Co agent umí nově (23. 10. 2026). Obrázky jsou z ukázkových projektů
     * nafocené lokálně, vlákno z ClickUpu je ilustrace v šabloně.
     *
     * @return list<array<string, mixed>>
     */
    private static function agentNews(): array
    {
        return [
            [
                'id' => 'agent-mozek-klienta',
                'label' => 'Mozek klienta',
                'title' => 'Mozek klienta: agent ví, co o klientovi víme',
                'intro' => 'Každý klient má vlastní archiv dokumentů a vlastní mozek. Agent ho dostane na začátku každé konverzace v projektu klienta, takže mu nemusíš pořád dokola vysvětlovat, co klient chce a co se domluvilo.',
                'where' => ['Nástroje', 'Projekty', 'klient', 'Mozek klienta'],
                'how' => [
                    'Nad konverzací je tlačítko Mozek klienta. Z boku ukáže, co agent o klientovi ví, návrhy ke schválení a archiv. Konverzace přitom běží dál.',
                    'Nahrát do archivu: vize, strategie, data, výsledky, marže, zápisy ze schůzek. Vyber druh a k jakému datu to platí. Tabulky stačí uložit, ze strategií a zápisů ať agent udělá poznatky.',
                    'Dokument můžeš poslat i rovnou agentovi do konverzace. Uloží ho do archivu sám a řekne ti to.',
                    'Co se agent v konverzaci dozví (třeba „tohle klient nechce“), navrhne jako poznatek. Platit začne, až ho schválíš.',
                    'Totéž najdeš i u klienta (Klienti › klient) v záložkách Archiv a Mozek klienta.',
                ],
                'images' => [
                    self::image('agent-mozek-klienta') + [
                        'alt' => 'Panel Mozek klienta vedle konverzace: návrh ke schválení, co agent o klientovi ví a dokumenty v archivu',
                        'caption' => 'Tlačítko Mozek klienta nad konverzací: návrhy, poznatky a archiv na jednom místě.',
                    ],
                ],
            ],
            [
                'id' => 'agent-clickup',
                'label' => 'Agent v ClickUpu',
                'title' => 'Agent v ClickUpu',
                'intro' => 'Úkol nemusíš přepisovat do nástrojů. Napiš k němu v ClickUpu komentář s @agent. Agent si přečte název, popis i ostatní komentáře úkolu, odpracuje to na serveru a odpoví do stejného vlákna.',
                'where' => ['ClickUp', 'úkol', 'komentář s @agent'],
                'how' => [
                    'V komentáři označ uživatele Agent, nabídne se v našeptávači. Stačí i napsat @agent, /agent nebo !agent.',
                    'Hned odpoví, že se do toho pouští. Až skončí, označí tě v odpovědi, takže ti přijde upozornění.',
                    'Ve vlákně pak piš jako v chatu, už bez @agent. Navazuje na stejnou konverzaci.',
                    'U klienta s propojeným seznamem v ClickUpu pracuje v jeho projektu, jinak v projektu marketing. Konverzaci najdeš i v Nástroje › Projekty, odkaz je na konci odpovědi.',
                    'Spustit ho můžete jen ty a Tom. V ClickUpu jen čte a přidává odpovědi, nic nemaže. Odpověď ale uvidí každý, kdo vidí úkol.',
                ],
                'thread' => [
                    'task' => 'MIRNIX · Kampaň na brusle',
                    'messages' => [
                        ['author' => 'Pavel', 'agent' => false, 'lines' => ['@agent napiš 3 krátké texty do reklamy na brusle. Drž se tónu z karty.']],
                        ['author' => 'Agent', 'agent' => true, 'lines' => ['Agent: pouštím se do toho (projekt marketing). Až budu hotový, napíšu sem.']],
                        ['author' => 'Agent', 'agent' => true, 'lines' => [
                            'Agent: hotovo.',
                            '1. Zima začíná na ledě. Vyber si brusle, které sednou napoprvé.',
                            '2. Rekreační, hokejové i dětské brusle na jednom místě.',
                            '3. Do Vánoc to stihneš. Brusle od [cena k doplnění].',
                            'Cenu a termín akce karta nemá, nechal jsem je k doplnění. @Pavel',
                        ]],
                        ['author' => 'Pavel', 'agent' => false, 'lines' => ['Dvojku zkrať pod 60 znaků a přidej variantu pro děti.']],
                    ],
                ],
            ],
            [
                'id' => 'agent-hotova-prace',
                'label' => 'Hotová práce',
                'title' => 'Hotová práce: zkontroluješ a zveřejníš sám',
                'intro' => 'Když agent upraví web Taveo, sám ho na web nepošle. Úprava se objeví v kartě Hotová práce s popisem a snímky, které si nafotil ve svém prohlížeči. Kód číst nemusíš, kontroluješ podle snímků.',
                'where' => ['Nástroje', 'Projekty', 'taveo'],
                'how' => [
                    'Zkontrolovat otevře popis, co je jinak, a snímky z počítače i telefonu. Kliknutím je zvětšíš.',
                    'Zveřejnit jde až se zelenými testy (Testy prošly). Pak to běží samo: testy, nasazení, na webu. Obvykle do 2 minut. Zveřejnit můžeš ty i Tom.',
                    'Když něco nesedí, napiš agentovi do konverzace pod kartou, co upravit. Tlačítko Vrátit u poslední zveřejněné úpravy vrátí web zpátky.',
                    'Úpravu s upozorněním „Mění nastavení serveru“ nech Tomovi.',
                    'U projektů pro Shoptet a Upgates se karta jmenuje Hotové podklady. Kódy a texty mají tlačítko Zkopírovat a popis, kam je v administraci vložit. Po vložení klikni Vloženo, hotovo.',
                ],
                'images' => [
                    self::image('agent-hotova-prace') + [
                        'alt' => 'Karta Hotová práce: úprava se nasazuje, další čeká na kontrolu se zelenými testy, starší jde vrátit',
                        'caption' => 'Jedna úprava se právě nasazuje, druhá čeká na kontrolu, starší jde vrátit.',
                    ],
                    self::image('agent-zkontrolovat') + [
                        'alt' => 'Panel Zkontrolovat s popisem úpravy a snímky z počítače a telefonu',
                        'caption' => 'Po kliknutí na Zkontrolovat: popis a snímky z prohlížeče agenta.',
                    ],
                    self::image('agent-podklady') + [
                        'alt' => 'Hotové podklady pro Shoptet: kódy s tlačítkem Zkopírovat a popisem, kam je vložit',
                        'caption' => 'U Shoptetu a Upgates: kód ke zkopírování a kam ho vložit.',
                    ],
                ],
            ],
            [
                'id' => 'agent-predplatne',
                'label' => 'Předplatné Claude',
                'title' => 'Kolik zbývá z předplatného',
                'intro' => 'Agent v nástrojích, agent v ClickUpu i Tomova práce v Claude Code jedou na jednom předplatném Claude. Kolik je z něj vyčerpané, vidíš uprostřed hlavičky nástrojů.',
                'where' => ['Nástroje', 'hlavička nahoře'],
                'how' => [
                    '5 h je relace, obnovuje se po pěti hodinách. 7 dní je týdenní limit.',
                    'Pruh je zelený, od 75 % oranžový a od 90 % červený.',
                    'Po kliknutí uvidíš, kdy se který limit obnoví. Ryska ukazuje, kolik z okna už uběhlo. Když je pruh za ní, limit dojde dřív.',
                    'Od 90 % se agent z ClickUpu nespustí a odpoví, kdy se limit obnoví. Zbytek zůstane na rozdělanou práci v nástrojích.',
                ],
                'images' => [
                    self::image('agent-predplatne') + [
                        'alt' => 'Ukazatel předplatného Claude v hlavičce nástrojů, rozbalený: relace, týden a kdy se obnoví',
                        'caption' => 'Rozbalený ukazatel: relace, týden a kdy se obnoví.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function features(): array
    {
        return [
            [
                'id' => 'ranni-prehled',
                'title' => 'Ranní přehled',
                'asked' => 'Ranní přehled priorit a upozornění na chybějící podklady či schválení.',
                'question' => 2,
                'where' => ['Nástroje', 'Ranní přehled'],
                'how' => [
                    'Ve všední dny v 6:50 agent projde klienty, kteří mají napojené reklamy nebo vyplněnou kartu, a ke každému napíše, co se změnilo, jestli jsou data spolehlivá, co hoří a jaký krok navrhuje.',
                    'Nahoře jsou tvoje priority: úkoly s termínem do dvou dnů nebo s vysokou prioritou. Vedle návrhy úkolů čekající na schválení.',
                    'Šipkami se vrátíš na předchozí dny. Tlačítka Reklamy a Karta klienta vedou rovnou ke klientovi.',
                ],
                'image' => 'ranni',
                'alt' => 'Ranní přehled: priority, návrhy úkolů a přehled od agenta u ukázkového klienta',
            ],
            [
                'id' => 'upozorneni',
                'title' => 'Upozornění a ranní e-mail',
                'asked' => 'Nečekané výkyvy, propady, blokace… automatická notifikace, denní kontrola by velmi pomohla.',
                'question' => 3,
                'where' => ['Reklamy', 'Upozornění'],
                'how' => [
                    'Každé ráno se čísla zkontrolují. Hlídáme útratu bez konverzí, nulovou útratu, čerpání rozpočtu, cenu za konverzi a ROAS proti cíli, únavu kreativ, skok útraty, zamítnuté reklamy a stav účtu.',
                    'Zvlášť hlídáme měření: nákupy bez hodnoty, víc nákupů než pokladen a nesoulad reklam s GA4. Taky nefunkční cílovou stránku a chybějící pixel.',
                    'U každého upozornění je napsané, co udělat. Přes tři tečky ho přepneš na Řeší se nebo Vyřešené. Co přestane platit, zavře se samo.',
                    'E-mail chodí ve všední dny v 7:15, jen když je co hlásit. Nová upozornění jsou oddělená od trvajících, ať tě stejná věc nespamuje. Komu chodí, nastavíš v Reklamy › Nastavení reklam.',
                ],
                'image' => 'upozorneni',
                'alt' => 'Seznam upozornění se závažností, doporučením a stavem',
            ],
            [
                'id' => 'upozorneni-klient',
                'title' => 'Upozornění na míru klientovi',
                'asked' => 'Pro každého klienta nastavit období, toleranci, minimum dat a naléhavost. Nechci opakovaný spam.',
                'question' => 8,
                'where' => ['Reklamy', 'Přehled', 'klient', 'Cíle a paušál', 'Upozornění pro tohoto klienta'],
                'how' => [
                    'Prázdné pole znamená, že platí společné nastavení. Vyplň jen to, co má být u klienta jinak.',
                    'Naléhavost Vysoká povýší Pozor na Hoří (velký rozpočet, akce s termínem). Nízká ho sníží na Tip (malý účet, awareness).',
                    'Pravidlo, které u klienta nedává smysl, zaškrtni jako ztlumené. Výpadek stahování a stav účtu ztlumit nejde.',
                    'Konkrétní hranice zatím drží výchozí hodnoty. Projdeme je spolu.',
                ],
                'image' => 'upozorneni-klient',
                'alt' => 'Formulář upozornění pro jednoho klienta: naléhavost, ztlumená pravidla, vlastní prahy',
            ],
            [
                'id' => 'karta',
                'title' => 'Karta klienta (mozek)',
                'asked' => 'MOZEK, který zná klienty a mohu mu postupně dávat nové vědomosti, data z meetingů, maily a informace.',
                'question' => 25,
                'where' => ['Klienti', 'Klienti', 'klient', 'Karta klienta'],
                'how' => [
                    'Cíle, KPI, rozsah, rozpočet, zdroj pravdy, omezení, schvalování, tón a zakázaná slova, sezona. Agent ji čte při každém přehledu i psaní textů. Co v ní není, si nedomýšlí.',
                    'Rozhodnutí a dohody mají datum, zdroj a „platí do“. Agent tak pozná, co je zastaralé, a upozorní na rozpory.',
                    'Do Cílových stránek dej až 5 adres z kampaní. Každé ráno se zkontroluje, že fungují a mají pixel.',
                    'Na kartu se dostaneš i z Ranního přehledu tlačítkem Karta klienta. Osobní údaje zákazníků sem nepatří.',
                ],
                'image' => 'karta',
                'alt' => 'Karta klienta s cíli, KPI, rozpočtem a tónem komunikace',
            ],
            [
                'id' => 'navrhy-ukolu',
                'title' => 'Ze schůzek a zpráv návrhy úkolů',
                'asked' => 'Převádět komunikaci z meetingů, mailů, WhatsAppu a Slacku do návrhů úkolů. Úkoly a termíny nemá domýšlet.',
                'question' => 2,
                'where' => ['Nástroje', 'Projekty', 'marketing'],
                'how' => [
                    'Do agenta vlož přepis schůzky, e-mail nebo zprávu a napiš, o kterého klienta jde.',
                    'Agent uloží poznámku, dohody zapíše do karty klienta a úkoly připraví jako návrhy s termínem, prioritou, tím, na co se čeká, a zdrojem. Co nezaznělo, označí „k doplnění“.',
                    'Návrhy najdeš u klienta v záložce Návrhy úkolů (a v Ranním přehledu). Schválit, Upravit, nebo Zahodit, jde to i hromadně. Klient je nevidí, dokud je neschválíš.',
                ],
                'image' => 'navrhy',
                'alt' => 'Návrhy úkolů od agenta s prioritou, termínem a tím, na co se čeká',
            ],
            [
                'id' => 'kvalita-leadu',
                'title' => 'Kvalita leadů',
                'asked' => 'Sledovat relevanci, kontaktovatelnost, obchody a hodnotu. Bez zpětné vazby neoznačovat levný lead za úspěch.',
                'question' => 8,
                'where' => ['Klienti', 'Klienti', 'klient', 'Kvalita leadů'],
                'how' => [
                    'Tlačítkem Zapsat zpětnou vazbu zapíšeš za období: kolik leadů klient eviduje, kolik se ozvalo, kolik bylo relevantních, obchody, jejich hodnotu a odkud to víš.',
                    'Když leadový klient má za 30 dní aspoň 5 leadů a poslední zpětná vazba je starší než 3 týdny, přijde upozornění.',
                    'Agent bez zpětné vazby levný lead za úspěch neoznačí.',
                ],
                'image' => 'kvalita-leadu',
                'alt' => 'Tabulka kvality leadů po měsících: leady, ozvali se, relevantní, obchody, hodnota',
            ],
            [
                'id' => 'kreativni-testy',
                'title' => 'Paměť kreativních testů',
                'asked' => 'Co jsme testovali, proč, kdy, s jakým rozpočtem a výsledkem. Rozlišovat neúspěšný test od testu s nedostatkem dat.',
                'question' => 11,
                'where' => ['Klienti', 'Klienti', 'klient', 'Kreativní testy'],
                'how' => [
                    'Před spuštěním: Zapsat test, hypotéza, argument, hook, formát, cílová stránka a rozpočet.',
                    'Po testu doplň výsledek, verdikt (Běží, Funguje, Nefunguje, Málo dat) a poučení pro další zadání.',
                    'Agent historii testů čte, když navrhuje texty, ať neopakuje nápady, které nefungovaly.',
                ],
                'image' => 'kreativni-testy',
                'alt' => 'Kreativní testy s verdikty Funguje, Málo dat a Běží',
            ],
            [
                'id' => 'prehled-klienta',
                'title' => 'Přehled klienta podle typu spolupráce',
                'asked' => 'Přizpůsobit dashboard podle typu spolupráce: e-shop, B2B leady, boosting obsahu, kombinace.',
                'question' => 25,
                'where' => ['Reklamy', 'Přehled', 'klient'],
                'how' => [
                    'V Cíle a paušál vyber hlavní cíl: Nákupy (e-shop), Poptávky a kontakty, nebo nově Dosah a interakce pro boosting a awareness.',
                    'Podle cíle se mění dlaždice i pravidla. U boostingu uvidíš dosah, frekvenci, CPM a interakce a cenu za konverzi nehlídáme.',
                    'Které dlaždice a sekce se ukazují, vybereš v Cíle a paušál › Co ukazovat. Nahoře přepínáš období.',
                ],
                'image' => 'reklamy-klient',
                'alt' => 'Přehled reklam ukázkového e-shopu: útrata, nákupy, cena za nákup, ROAS',
            ],
            [
                'id' => 'reporty',
                'title' => 'Reporty: fakta, příčiny, doporučení',
                'asked' => 'Uvádět zdroj, aktuálnost a atribuci. Chybějící data neprezentovat jako nulu. Oddělit fakta, možné příčiny a doporučení.',
                'question' => 10,
                'where' => ['Reklamy', 'Reporty'],
                'how' => [
                    'Koncept týdenního reportu vznikne v pondělí ráno, měsíčního 1. v měsíci. Zapínáš je u klienta v Cíle a paušál › Reporty.',
                    'V 6:50 agent ke konceptu napíše Co se stalo (jen z čísel), Možné příčiny (jako hypotézy) a Co doporučujeme.',
                    'Ty to upravíš, připíšeš komentář a tlačítkem Odeslat klientovi pošleš. Klient pod tím vidí i „Odkud čísla jsou“. Nákupy z Mety a Googlu se nesčítají.',
                ],
                'image' => 'report-fakta',
                'alt' => 'Koncept reportu se sekcemi Co se stalo, Možné příčiny a Co doporučujeme',
            ],
            [
                'id' => 'checklisty',
                'title' => 'Onboarding a kontrola před kampaní',
                'asked' => 'Jednotný onboardingový checklist a kontrolní seznam před spuštěním kampaně.',
                'question' => 9,
                'where' => ['Klienti', 'Checklisty', 'Šablony'],
                'how' => [
                    'Dvě šablony: Onboarding marketingového klienta (22 bodů) a Kontrola před spuštěním kampaně (12 bodů: cíl, rozpočet, termíny, lokalita, odkazy, UTM, texty, platnost nabídky, landing page).',
                    'U šablony klikni Vytvořit z šablony a vyber klienta.',
                    'U každého bodu veď stav. Do interní poznámky napiš, kdo za něj odpovídá a co chybí. Kampaň dál nasazuješ ručně, checklist je kontrola.',
                ],
                'image' => 'checklisty',
                'alt' => 'Šablony checklistů: Kontrola před spuštěním kampaně a Onboarding marketingového klienta',
            ],
            [
                'id' => 'vyuctovani',
                'title' => 'Vyúčtování hodin podle klienta',
                'asked' => 'Fakturace mi zabere až 4 hodiny. Každý klient chce vyúčtování jinak, na jiné maily, jiným způsobem.',
                'question' => 28,
                'where' => ['Klienti', 'Fakturace', 'klient', 'Podklad k vyúčtování'],
                'how' => [
                    'Jednou u klienta nastav v Kartě klienta › Vyúčtování hodin: kam (do faktury, e-mailem, nechce), jak podrobně, komu a poznámku.',
                    'Na konci měsíce ve Fakturaci klikni u klienta na Podklad k vyúčtování. Text se složí z odpracovaných hodin podle toho nastavení.',
                    'Text zkopíruješ do faktury, nebo vyplníš Komu a odešleš e-mailem. Popisy zápisů času zůstanou interní.',
                ],
                'image' => 'podklad',
                'alt' => 'Okno Vyúčtování hodin s předvyplněným textem pro klienta',
            ],
        ];
    }

    /** @return list<array{title: string, text: string}> */
    private static function partial(): array
    {
        return [
            ['title' => 'Web a Google v přehledu', 'text' => 'GA4 a Google Ads jsou naprogramované. Na serveru chybí přístupové údaje, to je na mně.'],
            ['title' => 'Skutečné objednávky jako zdroj pravdy', 'text' => 'V kartě je pole „Zdroj pravdy pro čísla“. Objednávky, storna a DPH ale bez dat z e-shopu nespočítáme.'],
            ['title' => 'Texty napůl hotové', 'text' => 'Varianty textů, scénáře, plán příspěvků nebo shrnutí po schůzce ti agent v projektu marketing napíše na požádání, z karty a kreativních testů. Sám je zatím nechystá.'],
            ['title' => 'Tón komunikace a brand manuál', 'text' => 'Pole v kartě je připravené, chybí ho u klientů vyplnit.'],
        ];
    }

    /** @return list<array{title: string, text: string}> */
    private static function missing(): array
    {
        return [
            ['title' => 'Sklik', 'text' => 'Potřebuje API token a společné vyzkoušení.'],
            ['title' => 'Objednávky ze Shoptetu a Upgates', 'text' => 'Každý e-shop potřebuje vlastní přístup k API.'],
            ['title' => 'Vyprodané produkty a končící akce', 'text' => 'Bez dat z e-shopu nejdou hlídat.'],
            ['title' => 'Kontrola UTM', 'text' => 'Zatím nepostavené.'],
            ['title' => 'Příspěvky čekající na boost', 'text' => 'Meta k tomu chce další oprávnění a víc dotazů. Kvůli riziku banu jsem do toho zatím nešel.'],
            ['title' => 'Kvalita leadů z CRM automaticky', 'text' => 'Zatím se zapisuje ručně. Napojím, až bude jasné, odkud ji klienti dávají.'],
            ['title' => 'Návrh nabídky pro nového klienta', 'text' => 'Zatím nepostavené. Cenu a závazky potvrzuješ vždycky ty.'],
            ['title' => 'TikTok a Clarity', 'text' => 'Zatím nepostavené.'],
            ['title' => 'Měření ušetřeného času', 'text' => 'Nejdřív potřebuju tvoje současné časy, viz níž.'],
        ];
    }
}
