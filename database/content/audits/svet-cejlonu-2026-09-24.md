## Shrnutí

Technický základ e-shopu je v pořádku: HTTPS, kanonické adresy, obsah vykreslený na serveru a produktová mikrodata. Brzdí ho tři věci.

1. Google dostává 1 974 adres a užitečných je z nich asi 80. Zbytek jsou filtry a varianty produktů.
2. Na webu zůstal ukázkový obsah ze šablony Upgates. Jeden nesmyslný článek už je ve výsledcích Seznamu.
3. Pro AI asistenty je e-shop skoro neviditelný. Roboti OpenAI a Anthropic dostávají od serveru chybu a ve vyhledávání, ze kterého AI čerpá, se na obecné dotazy objevuje konkurence.

### Stav podle oblastí

| Oblast | Stav | Poznámka |
|---|---|---|
| Indexace a sitemapa | [[kritické]] | v indexu jsou filtry a varianty produktů |
| Ukázkový obsah | [[kritické]] | 4 aktuality, návod na brož a výrobce „Upgates“ |
| Měření | [[kritické]] | chybí Search Console, Bing i Seznam Webmaster |
| AI vyhledávání | [[vysoké]] | dva blokovaní roboti, ve výsledcích je konkurence |
| Titulky a popisky | [[vysoké]] | verzálky, popisky jen opakují titulek |
| Obsah | [[vysoké]] | kategorie bez vlastního textu, žádné návody |
| Strukturovaná data | [[střední]] | chyby v recenzích a v údajích o firmě |
| Rychlost | [[střední]] | server občas odpovídá přes 8 s |
| Důvěryhodnost | [[střední]] | chybí stránka Kontakt |
| Technický základ | [[v pořádku]] | HTTPS, přesměrování, stránka 404, kanonické adresy |
| Alt texty | [[v pořádku]] | doplněné od 22. 9. |

> **Od 22. 9. se to zhoršilo.** V sitemapě bylo 414 adres, dnes je jich 1 974. Po hromadném importu parametrů udělal Upgates stránku z každé hodnoty, třeba `/caje/p-tip/s-citronem-zvyrazni-svezest-nalevu`.

## Co udělat nejdřív

Jednotlivé kroky jsou rozepsané v checklistu, odkaz je nahoře na stránce.

| # | Úkol | Dopad |
|---|---|---|
| 1 | Založit Google Search Console, Bing Webmaster Tools a Seznam Webmaster | [[kritický]] |
| 2 | Filtrační stránky nastavit jako „neindexovat“ | [[kritický]] |
| 3 | Vyřadit varianty produktů ze sitemapy | [[kritický]] |
| 4 | Smazat ukázkový obsah Upgates | [[kritický]] |
| 5 | Napsat podpoře Upgates kvůli blokovaným AI robotům | [[vysoký]] |
| 6 | Titulky a popisky úvodky, kategorií a stránek | [[vysoký]] |
| 7 | Opravit údaje o firmě a recenze ve strukturovaných datech | [[vysoký]] |
| 8 | Úvodní text a otázky v každé kategorii | [[vysoký]] |
| 9 | Návody do Rádce | [[vysoký]] |
| 10 | Sloučit Firmy.cz a ověřit Google Business Profile | [[střední]] |
| 11 | Projít zdravotní tvrzení u ájurvédy a doplňků | [[střední]] |

## Indexace a sitemapa

| Typ adresy | Počet |
|---|---:|
| Filtry podle parametru | **1 716** |
| Varianty produktů | **104** |
| Štítkové stránky (`/t-…`) | **68** |
| Produkty | **59** |
| Kategorie a stránky | **16** |
| Ukázkový obsah | **9** |
| Filtry podle výrobce | **2** |

### [[kritické]] 1 786 filtračních stránek v indexu

Každá kombinace kategorie, parametru a hodnoty má vlastní adresu a je v sitemapě. Po importu vznikly adresy z celých vět:

| Adresa | Titulek v Googlu |
|---|---|
| `/caje/p-tip/s-citronem-zvyrazni-svezest-nalevu` | ČAJE - Tip - S citronem – zvýrazní svěžest nálevu. |
| `/ajurveda/p-baleni/velke-baleni-25-g-safran` | ÁJURVÉDA - Balení - Velké balení - 25 g - Šafrán |

Část adres v sitemapě navíc vrací chybu 404. Google pak sitemapě přestává věřit.

