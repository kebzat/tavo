## Shrnutí

Po redesignu server odpovídá rychle a bez výpadků. Všech 1 978 adres ze sitemapy se otevřelo, nejpomalejší za 1,7 s (24. 9. to bylo až 22,9 s). Úkoly ze zářijového auditu ale zatím nikdo neudělal a SEO brzdí pořád stejné věci:

1. Vyhledávačům posíláme 1 979 adres a skutečných produktů a stránek je z nich 71. Zbytek jsou filtry, varianty produktů, pomocné stránky a ukázkový obsah Upgates.
2. Z textů parametrů jako Použití nebo Tip vznikají stovky samostatných stránek. Část z nich obsahuje zdravotní tvrzení, která u potravin nejsou povolená.
3. Web zatím nemá Google Search Console ani Seznam Webmaster. Bez nich nevíme, na jaké dotazy se e-shop ukazuje a kolik lidí z vyhledávání přichází.
4. Titulky, popisky a strukturovaná data neříkají, co e-shop prodává. Úvodní stránka se ve výsledcích jmenuje jen „Svět Cejlonu“ a v údajích o firmě je jméno majitele.
5. Kategorie mají jen dvě věty textu a na webu nejsou žádné návody. Google ani AI asistenti proto nemají z čeho poznat, že tu jde o cejlonský čaj, skořici nebo ájurvédu.

Základ je dobrý: obsah se vykresluje na serveru, adresy jsou čisté, přesměrování na https a www funguje, stránka 404 má správný kód a souhlas s cookies je nastavený podle pravidel Googlu. Produktové texty jsou osobní a konkrétní. Na tom se dá stavět.

### Stav podle oblastí

| Oblast | Stav | Poznámka |
|---|---|---|
| Indexace a sitemapa | [[kritické]] | 1 908 zbytečných adres v sitemapě |
| Zdravotní tvrzení | [[kritické]] | léčebné účinky u čajů a doplňků stravy |
| Měření vyhledávání | [[kritické]] | chybí Search Console, Bing i Seznam Webmaster |
| Ukázkový obsah | [[kritické]] | 4 aktuality a návod z šablony Upgates |
| Titulky a popisky | [[vysoké]] | kopie titulku, verzálky |
| Strukturovaná data | [[vysoké]] | chybný název firmy a data recenzí |
| Obsah a klíčová slova | [[vysoké]] | kategorie bez textu, žádné návody |
| Viditelnost ve vyhledávání | [[vysoké]] | na první stránce Seznamu jen na značku a „kari listy“ |
| Rychlost | [[střední]] | server rychlý, těžké obrázky u produktů |
| Odkazy a katalogy | [[střední]] | Heureka dobrá, název firmy v profilech nejednotný |
| AI vyhledávání | [[vysoké]] | 2 AI roboti dostávají chybu 503, v 10 dotazech jsme web nenašli |
| Měření a cookies | [[v pořádku]] | Consent Mode v2, GA4 a pixel správně |
| Technický základ | [[v pořádku]] | HTTPS, 404, čisté adresy, obsah bez JavaScriptu |

## Co se změnilo od 24. 9.

| Úkol ze zářijového auditu | Stav |
|---|---|
| Rychlost serveru | [[zlepšeno]] |
| Adresy v sitemapě vracející chybu 404 | [[zlepšeno]] |
| Search Console, Bing a Seznam Webmaster | [[ne]] |
| Filtry a varianty pryč z indexu a sitemapy | [[ne]] |
| Smazat ukázkový obsah Upgates | [[ne]] |
| Titulky a popisky úvodky a kategorií | [[ne]] |
| Údaje o firmě a recenze ve strukturovaných datech | [[ne]] |
| Úvodní text kategorií | [[částečně]] |
| Stránka Kontakt | [[ne]] |
| Kontrola zdravotních tvrzení | [[ne]] |

Kategorie mezitím dostaly krátký úvodní text. Sitemapa se proti září ještě o 5 adres zvětšila.

## Cíle a výchozí stav

Podle sortimentu a dosavadní spolupráce bereme jako cíl víc objednávek z vyhledávání u čajů, koření a dárkových balíčků. Nejbližší sezóna jsou Vánoce. Kdyby byl cíl jiný, třeba velkoobchod nebo ájurvéda, pořadí úkolů upravíme.

Výchozí stav zatím změřit nejde. Search Console není založená, takže chybí historie dotazů, zobrazení i kliknutí z Googlu. Hned po založení si uložíme:

- počet stránek v indexu Googlu a Seznamu,
- dotazy a stránky, které už dnes přivádějí lidi,
- organickou návštěvnost a objednávky z Google Analytics za posledních 12 měsíců,
- počet návštěv z ChatGPT, Perplexity a dalších AI nástrojů.

S těmito čísly porovnáme výsledky v březnu.

## Technické SEO

### [[kritické]] V sitemapě je 1 908 zbytečných adres

