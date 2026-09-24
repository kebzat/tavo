## Shrnutí

Technicky stojí e-shop na dobrém základu: HTTPS, kanonické adresy, obsah vykreslený na serveru a produktová mikrodata. **Největší problém je, že Google dostává 25× víc bezcenných adres než skutečných stránek** a že na webu zůstal nesmyslný ukázkový obsah ze šablony Upgates. U AI asistentů je e-shop prakticky neviditelný. Dva hlavní AI roboti (OpenAI a Anthropic) dostávají od serveru chybu a na obecné dotazy AI doporučuje konkurenci.

### Stav podle oblastí

| Oblast | Stav | Co jsme zjistili |
|---|---|---|
| **Indexace a sitemapa** | [[kritické]] | Index zahlcují filtry z textových parametrů a varianty produktů |
| **Ukázkový obsah** | [[kritické]] | 4 aktuality, rádce a výrobce „Upgates“ jsou nesmyslný text. Seznam je má v indexu |
| **Měření** | [[kritické]] | Chybí Search Console, Bing i Seznam Webmaster, takže se o chybách nikdo nedozví |
| **GEO a AI viditelnost** | [[vysoké]] | Blokovaní AI roboti, žádný citovatelný obsah, konkurence v AI odpovědích |
| **Titulky a popisky** | [[vysoké]] | Titulky verzálkami, úvodka jen „Svět Cejlonu“, popisky se generují ze šablony |
| **Obsah** | [[vysoké]] | Kategorie bez vlastního textu, žádné návody ani články, díra v informačních dotazech |
| **Strukturovaná data** | [[střední]] | Mikrodata existují, ale recenze a firma v nich mají chyby, chybí Organization a FAQ |
| **Rychlost** | [[střední]] | Server odpovídá pomalu a nestabilně (medián 0,7 s, špičky přes 8 s), na mobilu je vysoké TBT |
| **Důvěryhodnost (E-E-A-T)** | [[střední]] | Silný příběh a recenze, ale chybí stránka Kontakt a zdravotní tvrzení jsou riskantní |
| **Technický základ** | [[v pořádku]] | HTTPS + HSTS, přesměrování, 404, `hreflang`, `robots.txt`, kanonické URL |
| **Server-side rendering** | [[v pořádku]] | Veškerý obsah je v HTML bez JavaScriptu, což je výhoda pro AI i roboty |
| **Alt texty** | [[v pořádku]] | Od 22. 9. doplněné. Zbývají jen 2 technické obrázky ze šablony na každé stránce |

> **Co se od 22. 9. zhoršilo:** počet adres v sitemapě vyskočil ze 414 na 1 974. Příčinou je hromadný import parametrů produktů (chuťový profil, tipy, složení, použití…). Upgates z *každé hodnoty každého parametru* vyrobil indexovatelnou stránku, například `/caje/p-tip/s-citronem-zvyrazni-svezest-nalevu` s titulkem „ČAJE – Tip – S citronem – zvýrazní svěžest nálevu.“ Oprava zabere pár minut v administraci, viz Indexace.

## Top 12 priorit

Seřazeno podle poměru dopad / práce. Sloupec „Kdo“ říká, jestli to jde udělat v administraci (klient), v šabloně (my), nebo jestli je potřeba podpora Upgates.

| # | Úkol | Dopad | Práce | Kdo |
|---|---|---|---|---|
| 1 | **Založit Google Search Console, Bing Webmaster Tools a Seznam Webmaster** a odeslat sitemapu | [[kritický]] | 30 min | klient + my |
| 2 | **Filtrační stránky označit jako „neindexovat“** (1 786 URL). Výjimku dostane jen pár štítků s vlastním textem | [[kritický]] | 15 min | administrace |
| 3 | **Vypnout varianty v sitemapě** (104 URL, například `/p/lotovovy-kvet/203`, kterou už Bing zaindexoval) | [[kritický]] | 5 min | administrace |
| 4 | **Smazat ukázkový obsah Upgates**: 4 aktuality, rádce „brož“, výrobce „Upgates“, stránku `/why-us` a pomocné `/oznameni-*` vyřadit z indexu | [[kritický]] | 20 min | administrace |
| 5 | **Zeptat se podpory Upgates na odblokování GPTBot a ClaudeBot** (dnes vrací 503) a na kanonické URL u variant a filtrů | [[vysoký]] | 1 e-mail | podpora Upgates |
| 6 | **Titulek a popisek úvodky, 5 kategorií a obsahových stránek** (hotové návrhy v příloze) | [[vysoký]] | 1–2 h | administrace |
| 7 | **JSON-LD Organization / OnlineStore** se `sameAs` (Instagram, Facebook, Heureka, Firmy.cz) a opravit LocalBusiness („Marek Bezdíček“, `$$$$$$`) | [[vysoký]] | 1 h | my (šablona) |
| 8 | **Opravit mikrodata recenzí** (`reviewRating`, formát data), aby Google mohl ukazovat hvězdičky | [[vysoký]] | 1 h | my (šablona) |
| 9 | **Vlastní úvodní text kategorií** (150–300 slov) + FAQ blok v každé kategorii | [[vysoký]] | 1 den | klient (text) + my |
| 10 | **Rádce: 6–10 odborných návodů** (skořice cejlonská vs. kasie, jak louhovat, co je Pekoe/BOP, moringa…), které cituje AI | [[vysoký]] | průběžně | klient |
| 11 | **Sjednotit Firmy.cz** (dnes dva záznamy: „Svetcejlonu.cz“ a „Svět Cejlonu“) a založit Google Business Profile | [[střední]] | 30 min | klient |
| 12 | **Projít zdravotní tvrzení** u ájurvédy a doplňků („bolest hlavy“, „plísňové potíže“, „zubní první pomoc“) | [[střední]] | 2 h | klient |

## Bez Google Search Console: co to znamená a co s tím

Bez Search Console nikdo neví, které stránky Google zaindexoval, na jaké dotazy web zobrazuje, kolik lidí kliká ani jestli Google nehlásí chyby (třeba neplatná strukturovaná data nebo „Prohledáno – zatím neindexováno“). Tento audit proto všechno změřil zvenčí. Některé věci se ale jinak než přes Search Console zjistit nedají.

### Co jsme změřili bez ní

- všech 1 974 URL ze sitemapy a 1 009 prošlých stránek (stav, titulek, popisek, kanonická URL, H1, mikrodata, alt…)
- co má v indexu **Seznam** (dotaz `site:`): úvodku, 5 produktů, `/o-nas`, `/ajurveda`, ale i **ukázkovou aktualitu „Dárek k produktům značky Citizen“**
- jak web vidí **AI vyhledávání** (testovací dotazy, viz GEO)
- jak server odpovídá 14 různým robotům
- laboratorní Lighthouse pro úvodku, kategorii a produkt

### Co bez ní zjistit nejde

- skutečné pozice a dotazy, na které se web zobrazuje
- kolik z 1 974 URL Google skutečně zaindexoval
- data Core Web Vitals od reálných uživatelů (CrUX). Web má tak málo návštěv, že je Google pravděpodobně ani neshromažďuje
- jestli Google mikrodata recenzí přijímá, nebo hlásí chyby
- ruční opatření a bezpečnostní problémy
- zpětné odkazy, které Google zná

### Doporučené nastavení: tři nástroje, všechny zdarma

| Nástroj | Proč | Jak ověřit web na Upgates |
|---|---|---|
| **Google Search Console** | Indexace, dotazy, chyby, strukturovaná data. Po ověření odeslat `https://www.svetcejlonu.cz/sitemap.xml` | **Doménová vlastnost** přes DNS záznam TXT u registrátora domény (pokrývá www i bez www, http i https). Alternativa: meta tag vložíme do `layout/top.phtml` |
| **Bing Webmaster Tools** | Bing je zdroj pro **ChatGPT Search, Copilot a DuckDuckGo**. Pro GEO je to stejně důležité jako Google | Po založení GSC se dá web do Bingu jedním kliknutím *importovat z Google Search Console* |
| **Seznam Webmaster** | Seznam má v Česku stále významný podíl a hlavně ho používá starší a movitější publikum, které nakupuje čaj a ájurvédu | Soubor nebo meta tag, případně ho vložíme do šablony. Odeslat sitemapu a požádat o přeindexování opravených stránek |

> **Co už běží a dá se využít hned:** na webu je **GA4** (`G-GLVEDNS90L`). Stojí za to:
>
> - propojit GA4 se Search Console (Správce → Propojení služeb), aby se dotazy zobrazovaly přímo v Analytics,
> - založit v GA4 vlastní skupinu kanálů **„AI asistenti“** s regulárním výrazem na zdroj `chatgpt\.com|chat\.openai|perplexity|gemini\.google|copilot|claude\.ai|you\.com`. Jinak se návštěvy z AI ztrácejí v „Referral“,
> - v Search Console po měsíci zkontrolovat přehled *Stránky → Proč nejsou stránky indexovány*. Tam se ukáže, kolik filtrů a variant Google odmítl.

