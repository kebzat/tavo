<?php

namespace App\Support;

/**
 * Dopadové stránky s konkrétními nabídkami pro e-shopy.
 *
 * Obsah bydlí v kódu, ne v databázi, protože každá stránka má vlastní routu
 * a vlastní strukturovaná data — kdyby si správce v administraci smazal
 * položku, zůstala by po ní routa bez obsahu a odkaz v patičce do prázdna.
 * Texty se tu proto mění commitem, stejně jako routy.
 *
 * Tvar jedné nabídky:
 *   nav_label       krátký název do patičky a rozcestníku
 *   seo_title       titulek do <head> BEZ přípony (tu přidá PageMeta)
 *   seo_description meta description
 *   service_type    schema.org serviceType
 *   headline        H1
 *   intro           odstavce pod H1
 *   sections        [{title (H2), paragraphs}]
 *   faq             [{question (H3), answer}] — zdroj pro JSON-LD FAQPage
 *   cta_title       první věta CTA
 *   cta_perex       zbytek CTA
 */
final class EshopOffers
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'mereni-pro-eshopy' => [
                'nav_label' => 'Měření pro e-shopy',
                'seo_title' => 'Měření pro e-shopy: GA4, server-side GTM, Consent Mode',
                'seo_description' => 'Opravíme měření vašeho e-shopu, aby se čísla v GA4 a v reklamních účtech shodovala s realitou. Server-side GTM, Consent Mode v2, Meta CAPI. Hradec Králové, celá ČR.',
                'service_type' => 'Měření a analytika pro e-shopy',
                'headline' => 'Měření, kterému se dá věřit',
                'intro' => [
                    'Většina e-shopů, které k nám přijdou, má v GA4 jiná čísla než v administraci. Někdy o deset procent, někdy o třetinu. Google Ads pak optimalizuje podle konverzí, které se nikdy nestaly, a Meta vidí jen část nákupů. Marketing utrácí podle špatné mapy.',
                    'Zpravidla za tím není jedna velká chyba, ale několik malých: duplicitní purchase event, špatně nastavený Consent Mode, ad blockery, které odříznou třetinu návštěv, nebo dataLayer, který někdo před dvěma lety upravil a od té doby na něj nikdo nesáhl.',
                ],
                'sections' => [
                    [
                        'title' => 'Co uděláme',
                        'paragraphs' => [
                            'Začneme auditem. Projdeme si, co se kde měří, srovnáme to s objednávkami v administraci a napíšeme vám, kde se data ztrácejí. Tohle dostanete jako dokument, který si můžete přečíst i bez nás.',
                            'Pak to opravíme. Přesuneme měření na server-side Google Tag Manager, takže reklamní systémy dostávají data přímo ze serveru a nezávisí na tom, jestli má zákazník ad blocker. Nastavíme Consent Mode v2 tak, aby odpovídal tomu, co váš cookie banner opravdu zobrazuje. Zapojíme Meta Conversions API a rozšířené konverze pro Google Ads. E-commerce události v GA4 (zobrazení produktu, přidání do košíku, nákup) projdeme jednu po druhé.',
                            'Na konci dostanete kontrolní report: co se měří, kde, a jak si to sami ověříte, kdyby vám za půl roku něco nesedělo.',
                        ],
                    ],
                    [
                        'title' => 'Pro koho to je',
                        'paragraphs' => [
                            'Pro e-shopy, které utrácejí za reklamu tolik, že je špatné měření stojí víc než jeho oprava. Prakticky to znamená kohokoliv s Google Ads nebo Meta kampaněmi nad pár desítek tisíc měsíčně. Děláme to na Shoptetu, Shoptet Premium, Upgates, WooCommerce i Shopify.',
                        ],
                    ],
                    [
                        'title' => 'Proč my',
                        'paragraphs' => [
                            'Tom měření staví, Pavel podle něj dennodenně řídí kampaně. Když se něco měří špatně, poznáme to nejdřív v číslech kampaní, ne až v ročním reportu. To je rozdíl proti tomu, když měření nastaví někdo, kdo pak s výsledky nepracuje.',
                        ],
                    ],
                ],
                'faq' => [
                    [
                        'question' => 'Jak dlouho to trvá?',
                        'answer' => 'Audit máte do týdne. Kompletní přestavba měření včetně server-side GTM obvykle dva až tři týdny, podle toho, jak složitý je e-shop a kolik systémů je napojených.',
                    ],
                    [
                        'question' => 'Přijdu o historická data v GA4?',
                        'answer' => 'Ne. Nová implementace navazuje na stávající property. Historii nemažeme, jen dáme do pořádku, co se sbírá odteď.',
                    ],
                    [
                        'question' => 'Musím měnit cookie lištu?',
                        'answer' => 'Většinou ne. Consent Mode napojíme na tu, kterou máte. Pokud lišta nesplňuje, co po ní zákon chce, řekneme vám to v auditu.',
                    ],
                    [
                        'question' => 'Co server-side GTM stojí na provozu?',
                        'answer' => 'Serverový kontejner běží na cloudu a má měsíční náklad v řádu stovek korun. Konkrétní částku uvidíte v nabídce podle očekávané návštěvnosti.',
                    ],
                    [
                        'question' => 'Děláte i samotný audit bez opravy?',
                        'answer' => 'Ano. Někdo si opravu udělá interně, někdo ji chce od nás. Audit je užitečný v obou případech.',
                    ],
                ],
                'cta_title' => 'Chcete vědět, kolik dat se vám ztrácí?',
                'cta_perex' => 'Napište nám a domluvíme si audit.',
            ],

            'aplikace-pro-shoptet-premium' => [
                'nav_label' => 'Aplikace pro Shoptet Premium',
                'seo_title' => 'Vlastní aplikace a integrace pro Shoptet Premium',
                'seo_description' => 'Stavíme funkce, které Shoptet sám neumí: konfigurátory produktů, kalkulačky, napojení na ERP a sklad, AI asistenty. Externí aplikace napojená přes Shoptet API, bez zásahu do jádra.',
                'service_type' => 'Vývoj aplikací a integrací pro Shoptet Premium',
                'headline' => 'Když Shoptet Premium nestačí, dostavíme to',
                'intro' => [
                    'Shoptet Premium je dobrý základ pro větší e-shop. Má ale hranici, na kterou narazíte ve chvíli, kdy potřebujete něco, co nedělá nikdo jiný. Konfigurátor potisku, který zákazníkovi ukáže výsledek dřív, než zaplatí. Kalkulačku, která z rozměrů místnosti spočítá, kolik čeho koupit. Napojení na sklad, které váš dodavatel nepodporuje. Asistenta, který z fotky poradí, co vybrat.',
                    'Tohle se v administraci nenastaví a kodér šablony to nepostaví. Potřebujete aplikaci, která běží vedle e-shopu a mluví s ním přes API.',
                ],
                'sections' => [
                    [
                        'title' => 'Jak to stavíme',
                        'paragraphs' => [
                            'Aplikace běží na vlastním serveru mimo Shoptet. Do vaší šablony se vloží jedním skriptem, takže zákazník nepozná, že opustil e-shop. S košíkem a objednávkami komunikuje přes Shoptet API a webhooky. Data zůstávají u vás, ne u třetí strany, a Shoptet můžete kdykoliv aktualizovat, aniž by se něco rozbilo.',
                            'Stavíme v Laravelu. Ne proto, že by to bylo módní, ale proto, že v něm máme postavené věci, které běží roky bez zásahu.',
                        ],
                    ],
                    [
                        'title' => 'Co jsme dělali',
                        'paragraphs' => [
                            'Napojení dopravce PPL přes API tam, kde standardní modul nestačil. Konektor mezi Shoptetem a účetnictvím Pohoda včetně toho, co obvykle nejde: dopravné, platby, likvidace. Kompletní implementaci Shoptet Premium pro velkoobchod s desítkami tisíc položek. Konkrétní reference vám ukážeme na schůzce, podle toho, co řešíte.',
                        ],
                    ],
                    [
                        'title' => 'Jak probíhá spolupráce',
                        'paragraphs' => [
                            'Nejdřív krátká analýza: co přesně má aplikace umět, kde jsou hranice Shoptetu a co z toho jde udělat jednodušeji. Výstupem je návrh řešení a rozpad na fáze, každá s vlastní cenou. Vy vidíte, co za co platíte, a můžete začít jen první fází.',
                            'Pak vývoj po fázích. Každou fázi si odsouhlasíte, než začneme další. Po spuštění nabízíme provoz a rozvoj, protože aplikace, kterou nikdo nespravuje, za dva roky přestane fungovat.',
                        ],
                    ],
                ],
                'faq' => [
                    [
                        'question' => 'Nerozbije se to při aktualizaci Shoptetu?',
                        'answer' => 'Aplikace nesahá do jádra Shoptetu. Používá oficiální API a jeden skript v šabloně. Když Shoptet změní API, což oznamuje dopředu, upravíme napojení my.',
                    ],
                    [
                        'question' => 'Potřebuji Shoptet Premium, nebo to jde i na běžném tarifu?',
                        'answer' => 'Většina věcí potřebuje Premium kvůli přístupu k API a možnostem šablony. Pokud si nejste jistí, napište nám, co chcete udělat, a řekneme vám to rovnou.',
                    ],
                    [
                        'question' => 'Kdo vlastní výsledek?',
                        'answer' => 'Vy dostáváte licenci na aplikaci, svá data a tiskové či exportní soubory. Podrobnosti jsou ve smlouvě, nic v ní nepřekvapí.',
                    ],
                    [
                        'question' => 'Kolik kol úprav je v ceně?',
                        'answer' => 'U každé fáze dvě kola korekcí. Další úpravy hodinově. Říkáme to dopředu, aby ani jedna strana nebyla překvapená.',
                    ],
                ],
                'cta_title' => 'Popište nám, co má e-shop umět.',
                'cta_perex' => 'Odpovíme, jestli to jde a jak.',
            ],

            'migrace-na-shoptet' => [
                'nav_label' => 'Migrace na Shoptet',
                'seo_title' => 'Migrace e-shopu na Shoptet a Shoptet Premium',
                'seo_description' => 'Přechod z WooCommerce, PrestaShopu, Magenta nebo vlastního řešení na Shoptet. Přeneseme produkty, zákazníky, objednávky i pozice ve vyhledávání. Bez výpadku prodeje.',
                'service_type' => 'Migrace e-shopu na Shoptet',
                'headline' => 'Přechod na Shoptet bez ztráty pozic a zákazníků',
                'intro' => [
                    'Důvod k přechodu bývá stejný: současný e-shop je drahý na údržbu, aktualizace se bojíte spustit a každá úprava trvá týdny. Shoptet to řeší tím, že se o platformu stará někdo jiný. Vy se staráte o prodej.',
                    'Migrace ale není export a import. Je to stovky rozhodnutí o tom, co přenést, co zahodit a co udělat jinak. Špatně provedená migrace znamená propad z Googlu na půl roku a zákazníky, kterým nefungují přihlašovací údaje.',
                ],
                'sections' => [
                    [
                        'title' => 'Co přenášíme',
                        'paragraphs' => [
                            'Produkty včetně variant, parametrů, obrázků a popisů. Kategorie a jejich strukturu, případně upravenou, když ta stará nedávala smysl. Zákaznické účty. Historii objednávek, pokud ji chcete mít v novém systému. Přesměrování ze všech starých URL na nové, aby vyhledávače i staré odkazy vedly tam, kam mají.',
                            'Než přepneme, projdeme oba e-shopy vedle sebe. Přepínáme v době, kdy máte nejméně objednávek, a starý e-shop necháváme běžet, dokud si nejsme jistí.',
                        ],
                    ],
                    [
                        'title' => 'Odkud migrujeme',
                        'paragraphs' => [
                            'WooCommerce, PrestaShop, Magento, Upgates, OpenCart i e-shopy postavené na míru, kde už nikdo neví, jak fungují. U těch je práce nejvíc, ale i největší úleva, když skončí.',
                            'Máme za sebou migraci z Magenta na Shoptet Premium pro velkoobchod s desítkami tisíc položek. Ptejte se na ni.',
                        ],
                    ],
                    [
                        'title' => 'Co je po migraci',
                        'paragraphs' => [
                            'Shoptet umí hodně, ale ne všechno. Funkce, které váš starý e-shop měl a Shoptet nemá, buď nahradíme doplňkem, nebo dostavíme jako vlastní aplikaci. Zároveň dostanete správně nastavené měření, protože po přepnutí platformy se to jinak rozbije skoro vždy.',
                        ],
                    ],
                ],
                'faq' => [
                    [
                        'question' => 'Jak dlouho migrace trvá?',
                        'answer' => 'Malý e-shop tři až čtyři týdny. Velký e-shop s vlastními funkcemi dva až tři měsíce. Přesný odhad dostanete po analýze, ne dřív.',
                    ],
                    [
                        'question' => 'Přijdu o pozice ve vyhledávání?',
                        'answer' => 'Při správně provedených přesměrováních a zachování obsahu bývá výkyv malý a krátkodobý. Ztráta pozic pochází skoro vždy z chybějících redirectů nebo smazaných stránek, a to hlídáme.',
                    ],
                    [
                        'question' => 'Co zákaznické účty a hesla?',
                        'answer' => 'Účty přeneseme, hesla z bezpečnostních důvodů přenést nejdou. Zákazníkům pošleme informaci s odkazem na nastavení nového hesla. Máme na to připravený text.',
                    ],
                    [
                        'question' => 'Mohu na starém e-shopu prodávat, dokud není nový hotový?',
                        'answer' => 'Ano. Nový e-shop stavíme vedle, přepínáme až na konci a rozdíl v objednávkách mezi posledním exportem a přepnutím doplníme ručně.',
                    ],
                ],
                'cta_title' => 'Řekněte nám, na čem e-shop běží teď.',
                'cta_perex' => 'Do pár dnů dostanete odhad, co migrace obnáší.',
            ],

            'rozvoj-eshopu' => [
                'nav_label' => 'Rozvoj e-shopu',
                'seo_title' => 'Dlouhodobý rozvoj e-shopu: vývoj a výkonnostní marketing',
                'seo_description' => 'Jeden tým pro vývoj i marketing vašeho e-shopu. Každý měsíc dohodnutý objem práce, priority podle čísel, žádné dohadování mezi agenturou a programátorem.',
                'service_type' => 'Dlouhodobý rozvoj e-shopu a výkonnostní marketing',
                'headline' => 'Vývojář a marketér, kteří sedí u jednoho stolu',
                'intro' => [
                    'Znáte to. Marketingová agentura chce novou landing page, programátor nemá čas, kampaň běží na starou stránku a po měsíci se všichni dohadují, čí je to vina. My tenhle problém nemáme, protože Pavel a Tom jsou dva lidé, kteří spolu mluví každý den.',
                    'Dlouhodobá spolupráce s námi znamená, že máte každý měsíc k dispozici dohodnutý objem práce na vývoji i na marketingu a o tom, co se z něj udělá, rozhodují čísla. Když z dat vidíme, že mobilní košík ztrácí zákazníky, opraví se košík. Když je problém v kampani, řeší se kampaň.',
                ],
                'sections' => [
                    [
                        'title' => 'Co v tom je',
                        'paragraphs' => [
                            'Na straně vývoje: úpravy šablony a funkcí, integrace, oprava toho, co se rozbilo, hlídání aktualizací a měření. Na straně marketingu: správa kampaní v Google Ads a na Metě, práce s produktovými feedy, vyhodnocování a návrhy, co dál.',
                            'Jednou měsíčně si sedneme nad čísly a domluvíme, co se bude dít příští měsíc. Dostanete krátký report, který se dá přečíst za pět minut.',
                        ],
                    ],
                    [
                        'title' => 'Pro koho to je',
                        'paragraphs' => [
                            'Pro e-shopy, které už mají zákazníky a chtějí růst, ale nemají smysl zaměstnat vlastního vývojáře a vlastního markeťáka. Typicky obrat, kde každé procento konverze má reálnou hodnotu, a majitel, který chce mít jednoho partnera, ne pět dodavatelů.',
                        ],
                    ],
                    [
                        'title' => 'Jak to začíná',
                        'paragraphs' => [
                            'Prvním měsícem, kde se hlavně díváme: měření, stav e-shopu, kampaně. Z toho vznikne seznam věcí seřazený podle toho, co přinese nejvíc za nejméně práce. Pak se jede podle seznamu. Nemusíte se vázat na rok, spolupráce se prodlužuje po měsících.',
                        ],
                    ],
                ],
                'faq' => [
                    [
                        'question' => 'Co když jeden měsíc potřebuji víc vývoje a druhý víc marketingu?',
                        'answer' => 'Objem hodin je společný a přelévá se podle potřeby. Právě proto dává smysl mít obojí u jednoho týmu.',
                    ],
                    [
                        'question' => 'Jak řešíte urgentní věci, když e-shop nefunguje?',
                        'answer' => 'Máte na nás přímý kontakt. Výpadky řešíme přednostně, ne ve frontě s ostatními úkoly.',
                    ],
                    [
                        'question' => 'Mám už agenturu na marketing. Můžete dělat jen vývoj?',
                        'answer' => 'Můžeme. S vaší agenturou se domluvíme přímo, ať nemusíte dělat prostředníka.',
                    ],
                    [
                        'question' => 'Na jakých platformách to děláte?',
                        'answer' => 'Shoptet a Shoptet Premium především, dále Upgates, WooCommerce a Shopify. Lead-gen weby na WordPressu také.',
                    ],
                ],
                'cta_title' => 'Napište nám, jaký máte e-shop a co vás na něm trápí nejvíc.',
                'cta_perex' => 'Ozveme se s návrhem, jak začít.',
            ],
        ];
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /** @return array<string, mixed> */
    public static function find(string $slug): array
    {
        $offer = self::all()[$slug] ?? abort(404);

        return $offer + ['slug' => $slug, 'url' => self::url($slug)];
    }

    /**
     * Ostatní nabídky pro rozcestník na konci stránky.
     *
     * @return list<array<string, mixed>>
     */
    public static function others(string $slug): array
    {
        return collect(self::all())
            ->except($slug)
            ->map(fn (array $offer, string $key) => $offer + ['slug' => $key, 'url' => self::url($key)])
            ->values()
            ->all();
    }

    public static function url(string $slug): string
    {
        return route('eshop.'.$slug);
    }
}