> **Oprava:** *Nastavení → Produkty → Filtry a řazení* → označit vše → „Označit jako neindexovat“. Stránky zmizí i ze sitemapy. Indexované necháme jen pár štítků s vlastním textem (celé listy, bez kofeinu, skořice Alba).

### [[kritické]] 104 variant produktů v sitemapě

Adresy jako `/p/lotovovy-kvet/203` se liší jen hmotností. Ve výsledcích vyhledávání se už objevuje varianta místo hlavního produktu.

> **Oprava:** *Nastavení → Rozšířené → SEO* → vyloučit varianty ze sitemapy. Na kanonickou adresu variant se zeptáme podpory Upgates.

### [[vysoké]] Pomocné stránky ve vyhledávání

`/oznameni-dovolena`, `/oznameni-kosik` a `/oznameni-lista` jsou jen zdroj textů pro vyskakovací okno a lištu. `/why-us` je prázdná. Nemazat, jen v SEO záložce nastavit „neindexovat“.

## Ukázkový obsah Upgates

### [[kritické]] Nesmyslné aktuality a návod na brož

Na webu zůstaly 4 ukázkové aktuality a jeden návod v Rádci. Text je generovaný nesmysl („Obrázek samozřejmostí mi unii tištěném druhů…“). Seznam už má ve výsledcích článek „Dárek k produktům značky Citizen“. Úvodní stránka na aktuality pořád odkazuje, blok je schovaný jen pro oko.

> **Oprava:** smazat aktuality (*Obsah → Aktuality*), návod na brož (*Obsah → Rádce*) a výrobce „Upgates“ (*Produkty → Výrobci*). Blok aktualit odstraníme ze šablony.

## Titulky a popisky

### [[vysoké]] Úvodní stránka se v Googlu jmenuje jen „Svět Cejlonu“

V titulku chybí čaj, koření i Srí Lanka. Popisek zní „Vítejte na e-shopu Svět Cejlonu! :: Svět Cejlonu“. Návrhy jsou dole v kapitole Návrhy titulků a popisků.

### [[vysoké]] Popisky opakují titulek

Kategorie a stránky mají popisek stejný jako titulek, třeba `ČAJE :: Svět Cejlonu`. U produktů se popisek bere z krátkého popisu:

| Produktů | Problém |
|---:|---|
| **41** | popisek delší než 160 znaků, Google ho ořízne |
| **16** | popisek začíná emoji |
| **6** | bez popisku |

Bez popisku jsou Chilli, Dárková krabice velká a malá, Přenosná čajová sada, Samahan a Skleněný louhovač.

### [[střední]] Názvy verzálkami a víc hlavních nadpisů

35 z 59 produktů má název velkými písmeny (`KURKUMA - MLETÁ`). Ve výsledcích hledání to působí jako křik. Ayush pleťový krém má pět hlavních nadpisů H1 včetně řádků z podtržítek, Kardamon, Navratna a O nás mají dva.

### [[střední]] Překlepy v názvech a adresách

- „RARANAVARA“ místo Ranavara
- `/p/lotovovy-kvet` místo lotosový a `/p/cerny-caj-earl-gray` místo Grey
- `/p/mangostan-zeleny-caj` je ve skutečnosti černý čaj

Adresy jde měnit jen s přesměrováním ze staré.

## Strukturovaná data

Podle strukturovaných dat Google pozná cenu, hodnocení i to, kdo e-shop provozuje. Upgates je vypisuje, na několika místech ale chybně.

| Typ | Stav | Poznámka |
|---|---|---|
| Recenze | [[chybné]] | hodnocení nepatří k recenzi a datum má neplatný formát, proto se hvězdičky ve výsledcích nejspíš nezobrazí |
| Firma | [[chybné]] | jmenuje se „Marek Bezdíček“ a cenová hladina je „$$$$$$“ |
| Propojení se sítěmi | [[chybí]] | Instagram, Facebook, Heureka a Firmy.cz |
| Produkt | [[částečně]] | chybí značka, EAN a země původu |
| Drobečková navigace | [[duplicitní]] | na detailu produktu dvakrát |

Opravíme to v šabloně. Od vás potřebujeme vědět, jestli v datech chcete uvést adresu, když ji na webu schováváte.

## Technika a rychlost

| Kontrola | Stav |
|---|---|
| HTTPS, přesměrování, stránka 404 | [[ano]] |
| Obsah čitelný bez JavaScriptu | [[ano]] |
| Stránka Kontakt | [[ne]] |
| Obrázky v sitemapě | [[ne]] |
| HTTP/2 | [[ne]] |

HTTP/2 Upgates nepodporuje a změnit se to nedá.