Sitemapa je seznam stránek, který posíláme vyhledávačům. Obsahuje 1 979 adres:

| Typ adresy | Počet |
|---|---:|
| Filtry podle parametru (`/caje/p-…`) | **1 721** |
| Varianty produktů (`/p/…/240`) | **104** |
| Štítky (`/caje/t-…`) | **68** |
| Produkty | **59** |
| Kategorie a obsahové stránky | **12** |
| Pomocné stránky a ukázkový obsah | **13** |
| Filtry podle výrobce | **2** |

Nejvíc filtrů vzniklo z textových parametrů: Použití (344 adres), Tip (202), Příprava (191), Tradiční využití (171), Zajímavosti (168) a Chuťový profil (162). Každá věta z popisu produktu má vlastní stránku s titulkem i nadpisem. Roboti je najdou dvěma cestami: ze sitemapy a z detailu produktu, kde je každá hodnota parametru odkaz na filtr. Z 52 produktů vede tímto způsobem 808 různých odkazů na filtry.

Robot vyhledávače má na každý web omezený čas. Když ho tráví na tisícovce téměř prázdných stránek, nové produkty a změny v textech se do výsledků dostanou později. Google navíc posuzuje kvalitu webu jako celku a tisíce stránek s jednou větou mu ji kazí.

> **Oprava:** textové parametry vyřadíme z filtrů, ostatní filtry a štítky označíme jako „neindexovat“ a tím zmizí i ze sitemapy. V šabloně detailu produktu zrušíme odkazy z hodnot parametrů. Až filtry vyhledávače vyřadí, zakážeme je robotům v robots.txt. V indexu necháme jen štítky, které dostanou vlastní text.

### [[kritické]] Varianty produktů se tváří jako samostatné produkty

Každá hmotnost nebo velikost má vlastní adresu a sama sebe označuje jako hlavní verzi (canonical). Canonical je značka, kterou stránka říká vyhledávači, která adresa je ta pravá. Google tak dostává 104 skoro stejných stránek navíc a sám vybírá, kterou ukáže.

> **Oprava:** varianty vyřadíme ze sitemapy a canonical variant nastavíme na hlavní produkt. Pokud to Upgates neumožní, požádáme podporu.

### [[vysoké]] Pomocné stránky jsou otevřené vyhledávačům

`/oznameni-dovolena`, `/oznameni-lista` a `/oznameni-kosik` jsou jen zdroj textů pro lištu a vyskakovací okno. `/why-us` je prázdná, `/compare` je porovnání produktů a `/newsletter` přihlášení k odběru. Všechny jsou nastavené jako „indexovat“, první tři jsou i v sitemapě a `/newsletter` se na Seznamu ukazuje mezi prvními výsledky na hledání značky.

> **Oprava:** nastavíme je jako „neindexovat“. Stránky oznámení nemažeme, šablona z nich bere texty.

### [[střední]] Přesměrování ze starého tvaru adresy má dva kroky

`http://svetcejlonu.cz` vede nejdřív na `http://www.svetcejlonu.cz` a teprve potom na `https://www.svetcejlonu.cz`. Funguje to, jen se čeká na jeden krok navíc.

> **Oprava:** nastavit přesměrování rovnou na `https://www`. Je to nastavení serveru, takže požádáme podporu Upgates.

### [[střední]] Chrome na úvodní stránce a v kategoriích nezměří vykreslení hlavního obsahu

Google měří rychlost webu přes Core Web Vitals, tedy tři čísla z prohlížečů skutečných návštěvníků. Jedno z nich, LCP, říká, kdy se vykreslí největší prvek stránky. Na detailu produktu ho Chrome změří, na úvodní stránce a v kategoriích ne. Stejné je to v Lighthouse i při přímém měření v prohlížeči, s animacemi i bez nich. Google pak pro tyto stránky nejspíš nemá z čeho rychlost vyhodnotit.

> **Oprava:** najdeme v šabloně, co měření blokuje, a opravíme to. Výsledek ověříme v Lighthouse a později v Search Console.

### [[střední]] Detail produktu je na mobilu těžký

Detail mleté cejlonské skořice má přes 4 MB, skoro všechno jsou obrázky. Jeden z nich je PNG o velikosti 1,2 MB. Lighthouse na simulovaném pomalém mobilním připojení naměřil vykreslení hlavního obrázku za 21 až 23 s a odhaduje, že na obrázcích se dá ušetřit kolem 2 MB. V kategoriích se při načítání posouvá obsah (CLS 0,15 až 0,21, Google chce pod 0,1).

Server sám je rychlý. Polovina adres ze sitemapy odpověděla do 0,27 s.

> **Oprava:** zmenšit a převést produktové fotky do WebP, opravit posouvání obsahu v kategoriích a přeměřit.

### [[v pořádku]] Co funguje