## Indexace a sitemapa

E-shop nabízí vyhledávačům skoro dva tisíce adres. Skutečných stránek, které mají smysl ve vyhledávání, je kolem osmdesáti. Zbytek jsou kombinace filtrů a varianty produktů. Tomuhle stavu se říká *index bloat*: roboti tráví čas na bezcenných stránkách, nové produkty se indexují pomalu a síla webu se ředí.

### Složení sitemapy (1 974 URL)

| Typ adresy | Počet |
|---|---:|
| Filtry podle parametru | **1 716** |
| Varianty produktů | **104** |
| Štítkové stránky (`/t-…`) | **68** |
| Produkty | **59** |
| Kategorie a stránky | **16** |
| Ukázkový obsah (aktuality, rádce, výrobci) | **9** |
| Filtry podle výrobce | **2** |

### [[kritické]] 1 786 indexovatelných filtračních stránek, i z textových parametrů

Každá kombinace *kategorie + parametr + hodnota* má vlastní URL, odpovídá `200`, má `index, follow`, kanonickou adresu sama na sebe a je v sitemapě. Z 877 prošlých filtračních stránek jich bylo indexovatelných všech 877. Po importu parametrů vznikly adresy z celých vět:

| URL | Titulek, který vidí Google |
|---|---|
| `/caje/p-tip/s-citronem-zvyrazni-svezest-nalevu` | ČAJE - Tip - S citronem – zvýrazní svěžest nálevu. :: Svět Cejlonu |
| `/caje/p-slozeni/slozeni-zeleny-caj-camellia-sinensis-soursop` | ČAJE - Složení - Složení: Zelený čaj (Camellia sinensis), soursop |
| `/darkove-balicky/p-pouziti/podporu-prokrveni-a-stimulaci-pokozky-2` | Dárkové balíčky - Použití - podporu prokrvení a stimulaci pokožky |
| `/ajurveda/p-baleni/velke-baleni-25-g-safran` | ÁJURVÉDA - Balení - Velké balení - 25 g - Šafrán |

Navíc je v sitemapě i **zastaralá část**: prošlé filtry vracely `404` (například `/caje/p-aroma/intenzivnejsi-95`) nebo `302` zpět na kategorii. Sitemapa tedy tvrdí, že existují stránky, které už neexistují. Google z toho usuzuje, že sitemapě nelze věřit.

> **Oprava (administrace, 15 min):** *Nastavení → Produkty → Filtry a řazení* → označit všechny položky → hromadná akce **„Označit jako neindexovat“**. Podle dokumentace Upgates tím stránky zmizí i ze `sitemap.xml`. Filtrační stránky vznikají vždy, vypnout jde jen jejich indexace.
>
> **Výjimka:** indexované nechat jen štítky, které odpovídají skutečnému hledání, a jen pokud dostanou vlastní titulek, popisek a odstavec textu. Kandidáti: `/caje/t-cele-listy`, štítek „bez kofeinu“ a „cejlonská skořice Alba“.
>
> **Do budoucna:** textové parametry (Tip, Složení, Použití, Upozornění, Skvělé kombinace, Doporučujeme) z filtrů kategorií úplně vyřadit (*Kategorie → Seznam kategorií → kategorie → Filtry*). Zákazník podle nich stejně nefiltruje.

### [[kritické]] 104 variantních adres produktů v sitemapě

Adresy typu `/p/ibisek/187` se liší jen hmotností v titulku a odkazují kanonicky samy na sebe. Google a Bing je indexují: webové vyhledávání vrací `/p/lotovovy-kvet/203` s titulkem „LOTOSOVÝ KVĚT - Modrý lotos - Hmotnost: 25 g“ místo hlavního produktu.

> **Oprava:** *Nastavení → Rozšířené → SEO* (v dokumentaci Upgates jako *Marketing → SEO*) → nastavení sitemap → zaškrtnout vyloučení variant. Na kanonickou adresu varianty ukazující na hlavní produkt se zeptat podpory Upgates (šablona to sama řešit neumí, `$link_canonical` plní systém).

### [[vysoké]] Pomocné a prázdné stránky jsou indexovatelné

| URL | Slov | Co to je |
|---|---|---|
| `/oznameni-dovolena`, `/oznameni-kosik`, `/oznameni-lista` | 10–23 | Zdroj textů pro vyskakovací okno a lištu, ne stránka pro lidi |
| `/why-us` | 8 | Prázdná systémová stránka „Naše výhody“ |
| `/m/upgates`, `/m/svet-cejlonu` | 28 / 84 | Výrobce „Upgates“ z ukázkových dat a výrobce = e-shop sám |
| `/news`, `/advisor` | 115 / 68 | Rozcestníky s ukázkovým obsahem |
| `/chybi-vam-neco-ze-sri-lanky` | 89 | Tenká, ale legitimní. Doplnit text |

> **Oprava:** u stránek `/oznameni-*` nastavit v SEO záložce „neindexovat“ (nebo je vyřadit z „Zobrazit na webu“, pokud je skript nepotřebuje veřejné; skript je ale čte fetchem, takže bezpečnější je noindex). Výrobce „Upgates“ smazat (*Produkty → Výrobci*). `/why-us` buď naplnit, nebo vyřadit ze sitemapy.

### [[v pořádku]] Co funguje

- `robots.txt` blokuje košík, checkout, `?do=` a `?_fid=` parametry a odkazuje na sitemapu
- stránkování `?page=2` má kanonickou adresu na první stránku, `/caje/pg-2` přesměrovává
- všechny běžné stránky mají kanonickou adresu samy na sebe, UTM parametry nevytvářejí duplicity
- soukromé stránky (registrace, účet) mají `noindex, nofollow`

## Ukázkový obsah Upgates, který na webu zůstal

Při zakládání e-shopu Upgates vkládá ukázková data. Část z nich na webu pořád žije a je v sitemapě i ve vyhledávání. Předchozí audit je chybně bral jako „pět článků v Aktualitách“.

### [[kritické]] Aktuality a rádce jsou generovaný nesmyslný text

| URL | Titulek | Obsah |
|---|---|---|
| `/news/darek-k-produktum-znacky-citizen` | Ke každému produktu dárek | „Obrázek samozřejmostí mi unii tištěném druhů týmy kino anténě, tři molekulou či blízkosti statistika pralese…“ (česky generované lorem ipsum, 480 slov). Datum 27. 9. 2024 |
| `/news/rozsirili-jsme-nabidku-latek` | Rozšířili jsme nabídku látek |  |
| `/news/objednavejte-nyni-i-pres-zasilkovnu` | Objednávejte nyní i přes Zásilkovnu |  |
| `/news/pripravujeme-novou-kolekci` | Připravujeme novou kolekci! |  |
| `/advisor/ozdobte-se-vlastnorucne-delanou-brozi` | Ozdobte se vlastnoručně dělanou broží! | Ukázkový návod, 438 slov |

**Proč je to vážné:**

- **Seznam má „Dárek k produktům značky Citizen“ v indexu.** Kdo hledá značku, může narazit na stránku o hodinkách s nesmyslným textem.
- Úvodní stránka na tyto 4 články **odkazuje v HTML** (sekce aktualit je skrytá jen CSS třídou `d-xxl-no`). Roboti a AI skrytý blok čtou, včetně vět „Aktuality jsou řazeny chronologicky… můžete upravit v modulu Designer (návod)“.
- Nesmyslný text snižuje hodnocení kvality celého webu (Google *helpful content*) a AI z něj může citovat.

> **Oprava:** *Obsah → Aktuality*: smazat všechny 4. *Obsah → Rádce*: smazat „brož“ a místo ní začít psát skutečné návody (viz GEO obsah). Blok aktualit z úvodky vyřadit v šabloně, ne jen skrýt. Po smazání požádat v Seznam Webmasteru o odstranění URL.

## Titulky, popisky a nadpisy

### [[vysoké]] Titulek úvodky: „Svět Cejlonu“ (12 znaků)

Úvodka je stránka s největší autoritou, ale v titulku nemá ani „čaj“, ani „koření“, ani „Srí Lanka“. Meta popisek je `Vítejte na e-shopu Svět Cejlonu! :: Svět Cejlonu`, tedy zbytek nadpisu, který se z úvodky mezitím odstranil. Chybí i `og:description` a `og:type` má neplatnou hodnotu `web` (správně `website`).

> **Oprava:** návrh je v příloze. `og:type` a `og:description` opravíme v `layout/top.phtml`.

### [[vysoké]] Meta popisky: šablona místo textu

