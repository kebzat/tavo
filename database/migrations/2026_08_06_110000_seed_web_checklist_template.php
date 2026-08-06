<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Univerzální technický checklist webu se službou a kalkulačkou.
 *
 * Podklad, ze kterého klonujeme checklist pro každého klienta. Vznikl ze
 * zadání pro ATRIAMO, ale záměrně neobsahuje nic oborového, aby posloužil
 * i u další zakázky stejného typu (jedna služba, kalkulačka, poptávka).
 *
 * Pět kategorií je rozcestník, sekce uvnitř nich jsou podnadpisy v tabulce.
 *
 * Chová se jako `add()` u settings migrací: šablonu založí, jen když ještě
 * žádná neexistuje. Jakmile ji začneme upravovat v administraci, migrace
 * se jí nedotkne. Rozšiřovat body po spuštění patří do administrace, ne sem.
 */
return new class extends Migration
{
    private const NAME = 'Technický checklist webu se službou a kalkulačkou';

    public function up(): void
    {
        if (DB::table('checklists')->where('is_template', true)->exists()) {
            return;
        }

        $now = now();

        $checklistId = DB::table('checklists')->insertGetId([
            'client_id' => null,
            'is_template' => true,
            'is_public' => false,
            'name' => self::NAME,
            'intro' => 'Co má web se službou a kalkulačkou umět z pohledu měření, vyhodnocování a výkonnostního marketingu. '
                .'Body označené jako nutnost patří vyřešit dřív, než se pustí reklamy. Zbytek podle kapacity.',
            'public_token' => Str::random(40),
            'order_column' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($this->categories() as $categoryOrder => $category) {
            $categoryId = DB::table('checklist_categories')->insertGetId([
                'checklist_id' => $checklistId,
                'title' => $category['title'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'order_column' => $categoryOrder + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($category['sections'] as $sectionOrder => $section) {
                $sectionId = DB::table('checklist_sections')->insertGetId([
                    'checklist_category_id' => $categoryId,
                    'title' => $section['title'],
                    'description' => $section['description'],
                    'order_column' => $sectionOrder + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $rows = [];

                foreach ($section['items'] as $itemOrder => $item) {
                    $rows[] = [
                        'checklist_section_id' => $sectionId,
                        'checklist_id' => $checklistId,
                        'title' => $item[0],
                        'description' => $item[1],
                        'internal_note' => null,
                        'priority' => $item[2],
                        'status' => 'todo',
                        'order_column' => $itemOrder + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('checklist_items')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        // Cizí klíče smažou kategorie, sekce i položky kaskádou.
        DB::table('checklists')->where('is_template', true)->where('name', self::NAME)->delete();
    }

    /**
     * Položka = [název, vysvětlivka, priorita].
     *
     * @return array<int, array{title: string, slug: string, description: string, sections: array<int, array{title: string, description: string, items: array<int, array{0: string, 1: string, 2: string}>}>}>
     */
    private function categories(): array
    {
        return [
            [
                'title' => 'Web a technologie',
                'slug' => 'web-a-technologie',
                'description' => 'Z čeho web postavit, jak poskládat adresy a jak ho udržet rychlý.',
                'sections' => [
                    [
                        'title' => 'Technologie a řešení',
                        'description' => 'Rozhodnutí, která se později mění nejhůř.',
                        'items' => [
                            ['Obsah se vykresluje na serveru', 'Čistě klientská aplikace pošle vyhledávači prázdné HTML a obsah dogeneruje až JavaScript. Pro aplikaci za přihlášením je to v pořádku, pro web, který má být vidět, ne. Vhodné je serverové vykreslení nebo statické generování stránek.', 'must'],
                            ['Web i kalkulačka běží na jedné doméně', 'Kalkulačka v iframu nebo na cizí subdoméně rozbije měření. Ztratí se zdroj návštěvy a cookies se rozpadnou na dva weby.', 'must'],
                            ['HTTPS a jedna kanonická varianta domény', 'Rozhodněte, jestli web žije na www nebo bez www, a druhou variantu trvale přesměrujte. Jinak se návštěvnost i odkazy rozdělí na dvě adresy.', 'must'],
                            ['Texty, obrázky a reference jde měnit bez vývojáře', 'Administrace se vyplatí při každé úpravě ceníku, fotky realizace nebo textu, na který míří kampaň.', 'must'],
                            ['Leady a rozpracované kalkulace se ukládají do databáze', 'E-mailová schránka není databáze. Nedá se z ní filtrovat, exportovat ani spočítat konverzní poměr.', 'must'],
                            ['Testovací prostředí oddělené od ostrého', 'Zamčené heslem a s noindex, ať se rozpracovaná verze nedostane do vyhledávače.', 'should'],
                            ['Kód ve verzování a nasazení jedním krokem', 'Bez toho se nedá rychle vrátit zpět, když nová verze něco rozbije.', 'should'],
                            ['Repozitář, doména i hosting patří klientovi', 'Ne dodavateli. Při změně dodavatele se předání zasekne nejčastěji přesně tady.', 'must'],
                        ],
                    ],
                    [
                        'title' => 'Struktura stránky a adresy',
                        'description' => 'Bez vlastních adres se nedá poznat, kam zákazník došel a kde odpadl.',
                        'items' => [
                            ['Každý krok kalkulačky má vlastní adresu', 'Například /kalkulacka/typ-konstrukce, /kalkulacka/rozmery, /kalkulacka/kontakt. Jinak víte jen to, že někdo kalkulačku otevřel, ale ne kde skončil.', 'must'],
                            ['Děkovací stránka má vlastní adresu', 'Například /dekujeme. Konverze se pak dá měřit jako zobrazení stránky, což je nejspolehlivější způsob a funguje i tam, kde selže data layer.', 'must'],
                            ['Sekce, na které míří reklama, mají vlastní adresu nebo aspoň kotvu', 'Reklama na zimní zahrady má vést na zimní zahrady, ne na začátek dlouhé jednostránky.', 'should'],
                            ['Adresy jsou čitelné a bez identifikátorů', '/kalkulacka/rozmery místo /step?id=3. Snáz se čtou v reportu i v reklamě.', 'should'],
                            ['Právní stránky jsou na webu', 'Zásady zpracování osobních údajů, zásady cookies a obchodní podmínky.', 'must'],
                            ['Chybová stránka 404 nabízí cestu zpět', 'Ideálně rovnou odkaz na kalkulačku.', 'should'],
                            ['Kotvy fungují i při přímém otevření odkazu', 'Odkaz z reklamy nebo z e-mailu musí zákazníka posunout na správné místo i po čerstvém načtení stránky.', 'should'],
                        ],
                    ],
                    [
                        'title' => 'Rychlost a Core Web Vitals',
                        'description' => 'Za kliknutí na pomalou stránku se platí stejně jako za rychlou, jenom z něj nic nebude.',
                        'items' => [
                            ['LCP pod 2,5 sekundy na mobilu', 'Doba, než se vykreslí největší prvek na první obrazovce. Měřte na telefonu a na mobilních datech, ne na kancelářské wi-fi.', 'must'],
                            ['INP pod 200 ms', 'Jak rychle stránka odpoví na klik. Týká se hlavně kalkulačky, kde zákazník kliká často.', 'must'],
                            ['CLS pod 0,1', 'Poskakování obsahu při načítání. Nejčastější příčinou jsou obrázky bez uvedených rozměrů.', 'must'],
                            ['Obrázky ve WebP nebo AVIF, s rozměry a odloženým načítáním', 'Fotky realizací bývají největší položkou přenosu celé stránky.', 'must'],
                            ['Písma hostovaná na vlastní doméně', 'Google Fonts z CDN posílá IP adresu návštěvníka do USA, což je problém z pohledu GDPR, a přidává spojení navíc.', 'should'],
                            ['Kalkulačka se načte, i když marketingové skripty selžou', 'Výpadek GTM nesmí položit hlavní funkci webu.', 'must'],
                            ['Měřicí skripty se načítají asynchronně a až po souhlasu', 'Blokující skript třetí strany dokáže sám o sobě zdvojnásobit dobu načtení.', 'must'],
                            ['Nastavená cache pro statické soubory', 'Opakovaná návštěva pak nestahuje totéž znovu.', 'should'],
                            ['Rychlost se měří pravidelně, ne jen při spuštění', 'Web zpomaluje postupně, jak přibývají skripty, fotky a další nástroje.', 'should'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'SEO a obsah',
                'slug' => 'seo-a-obsah',
                'description' => 'Aby web našli lidé, vyhledávače i jazykové modely a aby jim dal důvod věřit.',
                'sections' => [
                    [
                        'title' => 'Indexace a AI vyhledávače',
                        'description' => 'Část zákazníků si dnes dodavatele hledá přes jazykové modely, ne jen přes Google.',
                        'items' => [
                            ['Obsah je v HTML odpovědi ze serveru', 'Co není v odpovědi, s tím vyhledávač ani jazykový model nepočítá.', 'must'],
                            ['Každá adresa má vlastní title a popisek', 'Duplicitní title napříč webem je nejčastější chyba jednostránek rozdělených na kroky.', 'must'],
                            ['Jeden nadpis H1 na stránku', 'A pod ním logická struktura H2 a H3.', 'should'],
                            ['robots.txt a sitemap.xml', 'Sitemapa jen s adresami, které mají být ve vyhledávání.', 'must'],
                            ['Kroky kalkulačky a děkovací stránka jsou z indexace vyloučené', 'Do vyhledávání patří vstupní stránka, ne mezikrok formuláře.', 'must'],
                            ['Kanonické adresy', 'Zabrání tomu, aby se tatáž stránka počítala vícekrát kvůli parametrům v adrese.', 'should'],
                            ['Strukturovaná data', 'Organization nebo LocalBusiness s adresou a IČO, Service u služby, FAQPage u častých dotazů. Vyhledávače i jazykové modely si odtud berou fakta o firmě.', 'should'],
                            ['Obrázek pro sdílení a favicona', 'Bez Open Graph obrázku vypadá odkaz na Facebooku i v Messengeru jako prázdný šedý obdélník.', 'must'],
                            ['Stránka odpovídá na konkrétní otázky', 'Ceny, materiály, průběh montáže, termíny, záruka. Jazykové modely citují stránky, které mají odpověď přímo v textu.', 'should'],
                            ['Ověřený přístup do Google Search Console', 'Jediné místo, kde uvidíte, na co se lidé ptají, než na web kliknou.', 'must'],
                        ],
                    ],
                    [
                        'title' => 'Obsah a důvěryhodnost',
                        'description' => 'Druhá fáze. Řeší se, až bude technická část hotová, ale na výsledek kampaní má vliv stejný jako cílení.',
                        'items' => [
                            ['Fotky skutečných realizací', 'U řemeslné práce rozhoduje důvěra. Generické vizuály z AI ji spíš berou, než přidávají.', 'should'],
                            ['Je vidět, kdo za firmou stojí', 'U nové značky je poctivější napsat, kdo z lidí kolem má za sebou jaké realizace, než tvrdit, že firma jich má desítky.', 'should'],
                            ['Popsaný proces od poptávky po montáž', 'Zákazník, který ví, co ho čeká, se rozhoduje rychleji.', 'should'],
                            ['Cena nebo aspoň cenové rozpětí', 'Poptávka od člověka bez představy o ceně se uzavírá špatně a zbytečně vytěžuje obchodníka.', 'should'],
                            ['Reference se jménem, lokalitou a fotkou', 'Anonymní citace bez jména nikoho nepřesvědčí.', 'should'],
                            ['Odpovědi na obvyklé námitky', 'Záruka, termíny, stavební povolení, co když se to nevejde.', 'should'],
                            ['Kontakt s adresou, IČO a jménem člověka', 'Formulář bez adresy a IČO působí u zakázky za statisíce podezřele.', 'must'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Měření a data',
                'slug' => 'mereni-a-data',
                'description' => 'Souhlas, nástroje, události a zdroje návštěvnosti. Bez téhle části se nedá vyhodnocovat.',
                'sections' => [
                    [
                        'title' => 'Cookies, souhlas a GDPR',
                        'description' => 'Web sbírá osobní údaje, takže tahle část není volitelná.',
                        'items' => [
                            ['Cookie lišta nabízí odmítnutí stejně viditelně jako souhlas', 'Schované odmítnutí je důvod ke stížnosti a v Česku už za něj padaly pokuty.', 'must'],
                            ['Měřicí a reklamní skripty se spustí až po souhlasu', 'Předem načtený pixel je porušení bez ohledu na to, co lišta říká.', 'must'],
                            ['Google Consent Mode v2', 'Předává stavy ad_storage, ad_user_data, ad_personalization a analytics_storage. Bez něj Google Ads v EU část konverzí nedokáže přiřadit.', 'must'],
                            ['Souhlas se ukládá a dá se doložit', 'Kdy, k čemu a v jaké verzi textu ho návštěvník udělil.', 'should'],
                            ['Zásady cookies vyjmenovávají konkrétní soubory', 'Včetně doby platnosti a toho, kdo je nastavuje. Obecný text stažený z internetu neodpovídá tomu, co web opravdu dělá.', 'should'],
                            ['Zásady zpracování osobních údajů odpovídají skutečnosti', 'Kdo data zpracovává, jak dlouho je držíte a komu je předáváte (hosting, CRM, e-mailing).', 'must'],
                            ['Souhlas s obchodními sděleními je oddělený od odeslání poptávky', 'Nesmí být předzaškrtnutý ani podmínkou odeslání.', 'must'],
                            ['Zpracovatelské smlouvy s dodavateli', 'S hostingem, e-mailingem a případným CRM.', 'should'],
                        ],
                    ],
                    [
                        'title' => 'Nástroje',
                        'description' => 'Účty založte dřív, než web půjde ven. Doplňovat měření zpětně znamená přijít o data z prvních týdnů.',
                        'items' => [
                            ['Google Tag Manager jako jediné místo, kde se spravují skripty', 'Přidání dalšího nástroje pak nevyžaduje zásah do kódu.', 'must'],
                            ['GA4 s vlastním datovým streamem', 'A s prodlouženou dobou uchování dat, výchozí nastavení je krátké.', 'must'],
                            ['Google Ads propojený s GA4 a s importovanými konverzemi', 'Bez propojení optimalizuje Google naslepo.', 'must'],
                            ['Meta Pixel a k němu Conversions API', 'Samotný pixel v prohlížeči přijde o část konverzí kvůli blokovačům a nastavení iOS. Odesílání ze serveru to dorovná.', 'must'],
                            ['Nástroj na heatmapy a nahrávky relací', 'Microsoft Clarity je zdarma a na začátek stačí. Ukáže, na kterém kroku kalkulačky lidé váhají.', 'should'],
                            ['Klíčové konverze se posílají i ze serveru', 'Odeslání leadu je ta jedna událost, u které se nevyplatí spoléhat na prohlížeč.', 'should'],
                            ['Účty vlastní klient, externisté mají přidělený přístup', 'Reklamní účet založený na osobním profilu externisty se převádí těžko.', 'must'],
                            ['Před spuštěním se měření ověří v testovacím režimu', 'GTM Preview, GA4 DebugView, Meta Test Events. Nefunkční měření se nejhůř dohání zpětně.', 'must'],
                        ],
                    ],
                    [
                        'title' => 'Data layer a události',
                        'description' => 'Pojmenování držte jednotné. Přejmenovat událost později znamená přijít o porovnání s historií.',
                        'items' => [
                            ['dataLayer je na stránce dřív než GTM', 'Jinak první událost odejde do prázdna.', 'must'],
                            ['page_view s typem stránky', 'Rozliší vstupní stránku, krok kalkulačky a děkovací stránku.', 'should'],
                            ['calculator_start', 'Otevření kalkulačky. Začátek celé měřené cesty.', 'must'],
                            ['calculator_step s číslem kroku, názvem a vybranou hodnotou', 'Nejcennější událost z celé sady. Ukáže, který krok lidi zastavuje.', 'must'],
                            ['calculator_step_back', 'Návrat o krok zpět bývá signál, že zadání není srozumitelné.', 'nice'],
                            ['calculator_abandon', 'Opuštění rozdělané kalkulačky, včetně posledního dokončeného kroku.', 'should'],
                            ['calculator_complete s odhadovanou cenou a parametry', 'Umožní porovnat, jaké konfigurace se dopočítají a jaké ne.', 'must'],
                            ['lead_submit s identifikátorem leadu a hodnotou', 'Identifikátor je pojítko mezi měřením a databází. Bez něj se konverze zpětně nespáruje se zakázkou.', 'must'],
                            ['form_error s důvodem', 'Odhalí, jestli lidé neodesílají kvůli chybě ve validaci.', 'should'],
                            ['cta_click, phone_click, email_click', 'Část zákazníků kalkulačku vynechá a rovnou zavolá.', 'should'],
                            ['Hloubka scrollu na vstupní stránce', 'Pomůže poznat, kde dlouhá stránka přestává lidi zajímat.', 'nice'],
                            ['generate_lead nastavený jako konverze v Ads i v Metě', 'A jen jednou, ať se konverze nepočítá dvakrát.', 'must'],
                            ['Offline konverze zpět do Ads a Mety', 'Zaměření, odeslaná nabídka a podepsaná smlouva se párují přes gclid a fbclid. Bez toho systém optimalizuje na leady, ne na zakázky.', 'must'],
                            ['Enhanced conversions s hashovaným e-mailem a telefonem', 'Dorovná konverze, které by se jinak ztratily.', 'should'],
                        ],
                    ],
                    [
                        'title' => 'Zdroje návštěvnosti a atribuce',
                        'description' => 'Aby se u podepsané zakázky dalo dohledat, ze které kampaně přišla.',
                        'items' => [
                            ['UTM parametry se zachytí při vstupu a uloží', 'Do cookie nebo session, ať přežijí i další stránky.', 'must'],
                            ['První i poslední zdroj se ukládají zvlášť', 'Zákazník často přijde z Mety a vrátí se přes vyhledání jména firmy. Když si držíte jen poslední zdroj, kampaň vypadá hůř, než jaká je.', 'should'],
                            ['gclid, fbclid, wbraid, gbraid a msclkid se ukládají k leadu', 'Tohle jsou klíče, přes které se posílají offline konverze zpátky.', 'must'],
                            ['Zdroj je uložený v databázi u leadu, ne jen v GA4', 'Z GA4 se zpětně nedozvíte, odkud přišel konkrétní podepsaný zákazník.', 'must'],
                            ['Referrer a vstupní stránka u leadu', 'Doplní obrázek tam, kde UTM chybí.', 'should'],
                            ['Parametry přežijí celý průchod kalkulačkou', 'Nejčastěji se ztratí při přechodu mezi kroky nebo po návratu z jiné stránky.', 'must'],
                            ['Domluvená konvence pojmenování UTM', 'Třeba utm_source=facebook, utm_medium=cpc, utm_campaign=pergoly-jaro. Bez pravidla vznikne v reportu deset variant jedné kampaně.', 'should'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Leady a provoz',
                'slug' => 'leady-a-provoz',
                'description' => 'Cesta od vyplněné kalkulačky k podepsané zakázce a co ji nesmí přerušit.',
                'sections' => [
                    [
                        'title' => 'Formuláře a kalkulačka',
                        'description' => 'Tady se rozhoduje, kolik z návštěv skončí poptávkou.',
                        'items' => [
                            ['Kontakt se získává co nejdřív', 'E-mail nebo telefon ve druhém kroku znamená, že máte na koho se obrátit i u nedokončené kalkulace. Hned v prvním kroku ale část lidí odradí, vyzkoušejte obě varianty proti sobě.', 'should'],
                            ['Rozpracovaná kalkulace se průběžně ukládá na server', 'Uložení jen v prohlížeči zmizí s vymazáním historie i s přechodem na jiné zařízení.', 'must'],
                            ['Částečná data se odesílají i bez dokončení', 'Největší skrytá hodnota celého měření. Uvidíte, na kterém kroku a při jakém parametru lidé odcházejí.', 'must'],
                            ['Odkaz na obnovení kalkulace pro zákazníka i obchodníka', 'Zákazník naváže, kde skončil. Obchodník otevře stejnou kalkulaci a projde ji s ním po telefonu.', 'should'],
                            ['Validace na klientovi i na serveru', 'Kontrola jen v prohlížeči se dá obejít a do databáze pak spadne nesmysl.', 'must'],
                            ['Ochrana proti botům', 'Cloudflare Turnstile nebo reCAPTCHA v3, k tomu skryté pole jako past. Bez toho začne po spuštění reklam chodit spam a zanese statistiku i práci obchodníkovi.', 'must'],
                            ['Omezení počtu odeslání z jedné adresy', 'Levná pojistka proti zahlcení formuláře.', 'should'],
                            ['Ukazatel průběhu', 'Krok 3 ze 6. Zákazník ví, do čeho jde, a spíš to dokončí.', 'should'],
                            ['Návrat o krok zpět bez ztráty vyplněného', 'Ztráta zadaných rozměrů je jeden z mála důvodů, proč lidé odejdou naštvaní.', 'must'],
                            ['Kalkulačka se ovládá na mobilu jednou rukou', 'Většina návštěv z Mety přijde z telefonu.', 'must'],
                            ['Děkovací stránka shrne zadání a řekne, co bude dál', 'Snižuje počet dotazů typu „přišlo vám to?".', 'should'],
                        ],
                    ],
                    [
                        'title' => 'Evidence zakázek a napojení',
                        'description' => 'Bez evidence stavů se nedá spočítat cena za podepsanou zakázku, tedy to jediné číslo, na kterém opravdu záleží.',
                        'items' => [
                            ['Lead se ukládá s kompletním obsahem kalkulace', 'Včetně všech zvolených parametrů a odhadované ceny.', 'must'],
                            ['Stavy zakázky odpovídají skutečnému procesu', 'Nová poptávka, kontaktováno, zaměření, nabídka, smlouva, realizace, ztraceno. Podle nich se počítá cena za zaměření, za nabídku i za podpis.', 'must'],
                            ['U ztraceného leadu se vyplňuje důvod', 'Cena, termín, konkurence, nereagoval. Po padesáti leadech je z toho podklad pro kampaň i pro ceník.', 'should'],
                            ['Obchodník si stavy mění sám, bez vývojáře', 'Jinak se evidence přestane vyplňovat během prvního měsíce.', 'must'],
                            ['Notifikace obchodníkovi hned po odeslání', 'E-mail vždy, u dražších poptávek ideálně i SMS. Rychlost první odpovědi rozhoduje víc než cena.', 'must'],
                            ['Export leadů do tabulky', 'Pro účetnictví, pro reporting a jako záložní kopie.', 'should'],
                            ['Rozhraní pro pozdější napojení na CRM', 'Webhook stačí. Řeší se, až bude jasné, jestli vlastní evidence přestala dostačovat.', 'nice'],
                            ['Opakované odeslání téhož formuláře se spojí, ne zdvojí', 'Jinak jeden zákazník vypadá jako tři leady a zkreslí cenu za lead.', 'should'],
                        ],
                    ],
                    [
                        'title' => 'E-maily zákazníkovi',
                        'description' => 'Co odejde hned po odeslání kalkulace a proč na tom záleží.',
                        'items' => [
                            ['Rekapitulace kalkulace odchází okamžitě', 'Zákazník má co ukázat doma a vy máte důvod se ozvat.', 'must'],
                            ['V e-mailu je napsané, kdo a kdy se ozve', '„Ozveme se do dvou pracovních dnů" funguje líp než „ozveme se co nejdříve".', 'must'],
                            ['Odesílání přes službu s doručitelností, s SPF, DKIM a DMARC', 'Bez nastavených záznamů spadne část potvrzení do spamu a zákazník má pocit, že se nic nestalo.', 'must'],
                            ['Odesílatel je na doméně klienta', 'Potvrzení z gmailové adresy důvěru nebuduje.', 'should'],
                            ['Text e-mailu se edituje v administraci', 'Formulace se ladí podle toho, na co lidé reagují.', 'should'],
                            ['Připomínka nedokončené kalkulace', 'Jen když zákazník nechal kontakt a souhlas. Jeden e-mail, ne série.', 'nice'],
                        ],
                    ],
                    [
                        'title' => 'Provoz, monitoring a zálohy',
                        'description' => 'Když běží reklama, každá hodina rozbité kalkulačky stojí peníze.',
                        'items' => [
                            ['Hlášení chyb aplikace s upozorněním', 'Sentry nebo obdoba. O spadlé kalkulačce se musíte dozvědět dřív než z reklamace.', 'must'],
                            ['Sledování dostupnosti webu', 'Kontrola po minutách a upozornění na telefon.', 'must'],
                            ['Automatický kontrolní test odeslání formuláře', 'Odhalí tichý výpadek konverze, kdy web běží, ale leady nechodí. To je nejdražší porucha, jaká může nastat.', 'should'],
                            ['Denní záloha databáze mimo produkční server', 'Záloha na stejném stroji neochrání před ztrátou toho stroje.', 'must'],
                            ['Obnova ze zálohy je vyzkoušená', 'Záloha, kterou nikdo nikdy neobnovil, je jen soubor.', 'should'],
                            ['Záznam o přijatých leadech nezávislý na e-mailu', 'Pojistka pro případ, že selže odesílání notifikací.', 'should'],
                            ['Aktualizace a bezpečnostní záplaty', 'Domluvte, kdo je hlídá a jak často.', 'should'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Řízení a vyhodnocení',
                'slug' => 'rizeni-a-vyhodnoceni',
                'description' => 'Kdo co vlastní a podle jakých čísel se pozná, že se to vyplácí.',
                'sections' => [
                    [
                        'title' => 'Přístupy a vlastnictví účtů',
                        'description' => 'Vyřešte na začátku. Zpětný převod reklamního účtu nebo domény bývá nepříjemný.',
                        'items' => [
                            ['Doména, hosting a repozitář na účtech klienta', 'Fakturace i vlastnictví.', 'must'],
                            ['Google Ads, Meta Business Manager, GA4, GTM a Search Console vlastní klient', 'Externisté se do nich přidávají, ne naopak.', 'must'],
                            ['Externisté mají přidělený přístup, ne vlastnictví', 'Partner access u Mety, běžný přístup u Google účtů.', 'must'],
                            ['Soupis všech účtů a přístupů na jednom místě', 'Kdo má kam přístup a na jakou úroveň.', 'should'],
                            ['Domluvený postup pro ukončení spolupráce', 'Co se předává, v jaké lhůtě a v jakém formátu.', 'should'],
                        ],
                    ],
                    [
                        'title' => 'Vyhodnocování a reporting',
                        'description' => 'Cílem není nejlevnější lead, ale nejlevnější podepsaná zakázka.',
                        'items' => [
                            ['Napsaná definice kvalifikovaného leadu', 'Bez ní se nedá počítat cena za kvalifikovaný lead a každý si pod tím představí něco jiného.', 'must'],
                            ['Měří se celý funnel od návštěvy po podpis', 'Návštěva, spuštění kalkulačky, dokončení, lead, zaměření, nabídka, smlouva.', 'must'],
                            ['Sledují se ceny po jednotlivých krocích', 'Cena za lead, za kvalifikovaný lead, za zaměření, za nabídku a za podepsanou zakázku.', 'must'],
                            ['Stavy zakázky se zpětně doplňují k leadu', 'Jinak nemá reklamní systém z čeho poznat, které leady stály za to.', 'must'],
                            ['Report nad GA4 a daty z databáze', 'Looker Studio stačí. Podstatné je, aby v jednom reportu byla útrata i podepsané zakázky.', 'should'],
                            ['Domluvený interval vyhodnocení', 'Týdně provoz kampaní, měsíčně obchodní výsledek.', 'should'],
                            ['Domluvené minimum dat, než se kampaň vypne', 'Vypnout kampaň po deseti kliknutích znamená rozhodovat se podle šumu.', 'should'],
                        ],
                    ],
                ],
            ],
        ];
    }
};