| Kontrola | Stav |
|---|---|
| HTTPS a jedna verze domény | [[ano]] |
| Adresy se lomítkem na konci se přesměrují | [[ano]] |
| Neexistující stránka vrací 404 a „neindexovat“ | [[ano]] |
| Obsah čitelný bez JavaScriptu | [[ano]] |
| Všechny produkty kategorie na jedné stránce | [[ano]] |
| Řazení produktů má canonical na kategorii | [[ano]] |
| Košík a pokladna zakázané v robots.txt | [[ano]] |
| Smíšený obsah (http na https stránce) | [[ne]] |

## Architektura a interní odkazy

Struktura je mělká a srozumitelná: úvodní stránka, 6 kategorií a v nich produkty. Každá kategorie se vejde na jednu stránku, takže se robot ke všem produktům dostane na dvě kliknutí. Drobečková navigace je na produktech i v kategoriích.

### [[střední]] Kategorie se od sebe skoro neliší

Čaje, Koření, Doplňky stravy, Ájurvéda a Ostatní mají pod výpisem stejné bloky: Nejprodávanější, Náš příběh a recenze. Kromě výpisu produktů a dvou vět úvodu je text stejný. Google tak vidí pět stránek, které se liší hlavně názvem. Kategorie zatím neodkazují na žádné návody, protože žádné nejsou.

> **Oprava:** bloky pod výpisem přizpůsobit kategorii a odkázat z ní na související návody a štítky, které zůstanou v indexu.

## Klíčová slova a mapa stránek

Hledanost slov bez přístupu do Search Console nebo placeného nástroje poctivě změřit nejde, proto ji neuvádíme. Témata jsme sestavili z našeptávačů Googlu a Seznamu pro 58 výchozích slov. U Seznamu jsme prošli první stránku výsledků. Na pozice v Googlu jsme se nedívali, ty ukáže až Search Console.

Na první stránce Seznamu je dnes svetcejlonu.cz jen u hledání značky (Svět Cejlonu, svetcejlonu.cz) a na 8. místě u „kari listy“. Na ostatní zkoušené dotazy ne.

### Témata a stránky, které je mají pokrýt

| Téma | Stránka | Stav |
|---|---|---|
| cejlonský čaj, sypaný čaj ze Srí Lanky | `/caje` | [[slabé]] |
| černý čaj, earl grey | jen produkty | [[chybí]] |
| zelený a jasmínový čaj | jen produkty | [[chybí]] |
| modrý čaj, motýlí hrášek | `/p/motyli-kvet` | [[dobré]] |
| ibiškový čaj | `/p/ibisek` | [[slabé]] |
| čaj bez kofeinu | štítek ve dvou kategoriích | [[duplicitní]] |
| cejlonská skořice, skořice Alba | dva produkty a dva štítky | [[duplicitní]] |
| cejlonská a čínská skořice, kumarin | žádná | [[chybí]] |
| srílanské koření | `/koreni` | [[slabé]] |
| kurkuma, kari listy, kardamom, garam masala | produkty | [[dobré]] |
| moringa, amla, ashwagandha | produkty | [[částečně]] |
| ájurvéda, ájurvédské produkty a kosmetika | `/ajurveda` | [[slabé]] |
| ájurvédský balzám | dva produkty | [[duplicitní]] |
| dárkový balíček čaje, dárek pro milovníka čaje | `/darkove-balicky` | [[slabé]] |
| jak louhovat sypaný čaj | žádná | [[chybí]] |
| recepty (masala chai, kari, garam masala) | žádná | [[chybí]] |
| co je ájurvéda | žádná | [[chybí]] |
| značka Svět Cejlonu | úvodní stránka | [[dobré]] |

Slabé znamená, že stránka existuje, ale v titulku, nadpisu ani textu nemá to, co lidé hledají. Dobré znamená, že stránka téma pokrývá, ne že je ve výsledcích na předních místech.

### [[vysoké]] Chybí stránky pro hledaná témata

> **Oprava:** založit podkategorie Černé čaje a Zelené čaje, stránku o pravé cejlonské skořici a návody v Rádci: cejlonská a čínská skořice, louhování sypaného čaje, co je modrý čaj, úvod do ájurvédy, recepty a dárky pro milovníky čaje.

### [[střední]] Stránky si konkurují o stejná hledání

Na cejlonskou skořici míří dva produkty a stejný štítek ve dvou kategoriích. Štítky jako „bez kofeinu“ nebo „nejprodávanější“ jsou zároveň v kategorii a v dárkových balíčcích. Každý produkt navíc soupeří se svými variantami. Vyhledávač pak neví, kterou stránku ukázat.

> **Oprava:** pro každé téma určit jednu hlavní stránku, ostatní neindexovat a odkázat z nich na hlavní.

### [[střední]] Web používá jiná slova než zákazníci

Lidé hledají „kardamom“, web píše „kardamon“. Lidé hledají „moringa prášek“ a „amla prášek“, web píše „mletá“.

> **Oprava:** v názvech a textech používat slova, která lidé hledají. Druhou variantu nechat v textu.

### Google a Seznam