### [[střední]] Pomalý server

Polovina stránek odpoví do 0,7 s. Některé ale trvaly přes 8 s a `/p/cerny-caj` dokonce 22,9 s. Mobilní test Lighthouse dal detailu produktu 47 bodů ze 100. Nejvíc brzdí velké obrázky a měřicí kódy (GA4, Facebook, Clarity, Heureka). Časy pošleme podpoře Upgates, zmenšené obrázky máme připravené.

## Obsah a klíčová slova

Produktové texty jsou dobré, konkrétní a s vlastní zkušeností ze Srí Lanky. Kategorie ale nemají vlastní text a na webu nejsou žádné návody. Google ani AI proto nepoznají, že `/caje` je stránka o cejlonském čaji.

| Dotaz | Stránka | Stav |
|---|---|---|
| cejlonský čaj, sypaný čaj | `/caje` | [[slabé]] |
| cejlonská skořice, skořice Alba | skořice celá a mletá | [[slabé]] |
| srílanské koření, kari listy | `/koreni` | [[slabé]] |
| ájurvédská kosmetika | `/ajurveda` | [[slabé]] |
| dárkový balíček čaje | `/darkove-balicky` | [[slabé]] |
| modrý čaj, butterfly pea | `/p/motyli-kvet` | [[dobré]] |
| ibiškový čaj | `/p/ibisek` | [[dobré]] |
| jak louhovat sypaný čaj | žádná | [[nic]] |

Hledanosti neuvádíme. Bez Search Console nebo Skliku se nedají poctivě změřit.

## Důvěryhodnost

Silné stránky: příběh zakladatele, zkušenost ze Srí Lanky u produktů, 25 recenzí u ibišku a certifikát Ověřeno zákazníky na Heurece.

### [[vysoké]] Chybí Kontakt a zdravotní tvrzení jsou riskantní

Adresa `/kontakt` vrací chybu, firma a IČO jsou jen v obchodních podmínkách.

Názvy jako „Ájurvédský balzám (Bolest hlavy)“ nebo mast na „plísňové potíže“ jsou u doplňků a kosmetiky regulované a Google takové stránky hodnotí přísněji. Bezpečnější je popsat, jak se produkt tradičně používá na Srí Lance, a diagnózu z názvu vynechat. Právní posudek to není, ten by měl udělat odborník.

## Katalogy a odkazy

| Místo | Stav | Co udělat |
|---|---|---|
| Firmy.cz | [[duplicita]] | dva záznamy, sloučit do jednoho |
| Google Business Profile | [[neověřeno]] | ověřit, případně založit jako firmu bez provozovny |
| Heureka | [[aktivní]] | zapojit feed produktů |
| Google Merchant Center, Zboží.cz | [[ne]] | feed je v Upgates připravený |
| YouTube | [[ne]] | nahrát videa ze Srí Lanky |

Odkazy z jiných webů přinese nejspíš příběh tamilské školy, cestovatelské blogy o Srí Lance a čajovny, které od vás odebírají.

## AI vyhledávání (GEO)

ChatGPT, Perplexity nebo Gemini hledají přes Bing a Google a často citují weby s konkrétními čísly. Pět dotazů jsme zkusili ve webovém vyhledávání, ze kterého AI nástroje čerpají:

| Dotaz | svetcejlonu.cz | Ve výsledcích místo toho |
|---|---|---|
| „Svět Cejlonu“ čaj | [[nezmíněn]] | Wikipedii, cajovebedynky.cz, manutea.cz |
| cejlonský sypaný čaj přímo od farmářů | [[nezmíněn]] | dobracajovna.com, cejlonskycaj.cz |
| kde koupit pravou cejlonskou skořici | [[nezmíněn]] | cejlonskekoreni.cz, bylik.cz |
| srílanské koření a ájurvéda | [[nezmíněn]] | zdravizesrilanky.cz, ayurshop.cz |
| svetcejlonu.cz | [[ano]] | jen Firmy.cz a varianta produktu |

Z cejlonskekoreni.cz se ve výsledcích cituje věta o obsahu kumarinu ve skořici. Podobné údaje na svetcejlonu.cz buď nejsou, nebo se ztrácí mezi emoji.

### [[vysoké]] Upgates blokuje roboty OpenAI a Anthropic

GPTBot a ClaudeBot dostávají chybu 503. Ostatních 12 robotů, které jsme zkoušeli, projde. Modely se tak z webu nic nenaučí a e-shop najdou jen přes vyhledávání. Blokace je na straně Upgates a v robots.txt se vyřešit nedá. Napíšeme proto podpoře Upgates.