Šablona popisku je `{heading} :: {project_name}`, takže kategorie a stránky mají popisek totožný s titulkem (`ČAJE :: Svět Cejlonu`). Stránky jako `/pomahame-skole-na-sri-lance` mají dokonce zdvojený text: „Podpora tamilské školy na Srí Lance. Podpora tamilské školy na Srí Lance :: Svět Cejlonu“.

U 59 produktů se popisek bere z krátkého popisu:

| Počet | Problém |
|---:|---|
| **41** | popisků delších než 160 znaků (Google je ořízne) |
| **39** | obsahuje zalomení řádků |
| **16** | začíná emoji (🌺 🌿 🤍 💙) |
| **6** | bez popisku, jen kopie titulku |

Bez popisku: Chilli, Dárková krabice velká, Dárková krabice malá, Přenosná čajová sada, Samahan, Skleněný louhovač.

> **Oprava:** kategorie a stránky vyplnit ručně (návrhy v příloze). U produktů stačí přepsat první větu krátkého popisu tak, aby prvních ~155 znaků fungovalo samostatně, bez emoji na začátku. Šablonu popisku v *Nastavení → Rozšířené → SEO* změnit tak, aby neopakovala titulek.

### [[střední]] Titulky verzálkami a oddělovač „::“

35 z 59 produktů a všech 5 kategorií má název velkými písmeny (`KURKUMA - MLETÁ`, `ČAJE`). Ve výsledcích vyhledávání to působí jako křik a verzálky se hůř čtou. Oddělovač `::` je neobvyklý; běžný je `|` nebo `–`. Titulky jsou jinak krátké (22–59 znaků) a nevyužívají prostor pro klíčová slova. Chybí v nich „cejlonský“, „sypaný“, „Srí Lanka“, hmotnost nebo „bio/přírodní“.

> **Oprava:** vyplnit SEO titulek (pole `seo_title`) u nejdůležitějších 15–20 produktů podle vzoru *Název produktu – upřesnění ze Srí Lanky | Svět Cejlonu*, například „Ibiškový čaj – sušený květ ibišku ze Srí Lanky | Svět Cejlonu“. Názvy produktů v administraci převést na běžná velká/malá písmena (pokud to design nevyžaduje; verzálky se dají udělat i v CSS).

### [[střední]] Víc H1 na stránce a přeskakované úrovně nadpisů

- **Ayush pleťový krém** má 5× H1, z toho dva jsou řádek podtržítek `_______________________` a dva „🌿 VARIANTA 1: TURMERIC“
- **Kardamon – černý čaj**: druhý H1 „Černý čaj Ceylon Black s kardamomem – BOFP ☕🌿“
- **Navratna olejíček**: druhý H1 „Narayana Oil – tradiční ájurvédský masážní olej…“
- **/o-nas**: druhý H1 „Jak vznikl Svět Cejlonu?“
- úvodka: karty produktů jsou `h4` hned pod `h2` (v sekci „Nepřehlédněte!“ chybí `h3`). Lighthouse hlásí *heading-order* i na produktu a kategorii

> **Oprava:** v editoru popisu přepnout tyto nadpisy na „Nadpis 2“ a odstranit dekorativní řádky podtržítek. Úrovně na kartách produktů srovnáme v šabloně.

### [[střední]] Překlepy a nesedící URL produktů

| Produkt | Problém |
|---|---|
| `/p/ranavara` | Název „**RARANAVARA** – Čistý květ“ (překlep, správně Ranawara/Ranavara) |
| `/p/lotovovy-kvet` | URL „lotovový“ místo „lotosový“ |
| `/p/cerny-caj-earl-gray` | URL „gray“, název „Earl Grey“ |
| `/p/mangostan-zeleny-caj` | URL říká zelený čaj, produkt je **černý** čaj |
| `/p/kardamon-cerny-caj-s-kardamonem` | V textu střídá „kardamon“ a „kardamom“ |

> **Oprava:** název opravit hned. URL měnit jen s 301 přesměrováním ze staré adresy (Upgates ho při změně URL obvykle nabízí, **ověřit** po změně přes `curl -I`).

### [[nízké]] Alt loga = titulek stránky

Logo v patičce i v hlavičce má `alt` i `title` nastavené na titulek aktuální stránky, například „IBIŠEK – Řezaný květ :: Svět Cejlonu“. Logo má mít `alt="Svět Cejlonu"`. Lighthouse to hlásí jako *image-redundant-alt*.

> **Oprava:** v šabloně (`logo.phtml`, pozor, má vlastní odkaz), 5 minut.

## Strukturovaná data

Upgates vypisuje **mikrodata** (atributy `itemprop`) pro WebSite, WebPage, BreadcrumbList, Product, Offer, Review a LocalBusiness. JSON-LD na webu není vůbec. Základ tedy existuje, ale v několika místech je chybný. Pro AI jsou strukturovaná data jedním z hlavních způsobů, jak spolehlivě pochopit, *kdo* web provozuje a *co* prodává.

| Typ | Stav | Poznámka |
|---|---|---|
| Product | [[částečně]] | má `name`, `sku`, `image`, `description`, `aggregateRating`. Chybí `brand`, `gtin` (EAN), `countryOfOrigin` |
| Offer | [[částečně]] | jen jedna cena „od 84 Kč“ pro všechny varianty. Chybí `priceValidUntil`, `shippingDetails`, `hasMerchantReturnPolicy`, které Google chce pro rozšířené výsledky u zboží. Správně `AggregateOffer` (lowPrice/highPrice) nebo `ProductGroup` + varianty |
| Review | [[chybné]] | hodnocení je v `div` s `itemtype="AggregateRating"` bez `itemscope` a `itemprop="reviewRating"`, takže `ratingValue` nepatří k ničemu. `datePublished` má neplatný formát `2026-09-21CEST19:54` |
| AggregateRating | [[ověřit]] | existuje (ibišek: 25 hodnocení, 5,0). Bez Search Console nevíme, jestli ho Google bere |
| BreadcrumbList | [[duplicitní]] | na detailu produktu jsou dva seznamy drobečků |
| LocalBusiness | [[chybné]] | `name` = „Marek Bezdíček“ (osoba, ne značka), `priceRange` = `$$$$$$`, chybí `address` (v patičce ji záměrně schováváme). Pro e-shop bez kamenné prodejny je vhodnější `OnlineStore` / `Organization` |
| Organization se `sameAs` | [[chybí]] | Pro AI klíčové: propojuje web s Instagramem, Facebookem, Heurekou a Firmy.cz jako jednu entitu |
| FAQPage | [[chybí]] | Google už FAQ výsledky u e-shopů neukazuje, ale AI asistenti strukturované otázky a odpovědi čtou dobře |
| Article (rádce) | [[ano]] | šablona rádce vypisuje Article + Organization. Stačí napsat skutečné návody |

### Návrh: JSON-LD pro celý web (vložíme do `layout/top.phtml`)

Doplní chybějící identitu firmy a nekoliduje s mikrodaty Upgates. Údaje je potřeba potvrdit s klientem (hlavně to, jestli chce adresu zveřejnit ve strukturovaných datech, když ji na webu skrývá).

```
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "OnlineStore",
  "@id": "https://www.svetcejlonu.cz/#organization",
  "name": "Svět Cejlonu",
  "alternateName": "svetcejlonu.cz",
  "url": "https://www.svetcejlonu.cz/",
  "logo": "https://www.svetcejlonu.cz/…/logo.png",
  "description": "Český e-shop s cejlonskými čaji, kořením a ájurvédskými produkty dováženými přímo od menších pěstitelů ze Srí Lanky, ručně balenými v ČR.",
  "email": "info@svetcejlonu.cz",
  "telephone": "+420 606 406 566",
  "founder": { "@type": "Person", "name": "Marek Bezdíček" },
  "taxID": "06451420",
  "areaServed": "CZ",
  "knowsAbout": ["cejlonský čaj", "cejlonská skořice", "srílanské koření", "ájurvéda"],
  "sameAs": [
    "https://www.instagram.com/svetcejlonu",
    "https://www.facebook.com/svetcejlonu",
    "https://obchody.heureka.cz/svetcejlonu-cz/recenze/",
    "https://www.firmy.cz/detail/13935855-svet-cejlonu-velke-porici.html"
  ]
}
</script>
```

> **Pozor na syntaxi šablony:** v `.phtml` se sekvence `{písmeno` vyhodnocuje jako makro. JSON musí být v šabloně zapsaný tak, aby za `{` vždy následovala mezera nebo uvozovka, případně celý blok obalit `{syntax off}…{/syntax}`.

## Technika a server