Našeptávač Googlu přidává ke slovům obchodní řetězce a lékárny (dm, Kaufland, Dr. Max). Seznam ukazuje přímo produkty ze Zboží.cz a firmy z Firmy.cz. U ájurvédy Google nabízí spíš otázky, co to je, Seznam spíš nákup. Pro Seznam proto víc rozhodují profily na Zboží.cz a Firmy.cz, pro Google návody.

## On-page

On-page znamená všechno, co vyhledávač čte přímo na stránce: titulek, popisek, nadpisy, text, obrázky a strukturovaná data.

### [[vysoké]] Titulek a popisek úvodní stránky neříkají, co prodáváte

Titulek je jen „Svět Cejlonu“ a popisek „Vítejte na e-shopu Svět Cejlonu! :: Svět Cejlonu“. Ve výsledcích hledání chybí čaj, koření, ájurvéda i Srí Lanka. Na tyto dotazy se úvodní stránka nemá šanci ukázat.

> **Oprava:** napsat titulek a popisek, ve kterém je sortiment, původ a hlavní výhoda.

### [[vysoké]] Popisky kategorií a stránek jsou kopie titulku

Kategorie a obsahové stránky mají popisek složený z titulku, kategorie navíc verzálkami. Google si pak popisek ve výsledcích skládá sám z textu stránky.

> **Oprava:** napsat vlastní titulky a popisky pro úvodní stránku, 6 kategorií a obsahové stránky. Upravit šablonu, aby popisek titulek neopakovala.

### [[střední]] Popisky produktů jsou moc dlouhé nebo s emoji

Popisek produktu se bere z krátkého popisu. U 41 z 59 produktů je delší než 160 znaků a Google ho ořízne. 15 jich obsahuje emoji, 6 jím začíná. 6 produktů má popisek kratší než 70 znaků: Chilli, obě dárkové krabice, Přenosná čajová sada, Samahan a Skleněný louhovač.

> **Oprava:** přepsat úvod krátkého popisu tak, aby prvních asi 155 znaků dávalo smysl samo o sobě.

### [[střední]] Názvy produktů verzálkami

35 z 59 produktů má v názvu slovo psané velkými písmeny, 18 celý název. Ve výsledcích hledání se to čte hůř a působí to jako křik. Kategorie mají verzálkami titulek, nadpis i popisek.

> **Oprava:** v administraci psát názvy normálně. Pokud design verzálky chce, nastaví se v šabloně stylem.

### [[nízké]] Nadpisy na stránkách

Ayush pleťový krém má pět hlavních nadpisů (H1), Kardamon a O nás dva. Na kategoriích jsou nadpisy druhé úrovně obecné a stejné všude. Google podle vlastních slov počet ani pořadí nadpisů neřeší, jde hlavně o čitelnost a přístupnost.

> **Oprava:** v popisech produktů změnit nadbytečné hlavní nadpisy na nižší úroveň.

### [[vysoké]] Strukturovaná data mají chyby

Strukturovaná data jsou údaje ve zdrojovém kódu, ze kterých Google pozná cenu, hodnocení, firmu nebo název webu. Upgates je vypisuje jako mikrodata.

| Údaj | Stav | Poznámka |
|---|---|---|
| Název webu | [[chybné]] | „Marek Bezdíček“ místo „Svět Cejlonu“ |
| Firma | [[chybné]] | jmenuje se „Marek Bezdíček“, cenová hladina „$$$$$$“ |
| Recenze | [[chybné]] | neplatný formát data, hodnocení recenze je zapsané jako souhrnné |
| Produkt | [[částečně]] | cena, dostupnost a hodnocení ano, chybí značka a EAN |
| Odkazy na profily firmy | [[chybí]] | Instagram, Facebook, Heureka ani Firmy.cz |
| Doprava a vrácení zboží | [[chybí]] | Google je umí převzít z údajů o firmě |
| Drobečková navigace | [[ano]] | |
| Typ stránky pro sdílení (`og:type`) | [[chybné]] | úvodní stránka má neplatnou hodnotu „web“ |

Recenze mají ve strukturovaných datech 38 produktů. Kvůli chybám v datech se hvězdičky ve výsledcích nejspíš neukážou.

> **Oprava:** v šabloně opravit název webu, údaje o firmě, formát recenzí a doplnit profily, značku a pravidla dopravy a vrácení. Ověříme v Rich Results Testu od Googlu.

### [[v pořádku]] Obrázky a adresy

Obrázky mají popisky (alt) skoro všude. Adresy jsou krátké, česky a bez diakritiky. Překlepy v adresách (`/p/lotovovy-kvet`, `/p/cerny-caj-earl-gray`) neměníme. Slova v adrese mají na pozice zanedbatelný vliv a změna by přinesla zbytečné riziko.

Soubory obrázků se jmenují čísly nebo automaticky vygenerovaným názvem. Pro Google Obrázky je lepší název, který popisuje, co na fotce je. Vyplatí se to u nových fotek, staré kvůli tomu přejmenovávat nemusíme.

