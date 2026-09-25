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
| Měření vyhledávání | [[kritické]] | GA4 a Meta pixel běží, chybí Search Console, Bing i Seznam Webmaster |
| AI vyhledávání | [[vysoké]] | dva blokovaní roboti, ve výsledcích je konkurence |
| Titulky a popisky | [[vysoké]] | verzálky, popisky jen opakují titulek |
| Obsah | [[vysoké]] | kategorie bez vlastního textu, žádné návody |
| Strukturovaná data | [[střední]] | chyby v recenzích, v údajích o firmě i v názvu webu |
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
| 2 | Filtrační stránky vyřadit z indexu a zakázat robotům | [[kritický]] |
| 3 | Vyřadit varianty produktů ze sitemapy | [[kritický]] |
| 4 | Smazat ukázkový obsah Upgates | [[kritický]] |
| 5 | Napsat podpoře Upgates kvůli blokovaným AI robotům | [[vysoký]] |
| 6 | Titulky a popisky úvodky, kategorií a stránek | [[vysoký]] |
| 7 | Opravit název webu, údaje o firmě a recenze ve strukturovaných datech | [[vysoký]] |
| 8 | Úvodní text a otázky v každé kategorii | [[vysoký]] |
| 9 | Návody do Rádce | [[vysoký]] |
| 10 | Sloučit dva záznamy na Firmy.cz | [[střední]] |
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

Část adres v sitemapě navíc vrací chybu 404. Vyhledávače chtějí v sitemapě jen platné adresy.

> **Oprava:** filtrační stránky nejdřív vyřadíme z indexu i ze sitemapy. Až z výsledků zmizí, zakážeme je robotům i v robots.txt, aby na ně přestali chodit. Takový postup u filtrů doporučuje Google. Ve vyhledávání necháme jen pár štítků s vlastním textem (celé listy, bez kofeinu, skořice Alba).

### [[kritické]] 104 variant produktů v sitemapě

Adresy jako `/p/lotovovy-kvet/203` se liší jen hmotností. Ve výsledcích vyhledávání se už objevuje varianta místo hlavního produktu.

> **Oprava:** varianty vyřadíme ze sitemapy a na kanonickou adresu variant se zeptáme podpory Upgates.

### [[vysoké]] Pomocné stránky ve vyhledávání

`/oznameni-dovolena`, `/oznameni-kosik` a `/oznameni-lista` jsou jen zdroj textů pro vyskakovací okno a lištu. `/why-us` je prázdná. Z vyhledávání je vyřadíme.

## Ukázkový obsah Upgates

### [[kritické]] Nesmyslné aktuality a návod na brož

Na webu zůstaly 4 ukázkové aktuality a jeden návod v Rádci. Text je generovaný nesmysl („Obrázek samozřejmostí mi unii tištěném druhů…“). Seznam už má ve výsledcích článek „Dárek k produktům značky Citizen“. Úvodní stránka na aktuality pořád odkazuje, blok je schovaný jen pro oko.

> **Oprava:** ukázkové aktuality, návod na brož i výrobce „Upgates“ smažeme a blok aktualit odstraníme ze šablony.

## Titulky a popisky

### [[vysoké]] Úvodní stránka se v Googlu jmenuje jen „Svět Cejlonu“

V titulku chybí čaj, koření i Srí Lanka. Popisek zní „Vítejte na e-shopu Svět Cejlonu! :: Svět Cejlonu“. Návrh je dole v kapitole Návrhy titulků a popisků.

### [[vysoké]] Popisky opakují titulek

Kategorie a stránky mají popisek stejný jako titulek, třeba `ČAJE :: Svět Cejlonu`. U produktů se popisek bere z krátkého popisu:

| Produktů | Problém |
|---:|---|
| **41** | popisek delší než 160 znaků, Google ho ořízne |
| **16** | popisek začíná emoji |
| **6** | bez popisku |

Bez popisku jsou Chilli, Dárková krabice velká a malá, Přenosná čajová sada, Samahan a Skleněný louhovač.

### [[střední]] Názvy verzálkami

35 z 59 produktů má název velkými písmeny (`KURKUMA - MLETÁ`). Ve výsledcích hledání to působí jako křik a hůř se to čte.

### [[nízké]] Víc hlavních nadpisů

Ayush pleťový krém má pět hlavních nadpisů H1 včetně řádků z podtržítek, Kardamon, Navratna a O nás mají dva. Google podle vlastních slov počet ani pořadí nadpisů neřeší, jde hlavně o čitelnost a přístupnost.

### [[střední]] Překlepy v názvech

„RARANAVARA“ místo Ranavara. Překlepy jsou i v adresách (`/p/lotovovy-kvet`, `/p/cerny-caj-earl-gray`), ty ale necháme. Slova v adrese mají podle Googlu na pozice zanedbatelný vliv a změna by přinesla zbytečné riziko.