| Kontrola | Stav | Detail |
|---|---|---|
| HTTPS + HSTS | [[ano]] | `max-age=31536000` |
| Přesměrování http / bez www | [[ano]] | 301. `http://svetcejlonu.cz` jde přes dva skoky (http → www → https). Nevadí, ale jeden skok by byl čistší |
| Koncové lomítko | [[ano]] | `/caje/` → 301 → `/caje` |
| Stránka 404 | [[ano]] | skutečný kód 404, ne „soft 404“. Velká písmena (`/CAJE`) vrací 404 |
| `lang` a `hreflang` | [[ano]] | `cs` / `cs-CZ` na všech stránkách. Jiná jazyková mutace neexistuje |
| Obsah bez JavaScriptu | [[ano]] | produkty, texty i recenze jsou v HTML ze serveru (medián 134 kB HTML) |
| Stránka Kontakt | [[ne]] | `/kontakt` vrací 404, kontakt je jen v modálním okně a v patičce |
| Twitter / X karty | [[ne]] | nevadí, X si vezme Open Graph |
| Cache HTML | [[slabé]] | `Cache-Control: no-store` na každé stránce blokuje back/forward cache prohlížeče (Lighthouse: *bf-cache*). Je to vlastnost platformy |
| Cache statických souborů | [[slabé]] | Lighthouse našel 19–42 souborů s krátkou dobou platnosti v cache |
| HTTP/2 | [[ne]] | celá platforma Upgates jede na HTTP/1.1, nedá se změnit |
| Bezpečnostní hlavičky | [[částečně]] | X-Frame-Options, nosniff, HSTS ano. Chybí `Referrer-Policy`, dá se doplnit v SEO nastavení v poli *HTTP hlavičky* (`Referrer-Policy:strict-origin-when-cross-origin`) |
| Obrázková sitemapa | [[prázdná]] | sitemapa produktů deklaruje `image:` jmenný prostor, ale neobsahuje ani jeden obrázek |
| llms.txt | [[ne]] | `/llms.txt` vrací prázdnou stránku s kódem 200 (viz GEO) |

### [[střední]] Nestabilní odezva serveru

Při procházení 1 009 stránek (6 souběžných požadavků) byl medián odezvy **0,73 s**, 90 % stránek do **2,0 s**, ale jednotlivé stránky trvaly **8–26 s** (`/darkove-balicky` 8,8 s, `/p/cerny-caj` 22,9 s, `/p/bylinny-ajurvedsky-balzam` 19,4 s). Lighthouse naměřil TTFB 1,9 s (úvodka), 2,4 s (produkt) a 7,7 s (kategorie). Google při pomalých odpovědích zpomaluje procházení. Tisíce filtračních stránek k tomu přispívají, protože robot spotřebovává výkon serveru na stránky, které nejsou potřeba.

> **Oprava:** odstranění filtrů z indexu pomůže nepřímo. Konkrétní časy s URL poslat podpoře Upgates (už se to řešilo, viz poznámky k HTTP/1.1: svetcejlonu je 3–4× pomalejší než jiné Upgates e-shopy).

## Rychlost (Core Web Vitals)

Laboratorní měření Lighthouse 12, mobil se simulovaným zpomalením, jeden běh 24. 9. 2026. Terénní data od uživatelů (CrUX) nejsou k dispozici: web má málo návštěv a bez Search Console je nevidíme.

| Stránka | Výkon | SEO | Přístupnost | FCP | LCP | TBT | CLS | Přeneseno |
|---|---|---|---|---|---|---|---|---|
| Úvodka | –* | 100 | 95 | 3,8 s | –* | –* | 0,101 | 2,9 MB |
| Produkt (ibišek) | 47 | 100 | 94 | 3,5 s | 11,9 s | 460 ms | 0,074 | 2,0 MB |
| Kategorie (čaje) | –* | 100 | 95 | 3,0 s | –* | –* | 0,145 | 1,3 MB |

\* Lighthouse u úvodky a kategorie nezachytil LCP (pravděpodobně kvůli najíždějícím animacím a cookie liště), proto chybí i celkové skóre výkonu. Hodnoty z 22. 9. (jiné měření, jiná síť): LCP 1,9 s, CLS 0,083, TBT 459 ms.

### [[střední]] Co brzdí

- **Obrázky:** na produktu lze ušetřit ~880 kB modernějším formátem a ~680 kB správnou velikostí (*modern-image-formats*, *uses-responsive-images*). Připravené optimalizované obrázky čekají v `podklady/optimalizace/`
- **Měřicí kódy třetích stran** (GA4, Facebook pixel, Clarity, widget Heureky) blokují hlavní vlákno ~420 ms
- **CLS 0,145 na kategorii** je nad hranicí 0,1: 3 posuny rozvržení
- **DOM 2 000–3 100 prvků**: skryté bloky (aktuality, ukázková data) se vykreslují a pak skrývají
- blokující CSS a JS: ~530 ms na produktu

## Obrázky

### [[zlepšeno]] Alt texty jsou doplněné

Na `/o-nas` 22. 9. chybělo 15 altů, dnes na všech 1 009 stránkách chybí shodně jen 2, a ty jsou technické: sledovací pixel Facebooku v `<noscript>` (správně) a prázdný `<img class="tt-image">` ze šablony (tooltip). Ten druhý stačí doplnit `alt=""`.

### [[nízké]] Názvy souborů a obrázky ve vyhledávání

Produktové fotky se jmenují `9.jpg`, `13.jpg`, `kark.jpg`. Pro Google Obrázky (u čaje a koření to není zanedbatelný kanál) je lepší `cejlonska-skorice-cela-alba.jpg`. Při příští výměně fotek (optimalizace do WebP/JPEG) je rovnou pojmenovat.

## Obsah a klíčová slova

Produktové texty jsou nadprůměrné: konkrétní, s osobní zkušeností ze Srí Lanky („Zkušenost z praxe“), s původem i přípravou. Slabé místo jsou **kategorie** a **úplná absence informačního obsahu**. Přitom právě informační dotazy tvoří většinu hledání v tomhle oboru a právě z nich AI skládá odpovědi.

Hledanosti nejsou v auditu uvedené, protože bez Search Console, Skliku nebo Google Ads je nelze poctivě změřit. Priority níže vycházejí z tématu a z toho, co vyhledávače a AI dnes ukazují.

### Mapa klíčových slov → stránka

| Téma / dotaz | Cílová stránka | Stav | Co chybí |
|---|---|---|---|
| cejlonský čaj, sypaný čaj ze Srí Lanky | `/caje` | [[slabé]] | titulek „ČAJE“, úvodní text o cejlonském čaji, oblasti (Nuwara Eliya, Uva, Kandy…), třídy listů |
| cejlonská skořice, skořice Alba, pravá skořice | `/p/skorice-cela-cejlonska`, `/p/skorice-mleta-cejlonska` | [[slabé]] | srovnání s kasií, kumarin, třídy (Alba, C5…). Konkurence přesně tohle cituje v AI odpovědích |
| srílanské koření, kari listy, thuna paha | `/koreni` | [[slabé]] | text kategorie, recepty (srílanské kari), odkazy mezi kořením |
| modrý čaj, butterfly pea, motýlí hrachor | `/p/motyli-kvet` | [[dobré]] | 7 700 slov včetně recenzí. Doplnit FAQ (mění barvu s citronem?) |
| ibiškový čaj, ibišek účinky | `/p/ibisek` | [[dobré]] | 25 recenzí, silný text. FAQ + příprava za studena |
| moringa, amla, ashwagandha, triphala | `/doplnky-stravy` + produkty | [[pozor]] | YMYL téma: potřebuje zdroje a opatrné formulace (viz E-E-A-T) |
| ájurvédská kosmetika, ájurvédské mýdlo, Chandanalepa | `/ajurveda` | [[slabé]] | text kategorie, vysvětlení srílanských značek a receptur, které e-shop prodává |
| dárek čajovému nadšenci, dárkový balíček čaje | `/darkove-balicky` | [[slabé]] | 8,8 s odezva, 431 slov, žádný popis kategorie |
| jak louhovat sypaný čaj, kolik gramů na šálek | chybí | [[nic]] | návod v Rádci s odkazy na produkty |
| čaj ze Srí Lanky přímo od farmářů, fair trade čaj | `/o-nas` | [[slabé]] | titulek „O NÁS“. Jmenovat konkrétní farmy, regiony a fotky |

### [[vysoké]] Kategorie nemají vlastní úvodní text

Stránka kategorie má 1 500–2 200 slov, ale skoro všechno jsou karty produktů a společné bloky (příběh, recenze, newsletter), které jsou stejné na každé stránce. Vlastní text o kategorii chybí. Google a AI tak nemají z čeho poznat, že `/caje` je *stránka o cejlonském čaji*.

> **Oprava:** 150–300 slov na kategorii (popis kategorie v administraci; po redesignu se vykresluje pod produkty, takže nezavazí): co je cejlonský čaj / koření, odkud přesně, jak se liší od běžného, 3–5 otázek a odpovědí. Klient dodá fakta, text můžeme připravit.