## Obsah a důvěryhodnost

E-E-A-T je zkratka, kterou Google používá pro zkušenost, odbornost, autoritu a důvěryhodnost. U e-shopu s doplňky stravy a ájurvédou ji hodnotí přísněji, protože jde o zdraví.

### [[kritické]] Zdravotní tvrzení u potravin

U 15 produktů slibuje text nebo parametr účinek na nemoc. Deset z nich jsou čaje a doplňky stravy: snižování krevního tlaku a cholesterolu, léčba průjmu, pomoc při nespavosti, úzkosti, infekcích nebo u diabetiků. U ibišku je dokonce srovnání s léky. Zbylých pět jsou masti, balzámy a zubní pasta s bolestí nebo plísní v textu. Čaj, koření i doplněk stravy jsou podle práva potraviny. U potravin se nesmí tvrdit, že léčí nemoc nebo jí předcházejí, a zdravotní tvrzení smí být jen ze seznamu schváleného Evropskou unií. Dodržování kontroluje SZPI. Masti a kosmetika mají vlastní pravidla, ani u nich ale nejde slibovat léčbu.

Tytéž věty jsou zároveň samostatné stránky otevřené vyhledávačům, protože z parametru Použití vzniklo 344 filtrů.

> **Oprava:** projít parametry Použití, Tradiční využití a Upozornění a popisy produktů a nechat jen to, co zákon dovoluje. Tradiční použití na Srí Lance se dá popsat bez slibu léčby. Konečné znění by měl posoudit odborník na potravinové právo.

### [[kritické]] Ukázkový obsah Upgates

Na webu zůstaly 4 ukázkové aktuality a návod na brož v Rádci. Text je generovaný nesmysl. Úvodní stránka na aktuality odkazuje a všechny jsou v sitemapě. Ukázkový je i výrobce „Upgates“ na adrese `/m/upgates`. Na Seznamu se jedna z ukázkových aktualit ukazuje na první stránce, když někdo hledá „Svět Cejlonu“.

> **Oprava:** ukázkové aktuality, návod a výrobce smazat, blok aktualit z úvodní stránky odstranit nebo naplnit skutečnými novinkami.

### [[střední]] Kategorie mají dvě věty a v jedné je chyba

Kategorie dostaly po redesignu krátký úvod. Pod ním je ale u Koření, Doplňků stravy, Ájurvédy i Ostatních věta „Nevíte, který čaj vybrat? Vyberte podle chuti nebo denní doby.“, která patří jen k čajům. Dvě věty nestačí, aby Google poznal, že je kategorie dobrou odpovědí na dotaz jako cejlonský čaj nebo srílanské koření.

> **Oprava:** opravit větu o čaji a ke každé kategorii napsat delší text a pár otázek a odpovědí.

### [[vysoké]] Chybí stránka Kontakt

Adresa `/kontakt` vrací chybu 404. Telefon je v patičce, firma a IČO jen v obchodních podmínkách. Kontakt je pro Google i pro zákazníka základní známka, že za e-shopem stojí skutečný člověk.

> **Oprava:** založit stránku Kontakt s provozovatelem, IČO, e-mailem, telefonem a adresou pro vrácení zboží.

### [[v pořádku]] Silné stránky

Příběh zakladatele na stránce O nás, stránka o podpoře tamilské školy, osobní zkušenost ze Srí Lanky v textech produktů a recenze s označením „Ověřená recenze“. Tohle se vyplatí víc ukázat, hlavně na kategoriích a v návodech.

### Co s jednotlivými stránkami

| Stránky | Počet | Co s nimi |
|---|---:|---|
| Produkty | 59 | ponechat, vylepšit popisky a zdravotní tvrzení |
| Kategorie | 6 | ponechat, rozšířit text |
| O nás, škola, velkoobchod, vše o nákupu, chybí vám něco | 5 | ponechat |
| Štítky (`/t-…`) | 68 | vybrané ponechat s vlastním textem, ostatní neindexovat |
| Filtry podle parametru a výrobce | 1 723 | neindexovat, pak zakázat robotům |
| Varianty produktů | 104 | canonical na hlavní produkt |
| Oznámení, `/why-us`, `/compare`, `/newsletter` | 6 | neindexovat |
| Ukázkové aktuality, návod, výrobce Upgates | 6 | smazat |

## Katalogy, odkazy a konkurence

Odkazy z jiných webů bez placeného nástroje (Ahrefs, Marketing Miner) změřit nejde. Dohledali jsme, kde se e-shop zmiňuje ve vyhledávání a v katalozích.