## Strukturovaná data

Podle strukturovaných dat Google pozná cenu, hodnocení i to, kdo e-shop provozuje. Upgates je vypisuje, na několika místech ale chybně.

| Typ | Stav | Poznámka |
|---|---|---|
| Recenze | [[chybné]] | hodnocení nepatří k recenzi a datum má neplatný formát, proto se hvězdičky ve výsledcích nejspíš nezobrazí |
| Název webu | [[chybné]] | v datech je „Marek Bezdíček“, Google ho může ukazovat nad výsledky místo „Svět Cejlonu“ |
| Firma | [[chybné]] | jmenuje se „Marek Bezdíček“ a cenová hladina je „$$$$$$“ |
| Odkazy na profily firmy | [[chybí]] | web v datech neuvádí svůj Instagram, Facebook, Heureku ani Firmy.cz, i když profily existují |
| Produkt | [[částečně]] | chybí značka a EAN, Google je doporučuje |
| Doprava a vrácení zboží | [[chybí]] | Google je doporučuje uvést jednou za celou firmu |
| Drobečková navigace | [[duplicitní]] | na detailu produktu dvakrát |

Hvězdičky jsou možné u produktů. Hodnocení celé firmy z vlastního webu Google hvězdičkami neukazuje, to sbírá Heureka a Firmy.cz.

Opravíme to v šabloně. Od vás potřebujeme vědět, jestli v datech chcete uvést adresu, když ji na webu schováváte.

## Technika a rychlost

| Kontrola | Stav |
|---|---|
| HTTPS, přesměrování, stránka 404 | [[ano]] |
| Obsah čitelný bez JavaScriptu | [[ano]] |
| Stránka Kontakt | [[ne]] |
| Obrázky v sitemapě | [[ne]] |

### [[střední]] Pomalý server

Polovina stránek odpoví do 0,7 s. Některé ale trvaly přes 8 s a `/p/cerny-caj` dokonce 22,9 s. Na mobilu se hlavní obrázek detailu produktu načetl až za 11,9 s. Na web vede i placená reklama z Instagramu a Facebooku, takže pomalé stránky stojí peníze za kliknutí. Do pozic v Googlu se rychlost promítá podle měření od skutečných návštěvníků, a těch má e-shop na to nejspíš zatím málo. Rychlost teď rozhoduje hlavně o tom, kolik lidí z reklamy nakoupí.

Nejvíc brzdí velké obrázky, zmenšené máme připravené. Měřicí kódy (GA4, Meta pixel, Clarity, Heureka) zdrží stránku asi o 0,4 s. Pixel kvůli reklamám potřebujete, jen se dá načítat šetrněji. Časy pošleme podpoře Upgates.

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
| Google Business Profile | [[pozor]] | jen s výdejním místem nebo prodejnou, čistý e-shop podle pravidel Googlu nárok nemá |
| Heureka | [[aktivní]] | zapojit feed produktů |
| Google Merchant Center, Zboží.cz | [[ne]] | feed je v Upgates připravený |
| Instagram, Facebook | [[aktivní]] | běží reklamy, profily propojíme s webem ve strukturovaných datech |
| YouTube | [[ne]] | nahrát videa ze Srí Lanky, třeba ta z reklam |

Odkazy z jiných webů přinese nejspíš příběh tamilské školy, cestovatelské blogy o Srí Lance a čajovny, které od vás odebírají.

## AI vyhledávání (GEO)

ChatGPT, Perplexity nebo Gemini hledají přes Bing a Google a často citují weby s konkrétními čísly. Google pro AI Overviews a AI Mode žádnou zvláštní úpravu nechce, stačí běžné SEO. Pět dotazů jsme zkusili ve webovém vyhledávání, ze kterého AI nástroje čerpají:

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

### Jak to měřit

Bing Webmaster Tools ukazuje, kdy web citoval Copilot a AI odpovědi Bingu. Přes IndexNow se dá Bingu a dalším vyhledávačům hned oznámit každá změna na webu, pokud to Upgates podporuje.

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

Nové titulky a popisky máme připravené pro úvodku, 6 kategorií, 4 obsahové stránky a hlavní produkty. Ukázka pro úvodní stránku:

| | Dnes | Návrh |
|---|---|---|
| Titulek | Svět Cejlonu | Cejlonský čaj, koření a ájurvéda ze Srí Lanky \| Svět Cejlonu |
| Popisek | Vítejte na e-shopu Svět Cejlonu! :: Svět Cejlonu | Sypané cejlonské čaje, pravá cejlonská skořice a ájurvédské produkty od menších pěstitelů ze Srí Lanky. Ručně baleno v ČR, dárek ke každé objednávce. |