### [[střední]] Duplicitní opakované bloky

Bloky „Náš příběh“, „Pomáháme škole“, „Co říkají zákazníci“, pruh výhod a newsletter jsou na každé kategorii i úvodce. Samo o sobě to nevadí, ale v kombinaci s chybějícím vlastním textem tvoří opakovaný obsah většinu textu stránky.

## Důvěryhodnost (E-E-A-T) a YMYL

Google hodnotí zkušenost, odbornost, autoritu a důvěryhodnost. U témat, která se týkají zdraví (doplňky stravy, ájurvédské masti a balzámy), přísněji. Stejné signály používají AI asistenti, když vybírají, koho citovat.

### Silné stránky

- autentický příběh zakladatele a život na Srí Lance (`/o-nas`, 1 051 slov)
- sekce „Zkušenost z praxe“ u produktů: přesně ten typ vlastní zkušenosti, který Google i AI oceňují
- ověřené recenze u produktů (ibišek 25×) a certifikát **Ověřeno zákazníky** na Heurece
- charitativní projekt (tamilská škola), který je konkrétní a ověřitelný
- obchodní podmínky se jménem, sídlem a IČO (06451420)

### Slabiny

- **chybí stránka Kontakt** (`/kontakt` = 404). Firma, adresa a IČO jsou jen ve VOP, adresa v patičce je schovaná
- VOP jsou na `/vse-o-nakupu` s titulkem „OBCHODNÍ PODMÍNKY“
- u produktů chybí autor nebo odborník, který text napsal
- nesmyslný ukázkový obsah (viz výše) přímo podkopává důvěryhodnost
- „10 000+ spokojených zákazníků“ je tvrzení, které na webu nic nedokládá

### [[vysoké]] Zdravotní tvrzení u doplňků a ájurvédy

Názvy a texty jako **„Ájurvédský balzám (Bolest hlavy)“**, **„Beamfiel mast – plísňové a jiné kožní potíže“**, **„Bylinná první pomoc (Zuby a dásna)“** nebo „rychlá úleva“ jsou u potravin a doplňků stravy regulované (nařízení EU 1924/2006 o zdravotních tvrzeních), u kosmetiky nesmí produkt slibovat léčebný účinek. Kromě právního rizika (SZPI, ČOI) to u Googlu spouští přísnější hodnocení YMYL a AI asistenti takové zdroje necitují.

> **Doporučení:** nechat projít odborníkem na regulaci. Z hlediska SEO: formulovat tradičním použitím („na Srí Lance se tradičně používá při…“), uvádět složení a zdroj, v názvu produktu nemít diagnózu. *Tohle není právní posudek.*

Co přidat:

stránku

Kontakt

(firma, IČO, e-mail, telefon, fakturační adresa, případně výdejní místo), krátký medailonek „Kdo za tím stojí“ s fotkou na

/o-nas

a podpis autora s jeho vztahem ke Srí Lance (například „Text: Marek Bezdíček, zakladatel“) pod návody v Rádci.

## Katalogy, recenze a odkazy

| Místo | Stav | Co udělat |
|---|---|---|
| **Firmy.cz** (Seznam) | [[duplicita]] | existují **dva** záznamy: „Svetcejlonu.cz“ a „Svět Cejlonu“ (oba IČO 06451420). Sloučit / jeden zrušit, doplnit popis, fotky, odkaz na web, otevírací dobu (nebo „jen e-shop“) |
| **Google Business Profile** | [[neověřeno]] | založit jako firmu bez provozovny (oblast působnosti ČR). Recenze tam vidí Google i Gemini |
| **Heureka** | [[aktivní]] | certifikát Ověřeno zákazníky. Pole `heureka_id` u produktů je prázdné, feed produktů zapojit (hodnocení produktů, srovnávač) |
| **Zboží.cz** | [[ne]] | feed je v Upgates připravený. Zboží.cz je zdroj dat i pro Seznam |
| **Google Merchant Center** | [[neověřeno]] | bezplatné výpisy produktů v Google Nákupech a v AI Mode. Feed z Upgates |
| Instagram, Facebook | [[ano]] | propojit přes `sameAs` (JSON-LD výše) |
| YouTube | [[ne]] | videa ze Srí Lanky (už se natáčejí do reklam) nahrát na YouTube. AI a Google je citují, video pod produktem je už připravené |

### Zpětné odkazy: kde je získat

Bez placeného nástroje (Ahrefs, Semrush, Marketing Miner) ani Search Console nejde odkazový profil změřit. Tyhle zdroje dávají smysl tematicky:

- **Projekt tamilské školy:** tisková zpráva, spolupráce s neziskovkami a krajanskými spolky. Takové příběhy média ráda přebírají
- **Cestovatelské blogy o Srí Lance**: „co si přivézt ze Srí Lanky“ a „kde koupit v ČR“
- **Velkoobchod:** čajovny, kavárny a restaurace, které odebírají, ať uvádějí „čaj od Svět Cejlonu“ s odkazem
- **Food blogeři a recepty**: srílanské kari, chai, zlaté mléko s kurkumou
- ájurvédští terapeuti a jógová studia (vonné tyčinky, oleje)

## GEO: viditelnost v AI asistentech

**GEO (Generative Engine Optimization)** je optimalizace pro to, aby e-shop zmiňovaly a citovaly ChatGPT, Perplexity, Google AI Overviews / AI Mode, Gemini, Claude a Copilot. Základ je stejný jako u SEO. AI asistenti ale hledají přes indexy vyhledávačů (hlavně Bing a Google), dávají přednost konkrétním ověřitelným faktům a potřebují jasně rozpoznat značku jako entitu.

### Test: jak dnes AI vyhledávání vidí Svět Cejlonu

Zkušební dotazy přes webové vyhledávání, které používají AI nástroje (24. 9. 2026):

| Dotaz | svetcejlonu.cz | Koho AI zmínila místo toho |
|---|---|---|
| „Svět Cejlonu“ čaj | [[nezmíněn]] | Wikipedie, cajovebedynky.cz, vitie.cz, manutea.cz, darka-shop.cz, cajovydychanek.cz |
| cejlonský sypaný čaj e-shop Srí Lanka přímo od farmářů | [[nezmíněn]] | dobracajovna.com, cajovydychanek.cz, day-spa-shop.cz, cejlonskycaj.cz, prodejcaje.cz |
| kde koupit pravou cejlonskou skořici Alba | [[nezmíněn]] | bylik.cz, pravavanilka.cz, **cejlonskekoreni.cz** (citován „testovaný obsah kumarinu ~12 mg/kg“), vanilkovyobchod.cz |
| e-shop srílanské koření a ájurvédské produkty | [[nezmíněn]] | ayurshop.cz, ajurv.eu, **zdravizesrilanky.cz** (AI ho označila za „nejspecializovanější“) |
| svetcejlonu.cz | [[ano]] | odpověď složená z **Firmy.cz** a stránky varianty `/p/lotovovy-kvet/203` |

> **Co z toho plyne:** AI zná e-shop jen tehdy, když se na něj zeptáte přesnou doménou, a i tehdy bere informace z katalogu a z variantní stránky. Na obecné dotazy doporučuje konkurenci. Ta nemá lepší produkty, ale má **citovatelné konkrétní údaje** (kumarin, třída Alba/C5, oblast původu) a srozumitelnou specializaci v titulku a úvodním textu.

### GEO checklist

| Faktor | Stav | Poznámka |
|---|---|---|
| AI roboti mají přístup | [[částečně]] | GPTBot a ClaudeBot dostávají 503, ostatní projdou (viz níže) |
| Obsah čitelný bez JavaScriptu | [[ano]] | AI roboti většinou JavaScript nespouštějí. Tady to nevadí |
| Web v indexu Bingu | [[neověřeno]] | bez Bing Webmaster Tools nevíme, co v indexu je. Bing krmí ChatGPT Search a Copilot |
| Značka jako entita (`Organization` + `sameAs`) | [[ne]] | LocalBusiness s názvem „Marek Bezdíček“ AI spíš mate |
| Jednotné údaje o firmě na webu (NAP) | [[ne]] | 2× Firmy.cz, jméno osoby vs. značka, žádná stránka Kontakt |
| Konkrétní, citovatelná fakta | [[částečně]] | v produktech jsou (Namanukule u Elly, GS-1, Pekoe), ale ztrácejí se v emoji a formátování |
| Otázky a odpovědi (FAQ) | [[ne]] | AI odpovídá na otázky, takže obsah ve tvaru otázka–odpověď cituje nejsnáz |
| Srovnávací a vysvětlující obsah | [[ne]] | Rádce obsahuje jen ukázkovou „brož“ |
| Recenze a zmínky mimo web | [[částečně]] | Heureka ano. Chybí články, blogy, fóra a YouTube |
| Aktuálnost (datum úpravy) | [[částečně]] | `lastmod` v sitemapě ano. Na stránkách chybí viditelné „aktualizováno“ |
| Měření návštěv z AI | [[ne]] | viz skupina kanálů v GA4 výše |