| Místo | Stav | Poznámka |
|---|---|---|
| Heureka | [[aktivní]] | 54 recenzí, 100 % doporučuje |
| Firmy.cz | [[částečně]] | jeden záznam pod názvem „Svetcejlonu.cz“, bez hodnocení |
| Zboží.cz | [[částečně]] | obchod je založený, bez hodnocení |
| Facebook | [[aktivní]] | 923 sledujících |
| Instagram | [[aktivní]] | 423 sledujících |
| YouTube | [[ne]] | kanál jsme nenašli |
| Články a blogy o e-shopu | [[nic]] | žádnou zmínku jsme nenašli |
| Google Business Profile | [[pozor]] | čistý e-shop podle pravidel Googlu nárok nemá |

Druhý záznam na Firmy.cz, o kterém psal zářijový audit, jsme 2. 10. nenašli ani po čtyřech různých hledáních.

### [[střední]] Firma vystupuje pod různými jmény a v jednom profilu s jinou adresou

Web a Heureka píšou „Svět Cejlonu“, Firmy.cz a Zboží.cz „Svetcejlonu.cz“, Facebook třetí variantu a strukturovaná data jméno majitele. Profil na webtrziste.cz uvádí jinou adresu a jiný telefon než všechny ostatní zdroje. Google i AI spojují informace o firmě podle shody jména, adresy a telefonu.

> **Oprava:** sjednotit název na „Svět Cejlonu“ ve všech profilech a opravit adresu a telefon na webtrziste.cz.

### [[střední]] Málo hodnocení mimo Heureku

Na Firmy.cz a Zboží.cz nemá e-shop žádné hodnocení. Seznam profily z obou katalogů ukazuje přímo ve výsledcích hledání i v našeptávači.

> **Oprava:** po nákupu posílat zákazníkům prosbu o hodnocení i na Firmy.cz. Zboží.cz začne sbírat hodnocení po zapojení feedu.

### [[nízké]] Google Business Profile

Google ho dává jen firmám, které se se zákazníky potkávají osobně na stálém místě. Prodej na trzích to nejspíš nesplňuje. Profil má smysl, jen kdyby vzniklo výdejní místo nebo prodejna.

### Konkurence

Pět e-shopů, které jsou na prvních místech Seznamu u hlavních dotazů:

| E-shop | Kde je na Seznamu | Co má |
|---|---|---|
| cejlonskycaj.cz | 1. „cejlonský čaj“ | dotaz v názvu domény, karta na Firmy.cz v našeptávači |
| caje-cejlon.cz | 1. „cejlonský čaj e-shop“ | vlastní popisky, blog |
| manutea.cz | 3. „cejlonský čaj“ | dotaz v titulku, magazín |
| cejlonskekoreni.cz | 1. „pravá cejlonská skořice“ | články a recepty |
| zdravizesrilanky.cz | 1. „ájurvédské produkty ze Srí Lanky“ | Srí Lanka v názvu domény |

Všichni mají hledaný dotaz nebo jeho podstatnou část v názvu domény nebo v titulku. Dva z pěti mají články nebo recepty. Textu na kategorii mají někteří méně než svetcejlonu.cz, délka textu tedy rozdíl nedělá.

## AI vyhledávání (GEO)

GEO znamená, jak e-shop vidí a cituje AI: ChatGPT, Perplexity, Copilot nebo AI odpovědi Googlu. Google pro AI Overviews a AI Mode podle vlastní dokumentace žádnou zvláštní úpravu ani zvláštní soubory nepotřebuje, stačí běžné SEO. ChatGPT má vlastního vyhledávacího robota, Copilot hledá přes Bing.

### [[vysoké]] Server Upgates odmítá dva AI roboty

| Robot | Stav |
|---|---|
| Googlebot, Bingbot, SeznamBot | [[200]] |
| OAI-SearchBot, ChatGPT-User (vyhledávání v ChatGPT) | [[200]] |
| Claude-SearchBot, PerplexityBot, Applebot | [[200]] |
| GPTBot (OpenAI) | [[503]] |
| ClaudeBot (Anthropic) | [[503]] |

GPTBot a ClaudeBot dostávají chybu 503 i na robots.txt. Stejně odpovídá i web upgates.cz, jde tedy nejspíš o pravidlo celé platformy. Vyhledávání v ChatGPT se na web dostane, modely se z něj ale nic nenaučí. Testovali jsme z běžného připojení s podpisem robota. Jak server odpovídá skutečným robotům, by ukázaly logy serveru.

> **Oprava:** napsat podpoře Upgates a zeptat se, jestli blokaci jde u e-shopu vypnout.

### [[vysoké]] V odpovědích AI se e-shop zatím neobjevuje

Deset typických otázek (třeba kde koupit pravou cejlonskou skořici, nejlepší cejlonský čaj nebo jak louhovat sypaný čaj) jsme zadali do AI vyhledávání. Ani jednou necitovalo svetcejlonu.cz. Citovalo hlavně velké obchody, lékárny a slevové weby a u skořice cejlonskekoreni.cz. Přímo v ChatGPT, Perplexity ani v AI odpovědích Googlu jsme netestovali.

Aby AI stránku citovala, musí na otázku přímo odpovídat. Na svetcejlonu.cz takové stránky zatím nejsou, chybí návody i texty kategorií.