### Co AI cituje

- Produkt, jehož první věta říká, co to je: „Ibišek je sušený květ ze Srí Lanky, ze kterého vzniká červený kyselkavý čaj bez kofeinu.“
- Tabulku faktů: původ, třída, kofein, teplota a doba louhování.
- Otázky a odpovědi na konci popisu.
- Návody v Rádci. Začít se dá třeba skořicí (cejlonská, nebo kasie?), oblastmi cejlonského čaje a tabulkou louhování.

## Cenová nabídka

::: box Jednorázově
### Základní úklid

**4 900 Kč**

- Google Search Console, Bing Webmaster Tools a Seznam Webmaster, odeslání sitemap
- 1 786 filtračních stránek a 104 variant pryč z indexu a ze sitemapy
- smazání ukázkového obsahu Upgates, noindex pomocných stránek
- dotaz na Upgates kvůli zablokovaným robotům OpenAI a Anthropic

Hotovo do týdne od dodání přístupu k DNS domény.
:::

::: box Měsíčně · minimálně 3 měsíce
### SEO monitoring

**5 000 Kč** / měsíc

- první měsíc: titulky a popisky úvodky, kategorií a stránek
- druhý měsíc: šablona, tedy údaje o firmě, recenze a nadpisy
- třetí měsíc: stránka Kontakt, opravy nadpisů a překlepů v produktech
- sledování organického růstu a propadů webu
- doporučení pro úpravy obsahu na webu
- konkrétní checklist pro každý měsíc
- měsíční report návštěvnosti a analytiky webu
- kontrola indexace a chyb v Search Console
- možnost e-mailových konzultací
- WhatsApp skupina pro rychlé diskuze

Výsledky SEO bývají vidět po 2–3 měsících, proto minimálně 3 měsíce. Po třetím měsíci se domluvíme, jestli monitoring pokračuje a v jakém rozsahu.
:::

> **První 3 měsíce celkem 19 900 Kč** (4 900 Kč úklid + 3 × 5 000 Kč). Úkoly z checklistu bereme od nejdůležitějších.

### Co od vás budeme potřebovat

- přístup k DNS domény kvůli ověření Search Console
- přístupy do administrace Upgates, Google Analytics a Firmy.cz
- fakta k produktům: oblast původu, farmy, sklizeň, vlastní zkušenosti
- kontrolu zdravotních tvrzení u ájurvédy a doplňků od odborníka na regulaci

### Volitelně

| Úkol | Cena |
|---|---|
| Feedy Zboží.cz a Google Merchant Center | 800 Kč |
| Nahrání zmenšených fotek produktů s popisnými názvy souborů | 800 Kč |

## Návrhy titulků a popisků

Titulky do 60 znaků, popisky do 155. Pokud Upgates přidá „:: Svět Cejlonu“ na konec titulku sám, „| Svět Cejlonu“ z návrhů vynecháme.

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

### Vzor pro produkty

| Produkt | Titulek | Popisek (první věta krátkého popisu) |
|---|---|---|
| Ibišek | Ibiškový čaj – sušený květ ze Srí Lanky \| Svět Cejlonu | Sušené květy ibišku ze Srí Lanky na rubínově červený, osvěžující čaj bez kofeinu. Výborný horký i ledový, balení 20–80 g. |
| Skořice celá | Pravá cejlonská skořice Alba celá \| Svět Cejlonu | Pravá cejlonská skořice nejvyšší třídy Alba: tenké křehké svitky s jemně sladkým aroma a minimem kumarinu. Přímo ze Srí Lanky. |
| Motýlí květ | Modrý čaj z motýlího květu (butterfly pea) \| Svět Cejlonu | Bylinný čaj z květů motýlího hrachoru, který se s citronem zbarví z modré do fialové. Bez kofeinu, ze Srí Lanky. |
| Černý čaj Pekoe | Cejlonský černý čaj Pekoe sypaný \| Svět Cejlonu | Klasický cejlonský černý čaj třídy Pekoe z vybraných oblastí Srí Lanky. Plná, čistá chuť, ideální k snídani i s mlékem. |

## Jak jsme měřili

Dne 24. 9. 2026 jsme prošli 1 009 stránek a všech 1 974 adres ze sitemapy. Server jsme vyzkoušeli se 14 roboty a rychlost změřili v Lighthouse. Bez Search Console nevidíme skutečné pozice, hledanosti ani to, kolik adres Google opravdu zaindexoval. To ukáže až Search Console měsíc po založení.