## AI roboti a přístup na web

Server odpověděl na požadavek produktu `/p/ibisek` a `/robots.txt` se 14 různými identifikacemi robotů. **Dva nejdůležitější trénovací roboti dostávají chybu 503, a to i na `robots.txt`.** Blokace je na úrovni platformy Upgates, ne v našem `robots.txt`.

| Robot | Kdo / k čemu | Odpověď |
|---|---|---|
| `GPTBot` | OpenAI, obsah pro trénink modelů (co ChatGPT „ví“ bez vyhledávání) | [[503]] |
| `ClaudeBot` | Anthropic, obsah pro trénink modelů Claude | [[503]] |
| `OAI-SearchBot` | OpenAI, vyhledávání v ChatGPT | [[200]] |
| `ChatGPT-User` | OpenAI, stránka otevřená na žádost uživatele | [[200]] |
| `Claude-SearchBot` | Anthropic, vyhledávání v Claude | [[200]] |
| `PerplexityBot` | Perplexity, index vyhledávání | [[200]] |
| `Google-Extended` | Google, Gemini (řídí se v robots.txt) | [[200]] |
| `Googlebot`, `bingbot`, `SeznamBot` | klasické vyhledávače (Googlebot krmí i AI Overviews) | [[200]] |
| `Applebot`, `Amazonbot`, `CCBot`, `meta-externalagent`, `Bytespider` | Apple Intelligence, Alexa, Common Crawl (zdroj mnoha modelů), Meta AI, ByteDance | [[200]] |

### [[vysoké]] GPTBot a ClaudeBot jsou zablokované platformou

Když se někdo zeptá ChatGPT nebo Clauda *bez* zapnutého vyhledávání, model odpovídá z toho, co se naučil. Obsah Světa Cejlonu se do toho nedostane. Vyhledávací roboti (OAI-SearchBot, Claude-SearchBot) projdou, takže odpovědi s vyhledáváním jsou možné. Obecná znalost značky v modelech ale chybí.

> **Oprava:** napsat podpoře Upgates. Jestli jde o globální pravidlo platformy, zeptat se, zda jde pro tento e-shop vypnout. Robots.txt to nevyřeší, protože roboti dostanou 503 dřív, než ho vůbec přečtou. Návrh e-mailu:
>
> *„Dobrý den, na doméně www.svetcejlonu.cz vrací server kód 503 robotům s user-agentem GPTBot a ClaudeBot (i na /robots.txt), ostatním robotům 200. Jde o globální blokaci na úrovni platformy? Můžete ji pro náš e-shop vypnout? Přístup robotů chceme řídit sami přes robots.txt. Zároveň se chci zeptat: lze u variant produktů (/p/…/ID) a filtračních stránek nastavit kanonickou adresu na hlavní produkt / kategorii? Děkuji.“*

### Doporučený doplněk do `robots.txt`

V *Nastavení → Rozšířené → SEO* se dá `robots.txt` upravit. Explicitní povolení AI robotů je hlavně signál; dnes je nic neblokuje, ale jasné pravidlo pomůže, kdyby Upgates blokaci zrušil:

```
User-agent: GPTBot
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: ClaudeBot
User-agent: Claude-SearchBot
User-agent: PerplexityBot
User-agent: Google-Extended
User-agent: Applebot-Extended
Allow: /
Disallow: /cart
Disallow: /checkout
Disallow: /shipment
Disallow: /summary
Disallow: /*?do=
Disallow: /*?_fid=
```

Robot se řídí jen nejkonkrétnější skupinou `User-agent`, proto se v ní musí zopakovat zákazy košíku a parametrů.

### [[nízké]] llms.txt

`/llms.txt` je navrhovaný standard, přehled webu pro AI v Markdownu. Na Upgates ho do kořene domény nahrát nejde (adresa dnes vrací prázdnou stránku s kódem 200) a žádný z velkých AI vyhledávačů zatím potvrzeně nepoužívá. **Nedoporučuji tím ztrácet čas.** Stejnou službu udělá dobře napsaná stránka `/o-nas` + JSON-LD Organization. Kdyby Upgates nahrání souboru do kořene umožnil, připravíme ho za 15 minut.

## Obsah, který AI cituje

AI asistenti skládají odpověď z krátkých, samostatně srozumitelných pasáží. Nejčastěji citují stránky, které na otázku odpovídají **přímo v první větě**, uvádějí **čísla a zdroje** a mají jasnou strukturu (nadpis → odpověď → detail, tabulky, seznamy).

### 1. Rádce: 10 článků s nejvyšší šancí na citaci

| # | Téma (titulek) | Proč | Odkazuje na |
|---|---|---|---|
| 1 | Cejlonská skořice vs. kasie: jak poznat pravou skořici a proč na tom záleží (kumarin) | přesně tohle dnes AI cituje od konkurence | skořice celá, mletá, skořicový čaj |
| 2 | Třídy cejlonské skořice: Alba, Continental, C5, M5… co znamenají | faktický, tabulkový obsah | skořice |
| 3 | Cejlonský čaj: oblasti, nadmořské výšky a chuť (Nuwara Eliya, Uva, Dimbula, Kandy, Ruhuna) | dotaz „cejlonský čaj“ dnes vyhrává Wikipedie | kategorie čaje, černý čaj Pekoe |
| 4 | Co znamenají zkratky OP, Pekoe, BOP, BOPF, GS-1 na čaji | konkrétní, časté otázky | černý, zelený, kardamonový čaj |
| 5 | Jak správně louhovat sypaný čaj: teplota, čas, gramy (tabulka pro každý druh) | ideální formát pro AI | louhovače, konvice, všechny čaje |
| 6 | Modrý čaj (butterfly pea): proč mění barvu a jak ho připravit | vizuální, virální téma | motýlí květ |
| 7 | Srílanské kari doma: základní koření a recept s Thuna Paha | recepty přitahují odkazy | kari listy, thuna paha, kardamon, pepř |
| 8 | Moringa, amla, ashwagandha: co to je a jak se tradičně používají (s odkazy na studie) | YMYL, potřebuje zdroje. O to větší hodnota | doplňky stravy |
| 9 | Co přivézt ze Srí Lanky (a co si můžete koupit i v Česku) | cestovatelské dotazy, přirozené odkazy od blogů | celý sortiment |
| 10 | Jak vybíráme farmáře: návštěva plantáže v … (konkrétní místo, fotky, data) | vlastní zkušenost = E-E-A-T | o nás, škola |

Rádce je v Upgates určený přesně k tomu (*Obsah → Rádce*) a dá se připojit ke kategoriím, takže se článek zobrazí i u produktů.

### 2. Úprava produktových textů pro AI

- **První věta = definice**: „Ibišek (Hibiscus sabdariffa) je sušený květ ze Srí Lanky, ze kterého vzniká rubínově červený, kyselkavý čaj bez kofeinu.“ Ne „🌺 Ibišek“.
- **Tabulka faktů** ke každému produktu: původ (oblast, obec), nadmořská výška, sklizeň, třída, obsah kofeinu, doporučené dávkování a teplota. Parametry už existují, stačí je vypsat jako tabulku (dnes se vykreslují jako chuťový profil)
- **3–5 otázek a odpovědí** na konci popisu („Obsahuje kofein?“, „Kolik gramů na šálek?“, „Dá se louhovat vícekrát?“)
- emoji a řádky podtržítek přesunout z textu do designu. AI i Google je čtou jako šum

### 3. Stránka „O nás“ jako zdroj pravdy o firmě

AI bere fakta o firmě odtud a z katalogů. Na `/o-nas` by měla být jedním odstavcem a bez metafor: *kdo* (jméno, od kdy), *co* (sortiment), *odkud* (konkrétní regiony a farmy), *jak* (ručně baleno v ČR, malé šarže), *čím se liší* a *ověřitelná čísla* (počet zákazníků, recenze na Heurece, podpora školy od roku…).

### 4. Zmínky mimo web

Perplexity i ChatGPT Search často citují srovnání a doporučení z jiných webů („nejlepší e-shopy s cejlonským čajem“). Cílem je dostat se do takových článků, na fóra (Reddit r/czech, Modrý koník, Facebook skupiny o čaji a ájurvédě) a do cestovatelských blogů. Viz odkazy.

## Akční plán

Úkoly z auditu jsou rozepsané ve sdíleném checklistu. Odkaz na něj je v hlavičce této stránky. U každého úkolu je vysvětlené, co a kde udělat, a hotové úkoly se dají odškrtnout.