> **Oprava:** návody a texty kategorií psát tak, aby první věta odpovídala na otázku. Fakta o firmě mít na stránce O nás a v profilech stejná.

### [[nízké]] llms.txt

Adresa `/llms.txt` vrací prázdný soubor. Google podle své dokumentace žádné zvláštní soubory pro AI nepotřebuje, nic se tu řešit nemusí.

### Jak to sledovat

Bing Webmaster Tools má od února 2026 přehled citací v Copilotu a AI odpovědích Bingu. Jednou měsíčně zadáme stejných deset otázek do ChatGPT, Perplexity a Googlu a zapíšeme, kdo se cituje.

## Měření

### [[kritické]] Chybí Search Console, Bing Webmaster Tools a Seznam Webmaster

Všechny tři nástroje jsou zdarma a ukazují, jak web vidí vyhledávač: které stránky má v indexu, na jaké dotazy web ukazuje a jaké chyby hlásí. Bez nich nejde ani ověřit, jestli úklid indexu zabral.

> **Oprava:** založit všechny tři, odeslat sitemapu a propojit Search Console s Google Analytics.

### [[v pořádku]] Souhlas s cookies a Google Analytics

Lišta s cookies se ukáže hned a do souhlasu se ukládají jen technické cookies Upgates. Google Analytics 4 běží v režimu Consent Mode v2: bez souhlasu posílá jen anonymní signály bez cookies, Meta pixel čeká na souhlas.

Jedno upozornění: kdo cookies odmítne, toho Analytics nepočítá celého. Organická návštěvnost bude v Analytics nižší než ve skutečnosti. Na vyhodnocení SEO proto budeme brát hlavně Search Console.

### [[střední]] Návštěvy z AI nástrojů nejsou oddělené

Návštěvy z ChatGPT, Perplexity nebo Copilotu dnes v Analytics padají mezi odkazy z jiných webů.

> **Oprava:** v Analytics založit vlastní skupinu kanálů pro AI nástroje.

## Navíc nad rámec zadání

### [[vysoké]] Vánoce

Dárkové balíčky jsou nejlepší kandidát na vánoční hledání. Aby se stránka do výsledků dostala včas, musí mít titulek, popisek, text a odkazy hotové v listopadu. Totéž platí pro produktový feed do Google Merchant Center a Zboží.cz.

### [[střední]] Kontrola adres po redesignu

Redesign zůstal na Upgates a adresy kategorií a produktů se podle všeho nezměnily. Ověřit to v archivu webu jsme 2. 10. nemohli, Internet Archive byl mimo provoz. Po založení Search Console zkontrolujeme, jestli Google nehlásí chyby 404 na starých adresách.

### [[střední]] Produktové feedy

Google Merchant Center a Zboží.cz ukazují produkty zdarma v nákupních výsledcích. Upgates feed pro oba umí. Je to nejrychlejší způsob, jak dostat konkrétní produkty do vyhledávání ještě před Vánoci.

### [[nízké]] Slovensko

E-shop doručuje na Slovensko, ale má jen českou verzi. Pro začátek to stačí. Samostatná slovenská verze má smysl, až bude ze Slovenska vidět poptávka v Analytics.

### [[střední]] Pravidelná kontrola

Jednou měsíčně projdeme Search Console (chyby, index, dotazy), pozice hlavních dotazů a citace v AI nástrojích a pošleme krátký přehled.

## Všechny nálezy

Seřazené podle měsíce, kdy je na řadě. Každý nález je rozepsaný v kapitolách výš a jako úkol v checklistu, odkaz je nahoře na stránce.

| # | Co je potřeba udělat | Priorita | Měsíc |
|---|---|---|---|
| 1 | Založit Search Console, Bing Webmaster Tools a Seznam Webmaster, uložit výchozí stav | [[kritické]] | říjen |
| 2 | Vyřadit textové parametry z filtrů, filtry a štítky neindexovat | [[kritické]] | říjen |
| 3 | Vyřadit varianty ze sitemapy, canonical na hlavní produkt | [[kritické]] | říjen |
| 4 | Smazat ukázkový obsah Upgates | [[kritické]] | říjen |
| 5 | Upravit zdravotní tvrzení u 15 produktů | [[kritické]] | říjen |
| 6 | Pomocné stránky nastavit jako neindexovat | [[vysoké]] | říjen |
| 7 | Zeptat se podpory Upgates na GPTBot, ClaudeBot a přesměrování | [[vysoké]] | říjen |
| 8 | Zkrátit přesměrování na https://www | [[střední]] | říjen |
| 9 | Titulek a popisek úvodní stránky, kategorií a obsahových stránek | [[vysoké]] | listopad |
| 10 | Opravit název webu, údaje o firmě a recenze ve strukturovaných datech | [[vysoké]] | listopad |
| 11 | Založit stránku Kontakt | [[vysoké]] | listopad |
| 12 | Připravit dárkové balíčky a feedy na Vánoce | [[vysoké]] | listopad |
| 13 | Návod „dárek pro milovníka čaje“ | [[střední]] | listopad |
| 14 | Najít, proč Chrome na úvodní stránce a v kategoriích nezměří LCP | [[střední]] | listopad |
| 15 | Zmenšit produktové fotky, opravit posouvání obsahu v kategoriích | [[střední]] | listopad |
| 16 | Popisky produktů, názvy bez verzálek, nadpisy | [[střední]] | listopad |
| 17 | Doplnit profily firmy, dopravu, vrácení, značku a EAN do dat | [[střední]] | listopad |
| 18 | Opravit větu o výběru čaje mimo čaje | [[střední]] | prosinec |
| 19 | Delší text a otázky ke každé kategorii | [[vysoké]] | prosinec |
| 20 | Podkategorie Černé a Zelené čaje, stránka o cejlonské skořici | [[vysoké]] | prosinec |
| 21 | Jedna hlavní stránka pro každé téma | [[střední]] | prosinec |
| 22 | Slova, která lidé hledají (kardamom, prášek) | [[střední]] | prosinec |
| 23 | Fakta a otázky u nejprodávanějších produktů | [[střední]] | prosinec |
| 24 | Návody v Rádci podle témat bez stránky | [[vysoké]] | leden |
| 25 | Stránka O nás jako zdroj faktů, autor u návodů | [[střední]] | leden |
| 26 | Sjednotit název, adresu a telefon v profilech | [[střední]] | únor |
| 27 | Hodnocení na Firmy.cz a Zboží.cz | [[střední]] | únor |
| 28 | Příběh školy, čajovny a odběratelé, YouTube | [[nízké]] | únor |
| 29 | Zakázat filtry v robots.txt, až zmizí z indexu | [[střední]] | únor |
| 30 | Vyhodnotit výsledky proti výchozímu stavu | [[vysoké]] | březen |

## Plán po měsících

Pořadí úkolů na šest měsíců. Nejdřív úklid a měření, protože bez nich nemá smysl psát nový obsah. Výsledky SEO se obvykle projeví až po několika měsících.

::: box Říjen
### Měření a úklid indexu

- Search Console, Bing a Seznam Webmaster, výchozí stav
- filtry, štítky a varianty pryč z indexu a sitemapy
- smazat ukázkový obsah Upgates
- zdravotní tvrzení
- dotaz na podporu Upgates
:::

::: box Listopad
### Šablona, titulky a Vánoce

- titulky a popisky
- strukturovaná data a stránka Kontakt
- dárkové balíčky a feedy před Vánoci
- rychlost a fotky
:::

::: box Prosinec
### Kategorie a produkty

- texty a otázky v kategoriích
- podkategorie čajů, stránka o skořici
- fakta u nejprodávanějších produktů
:::

::: box Leden
### Návody v Rádci

- skořice, louhování, modrý čaj, ájurvéda, recepty
- O nás a autor u návodů
:::

::: box Únor
### Katalogy a odkazy

- jednotné údaje v profilech
- hodnocení na Firmy.cz a Zboží.cz
- filtry zakázat v robots.txt
- příběh školy a odběratelé
:::

::: box Březen
### Vyhodnocení

- porovnání s výchozím stavem
- znovu test v AI vyhledávání
- plán na další měsíce
:::

Každý měsíc navíc projdeme Search Console a pošleme krátký přehled.

## Co od vás budeme potřebovat

- přístup k DNS domény nebo společné ověření Search Console,
- přístupy do administrace Upgates, Google Analytics a Firmy.cz,
- fakta k produktům a kategoriím: oblasti původu, farmy, sklizeň, vlastní zkušenosti,
- posouzení zdravotních tvrzení od odborníka na potravinové právo,
- rozhodnutí, jestli ve strukturovaných datech uvést adresu firmy.

## Co zvenku nevidíme

- **Návštěvnost a objednávky z vyhledávání.** Bez přístupu do Google Analytics nevíme, kolik lidí z vyhledávání chodí a nakupuje.
- **Pozice v Googlu a hledanost slov.** Bez Search Console nebo placeného nástroje je nejde poctivě změřit. Pozice na Seznamu jsme viděli jen na první stránce výsledků.
- **Odkazy z jiných webů.** Počet a kvalitu odkazujících webů ukáže Search Console nebo Marketing Miner, který má verzi zdarma.
- **Skutečná rychlost u návštěvníků.** Měřili jsme v laboratoři (Lighthouse a prohlížeč). Data od skutečných návštěvníků ukáže Search Console.
- **Staré adresy před redesignem.** Archiv webu nebyl 2. 10. dostupný.
- **Odpovědi ChatGPT, Perplexity a AI odpovědi Googlu.** Testovali jsme přes AI vyhledávání, ne přímo v těchto nástrojích.
- **Nastavení v administraci Upgates.** Co se dá vypnout v nastavení a co musí udělat podpora, ověříme až s přístupem.