## Cenová nabídka

Nabídka má dvě části. Nejdřív jednorázový **úklid**, který opraví chyby, jež web dnes poškozují. Pak **měsíční péče**, ve které postupně přibývá obsah a každý měsíc je vidět, co se změnilo. Ceny jsou bez DPH.

### SEO úklid: 4 900 Kč jednorázově

- Google Search Console, Bing Webmaster Tools, Seznam Webmaster, odeslání sitemap
- 1 786 filtračních stránek a 104 variant pryč z indexu a ze sitemapy
- smazání ukázkového obsahu Upgates (aktuality, rádce, výrobce), noindex pomocných stránek
- `robots.txt` pro AI roboty a dotaz na Upgates kvůli zablokovaným GPTBot a ClaudeBot
- titulky a popisky úvodky, kategorií a obsahových stránek
- šablona: JSON-LD o firmě, oprava hodnocení a recenzí pro hvězdičky ve vyhledávání, Open Graph, alt loga, nadpisy
- stránka Kontakt, opravy víc H1 a překlepů v produktech
- GA4: propojení se Search Console a sledování návštěv z AI asistentů

Hotovo do týdne od dodání přístupu k DNS domény.

### SEO a AI péče: 1 900 Kč měsíčně

Minimálně 3 měsíce.

- **2 odborné články do Rádce** měsíčně (cejlonská skořice vs. kasie, oblasti čaje, louhování…) nebo text kategorie
- **fakta a FAQ u 5 produktů** měsíčně, začíná se nejprodávanějšími
- SEO titulek a popisek pro každý nový produkt
- kontrola indexace a chyb v Search Console
- **krátký měsíční report**: návštěvy z Googlu a Seznamu, dotazy, na které se web zobrazuje, návštěvy z AI a jestli ChatGPT a Perplexity e-shop doporučují

Výsledky SEO se projevují po 2–3 měsících, proto minimální délka 3 měsíce. Pak lze kdykoliv ukončit.

> **První 3 měsíce celkem: 10 600 Kč** (4 900 Kč úklid + 3 × 1 900 Kč péče). Za tu dobu přibude 6 článků nebo textů kategorií a fakta s FAQ u 15 produktů. To je obsah, podle kterého web začnou nacházet Google i AI asistenti.

### Na straně klienta

- přidat DNS záznam pro Search Console (stačí poslat přístup, zabere to 5 minut)
- sloučit dva záznamy na Firmy.cz, založit Google Business Profile (s návodem)
- dodat fakta k produktům: oblast původu, farmy, sklizeň, osobní zkušenosti
- nechat odborníka zkontrolovat zdravotní tvrzení u ájurvédy a doplňků

### Volitelně

| Úkol | Cena |
|---|---|
| Feedy Zboží.cz a Google Merchant Center (produkty v srovnávačích a Google Nákupech) | 800 Kč |
| Nahrání optimalizovaných fotek (připravené v `podklady/optimalizace/`) se SEO názvy souborů | 800 Kč |
| Článek do Rádce navíc nad rámec péče | 600 Kč / ks |

## Návrhy titulků a popisků

> **Nejdřív ověřit:** vyplnit SEO titulek u jedné kategorie a podívat se do zdroje stránky. Pokud Upgates přilepí `:: Svět Cejlonu` sám, vynechat „| Svět Cejlonu“ z návrhů. Popisky do 155 znaků, titulky do ~60.

| Stránka | Titulek | Popisek |
|---|---|---|
| **Úvodka** | Cejlonský čaj, koření a ájurvéda ze Srí Lanky \| Svět Cejlonu | Sypané cejlonské čaje, pravá cejlonská skořice a ájurvédské produkty od menších pěstitelů ze Srí Lanky. Ručně baleno v ČR, dárek ke každé objednávce. |
| `/caje` | Cejlonské sypané čaje ze Srí Lanky \| Svět Cejlonu | Černé, zelené, bílé i bylinné čaje z horských oblastí Srí Lanky. Celé listy, známý původ a chuť, kterou v sáčku nenajdete. |
| `/koreni` | Cejlonská skořice a srílanské koření \| Svět Cejlonu | Pravá cejlonská skořice Alba, kardamon, hřebíček, kurkuma a kari listy přímo od srílanských pěstitelů. Bez příměsí, v malých šaržích. |
| `/doplnky-stravy` | Moringa, amla a ashwagandha ze Srí Lanky \| Svět Cejlonu | Tradiční srílanské byliny a plody v jemně mleté podobě: moringa, amla, ashwagandha, triphala. Přírodní složení, známý původ. |
| `/ajurveda` | Ájurvédská kosmetika a péče ze Srí Lanky \| Svět Cejlonu | Ájurvédská mýdla, oleje, balzámy a zubní pasty podle tradičních srílanských receptur. Dovezeno přímo z ostrova. |
| `/ostatni` | Čajové příslušenství a vonné tyčinky \| Svět Cejlonu | Skleněné louhovače a konvice, louhovací sáčky, dřevěné podtácky a přírodní vonné tyčinky ze Srí Lanky. |
| `/darkove-balicky` | Dárkové balíčky čaje a koření ze Srí Lanky \| Svět Cejlonu | Sestavte dárek z cejlonských čajů, koření a ájurvédy v dárkové krabici. Ideální pro milovníky čaje i exotické kuchyně. |
| `/o-nas` | Náš příběh: čaj a koření přímo ze Srí Lanky \| Svět Cejlonu | Jak vznikl Svět Cejlonu, proč nakupujeme přímo od menších farmářů a co to znamená pro to, co si nasypete do šálku. |
| `/pomahame-skole-na-sri-lance` | Pomáháme tamilské škole na Srí Lance \| Svět Cejlonu | Část z každé objednávky jde na tamilskou školu na Srí Lance. Co se z vašich nákupů už pořídilo a jak projekt pokračuje. |
| `/velkoobchod-a-spoluprace` | Velkoobchod cejlonského čaje a koření \| Svět Cejlonu | Cejlonské čaje, skořici a koření dodáváme čajovnám, kavárnám, restauracím i obchodům. Napište si o velkoobchodní ceník. |
| `/vse-o-nakupu` | Doprava, platba a obchodní podmínky \| Svět Cejlonu | Jak u nás nakoupit, možnosti dopravy a platby, doprava zdarma od 1 600 Kč, reklamace a obchodní podmínky. |

### Vzor pro produkty (příklady)

| Produkt | Titulek | Popisek (1. věta krátkého popisu) |
|---|---|---|
| Ibišek | Ibiškový čaj – sušený květ ibišku ze Srí Lanky \| Svět Cejlonu | Sušené květy ibišku ze Srí Lanky na rubínově červený, osvěžující čaj bez kofeinu. Výborný horký i ledový, 20–100 g. |
| Skořice celá | Pravá cejlonská skořice Alba celá \| Svět Cejlonu | Pravá cejlonská skořice nejvyšší třídy Alba: tenké křehké svitky s jemně sladkým aroma a minimem kumarinu. Přímo ze Srí Lanky. |
| Motýlí květ | Modrý čaj z motýlího květu (butterfly pea) \| Svět Cejlonu | Bylinný čaj z květů motýlího hrachoru, který se s citronem zbarví z modré do fialové. Bez kofeinu, ze Srí Lanky. |
| Černý čaj Pekoe | Cejlonský černý čaj Pekoe sypaný \| Svět Cejlonu | Klasický cejlonský černý čaj třídy Pekoe z vybraných oblastí Srí Lanky. Plná, čistá chuť, ideální k snídani i s mlékem. |
| Chilli | Srílanské chilli mleté \| Svět Cejlonu | (dnes bez popisku) Pálivé srílanské chilli…, doplní klient podle skutečnosti. |

## Příloha: stav všech 59 produktů

Tučně jsou problémové hodnoty: popisek chybí nebo je delší než 160 znaků, víc než jeden H1, odezva nad 3 s.

| Produkt | Titulek | Popisek | Popisek: problém | H1 | Slov | Odezva |
|---|---|---|---|---|---|---|
| AMLA - MLETÁ (indický angrešt) | 46 | **256** | dlouhý, zalomení | 1 | 1116 | 0.7 s |
| ASHWAGANDHA | 27 | **199** | dlouhý, zalomení | 1 | 825 | 1.0 s |
| Ayush - pleťový krém | 36 | **230** | dlouhý | **5** | 734 | 0.3 s |
| Beamfiel mast (Problémová pokožka) | 50 | **234** | dlouhý | 1 | 664 | 0.9 s |
| BELI MAL – Sušený květ | 38 | **188** | dlouhý, emoji, zalomení | 1 | 1561 | 0.5 s |
| Bylinná mast (Svaly a klouby) | 45 | **278** | dlouhý | 1 | 986 | 1.0 s |
| Bylinná první pomoc (Zuby a dásna) | 50 | **283** | dlouhý | 1 | 815 | 0.7 s |
| Bylinný balzám (Svaly a klouby) | 47 | **314** | dlouhý | 1 | 901 | **19.4 s** |
| BÍLÝ ČAJ - Silver tips | 38 | **312** | dlouhý, emoji, zalomení | 1 | 1388 | 0.9 s |
| CHILLI | 22 | **22** | chybí (kopie titulku) | 1 | 549 | 0.3 s |
| CITRONOVÁ TRÁVA | 31 | 126 | zalomení | 1 | 1244 | 0.4 s |
| Dárková krabice malá (27 × 19,5 cm) | 51 | **51** | chybí (kopie titulku) | 1 | 55 | 0.6 s |
| Dárková krabice velká (30 × 30 cm) | 50 | **50** | chybí (kopie titulku) | 1 | 55 | 0.5 s |
| Dřevěný podtácek | 32 | 125 | – | 1 | 425 | 0.7 s |
| EARL GREY - Černý čaj | 37 | **232** | dlouhý, zalomení | 1 | 707 | 0.5 s |
| GARAM MASALA - SMĚS KOŘENÍ | 42 | **195** | dlouhý, zalomení | 1 | 560 | 0.7 s |
| HŘEBÍČEK CELÝ | 29 | 124 | emoji, zalomení | 1 | 610 | 1.0 s |
| HŘEBÍČKOVÝ - Černý čaj | 38 | **185** | dlouhý, emoji, zalomení | 1 | 862 | 1.1 s |
| IBIŠEK – Řezaný květ | 36 | **237** | dlouhý, emoji, zalomení | 1 | 2180 | 0.7 s |
| JASMÍNOVÝ - Zelený čaj | 38 | **197** | dlouhý, emoji, zalomení | 1 | 713 | 0.6 s |
| KAKAO CEJLONSKÉ | 31 | 137 | – | 1 | 990 | 0.4 s |
| KARDAMON - Černý čaj s kardamonem | 49 | 131 | – | **2** | 644 | 1.2 s |
| KARDAMON CELÝ | 29 | 154 | emoji, zalomení | 1 | 669 | 0.5 s |
| KARI LISTY | 26 | **289** | dlouhý, emoji, zalomení | 1 | 586 | 0.4 s |
| KARI SRÍLANSKÉ (Thuna Paha) | 43 | **195** | dlouhý, zalomení | 1 | 547 | 0.4 s |
| KURKUMA - MLETÁ | 31 | 154 | emoji, zalomení | 1 | 769 | 0.6 s |
| LOTOSOVÝ KVĚT - Modrý lotos | 43 | **250** | dlouhý, emoji, zalomení | 1 | 1051 | 0.9 s |
| Louhovací sáčky | 31 | **264** | dlouhý, zalomení | 1 | 686 | 0.9 s |
| MANGOSTAN - ČERNÝ ČAJ | 37 | **163** | dlouhý, zalomení | 1 | 942 | 0.4 s |
| MARAKUJA - Zelený čaj | 37 | **180** | dlouhý, emoji, zalomení | 1 | 799 | 0.5 s |
| MASALA ČAJ - směs | 33 | **238** | dlouhý, zalomení | 1 | 1046 | 0.3 s |
| MODRÝ ČAJ – Motýlí květ | 39 | **215** | dlouhý, emoji, zalomení | 1 | 7710 | 0.7 s |
| MORINGA - MLETÁ | 31 | **217** | dlouhý, zalomení | 1 | 1492 | 0.8 s |
| Navratna - bylinný ájurvédský olej | 50 | **196** | dlouhý | **2** | 548 | 0.7 s |
| PEPŘ - CELÝ | 27 | **248** | dlouhý, zalomení | 1 | 554 | 0.4 s |
| Podložka na vonné tyčinky | 41 | **162** | dlouhý, zalomení | 1 | 353 | 0.9 s |
| Přenosná čajová sada | 36 | **36** | chybí (kopie titulku) | 1 | 151 | 0.2 s |
| Přírodní olej z černuchy seté (Black seeds) | 59 | **292** | dlouhý | 1 | 718 | **8.4 s** |
| RARANAVARA – Čistý květ | 39 | **239** | dlouhý, zalomení | 1 | 1447 | 0.8 s |
| Samahan - Bylinný nápoj | 39 | **39** | chybí (kopie titulku) | 1 | 394 | 0.7 s |
| Skleněná konvice s louhovačem 1L | 48 | 108 | zalomení | 1 | 427 | 0.7 s |
| Skleněný louhovač | 33 | **33** | chybí (kopie titulku) | 1 | 1996 | 0.7 s |
| SKOŘICE CELÁ (CEJLONSKÁ) | 40 | **211** | dlouhý, zalomení | 1 | 1001 | 1.0 s |
| SKOŘICE MLETÁ (CEJLONSKÁ) | 41 | **234** | dlouhý, zalomení | 1 | 1467 | 1.1 s |
| SKOŘICOVÝ - Černý čaj | 37 | 146 | zalomení | 1 | 950 | 0.7 s |
| SOURSOP - ZELENÝ ČAJ | 36 | **258** | dlouhý, zalomení | 1 | 912 | 0.9 s |
| Supirivicky ájurvédská zubní pasta | 50 | **272** | dlouhý | 1 | 815 | 0.6 s |
| Triphala - Ájurvédský doplněk tří plodů | 55 | **247** | dlouhý | 1 | 671 | 0.7 s |
| Tromlované kameny | 33 | **170** | dlouhý | 1 | 243 | 0.6 s |
| VANILKA - LUSK | 30 | 118 | emoji, zalomení | 1 | 593 | 0.8 s |
| VANILKA - MLETÁ | 31 | 128 | emoji, zalomení | 1 | 865 | 0.6 s |
| Vonné tyčinky - Přírodní | 40 | **270** | dlouhý, zalomení | 1 | 639 | **11.6 s** |
| Vonné tyčinky přírodní - Santalové dřevo | 56 | **288** | dlouhý, zalomení | 1 | 490 | 0.7 s |
| ZELENÝ ČAJ - GS-1 Special | 41 | **314** | dlouhý, emoji, zalomení | 1 | 2225 | 0.7 s |
| ZÁZVOR - MLETÝ | 30 | 138 | emoji, zalomení | 1 | 591 | 0.6 s |
| Ájurvédské mýdlo - 9 bylin | 42 | **264** | dlouhý, zalomení | 1 | 911 | 0.6 s |
| Ájurvédské mýdlo - Cejlonská skořice | 52 | **299** | dlouhý, zalomení | 1 | 873 | 0.3 s |
| Ájurvédský balzám (Bolest hlavy) | 48 | **301** | dlouhý | 1 | 766 | 0.8 s |
| ČERNÝ ČAJ – Ceylon Pekoe | 40 | **285** | dlouhý, zalomení | 1 | 1207 | **22.9 s** |

## Metodika a zdroje

- **Crawl:** vlastní skript (Python + BeautifulSoup), 24. 9. 2026: sitemap index → 6 dílčích sitemap → 1 974 URL. Prošlo se 1 009 stránek (všechny produkty, kategorie, obsahové stránky, vzorek 877 filtrů). U každé se měřil stavový kód, odezva, titulek, popisek, robots, kanonická URL, hreflang, Open Graph, H1–H6, mikrodata, JSON-LD, obrázky a alt, počet slov v `<main>` a odkazy
- **Roboti:** `curl` se 14 user-agenty na `/`, `/robots.txt`, `/caje`, `/p/ibisek`
- **Rychlost:** Lighthouse 12 lokálně (mobil, simulované zpomalení), 1 běh. PageSpeed Insights API mělo vyčerpanou denní kvótu
- **Indexace:** `site:` dotaz na Seznamu. Bing blokuje automatické dotazy, takže bez Webmaster Tools se jeho index ověřit nedá
- **AI viditelnost:** 5 testovacích dotazů přes webové vyhledávání, které používají AI nástroje. Jde o orientační vzorek, ne o systematické měření
- **Administrace:** cesty ověřeny v dokumentaci Upgates ([Filtry a filtrační stránky](https://www.upgates.cz/a/filtry-a-razeni-napoveda), [SEO](https://www.upgates.cz/a/automaticke-seo), [Aktuality](https://www.upgates.cz/a/aktuality), [Rádce](https://www.upgates.cz/a/radce)) a předchozím auditem z 22. 9.
- **Co audit nepokrývá:** skutečné pozice a hledanosti (vyžaduje GSC / Sklik / placený nástroj), zpětné odkazy, obsah za přihlášením, košík a objednávku (blokované v robots.txt)

SEO a GEO audit svetcejlonu.cz · 24. 9. 2026 · Navazuje na

podklady/SEO-audit.md

(22. 9. 2026)
