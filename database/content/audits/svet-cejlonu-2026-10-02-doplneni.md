## Shrnutí

Svět Cejlonu má skvělý příběh značky, osobní zkušenost se Srí Lankou a hodnocení zákazníků.

Nejdřív ověříme měření, odstraníme zjevné chyby a určíme, které stránky mají být dohledatelné ve vyhledávačích. Současně připravíme důležité kategorie a dárkové balíčky na vánoční sezónu. Další obsah zaměříme na produkty s obchodním potenciálem a na otázky, které zákazníci řeší před nákupem.

Úspěch budeme hodnotit podle relevantní návštěvnosti, objednávek a tržeb z organického vyhledávání.

### Stav podle oblastí

| Oblast | Priorita | Zjištění a další krok |
|---|---|---|
| Zdravotní a léčebná tvrzení | [[kritické]] | U 15 produktů prověřit rizikové formulace; jde zejména o právní a reputační riziko. |
| Měření vyhledávání a objednávek | [[vysoké]] | Chybí Search Console, Bing a Seznam Webmaster. Ověřit nákupní události v GA4. |
| Indexace a sitemapa | [[vysoké]] | 1 979 URL, z toho 1 721 parametrických filtrů. 1 099 adres ze sitemapy nemá na webu žádný odkaz. |
| Interní odkazy | [[vysoké]] | Na Dárkové balíčky nevede odkaz z menu ani z žádné procházené stránky. Kategorie neodkazují na návody. |
| Ukázkový obsah | [[vysoké]] | Odstranit 4 ukázkové aktuality, návod, výrobce Upgates a odkazy na nápovědu Upgates z úvodní stránky. |
| Titulky a popisky | [[vysoké]] | Doplnit sortiment a konkrétní přínos, začít hlavními a vánočními stránkami. |
| Strukturovaná data | [[vysoké]] | Opravit název webu a firmy a zápis recenzí, doplnit profily, dopravu a vrácení. |
| Obsah kategorií a konkurence | [[vysoké]] | Na Googlu web 2. 10. nebyl na první stránce u žádného z 9 hlavních dotazů. Konkurence má podkategorie, návody, delší text kategorií a doložený původ. |
| Rychlost | [[vysoké]] | Prioritně zmenšit těžké produktové obrázky; laboratorní výsledky oddělit od reálných návštěvníků. |
| Technický crawl | [[střední]] | 1 982 z 1 988 adres vrací 200, 1 rozbitý interní odkaz, žádná chyba serveru. Problém je množství podobných adres, ne chyby. |
| Profily a externí odkazy | [[střední]] | Ověřit kontakty, sjednotit značku a odlišit ji od právního provozovatele. |
| GEO | [[střední]] | Vyhledávací roboti AI služeb mají přístup. Chybí jednotná fakta o značce a odpovědi na otázky zákazníků. |
| Správa e-shopu | [[střední]] | Chybí pravidla pro nové produkty, vyprodané zboží a kontrolu po změnách. |
| Analytics a cookies | [[částečně]] | Základní chování bylo otestováno, úplnost měření a nákupní události je třeba ověřit. |
| Nákupní cesta | [[v pořádku]] | Přidání do košíku, doprava zdarma, dárek a recenze fungují. Drobnosti na mobilu. |
| Technický základ | [[v pořádku]] | HTTPS, funkční 404, obsah dostupný bez JavaScriptu, žádné chyby 5xx. |

## Cíle a výchozí stav

Pracovní cíl je zvýšit počet a hodnotu objednávek z organického vyhledávání u čajů, koření a dárkových balíčků. Nejbližší obchodní příležitostí jsou Vánoce.

Nemáme doložené přístupy do analytických nástrojů.

### Co budeme měřit

| Ukazatel | Zdroj | Způsob hodnocení |
|---|---|---|
| Objednávky a tržby z organického vyhledávání | GA4 po ověření nákupních událostí | Vývoj podle vstupních stránek a kategorií; kontrola proti objednávkám v e-shopu |
| Kliknutí a zobrazení | Google Search Console | Značkové a neznačkové dotazy odděleně |
| CTR a průměrná pozice | Google Search Console | V kontextu konkrétního dotazu, stránky, zařízení a období |
| Indexace důležitých URL | Nástroje vyhledávačů | Dostupnost hlavních kategorií a produktů; důvody vyloučení |
| Mobilní použitelnost a rychlost | Lighthouse, případně dostupná data skutečných návštěvníků | Stejné testovací podmínky před úpravou a po ní |
| Návštěvy z AI služeb | GA4 podle dostupného zdroje návštěvy | Doplňkový ukazatel; část návštěv nemusí předat rozpoznatelný zdroj |

Uložíme dostupná historická data, ideálně i srovnatelné období minulého roku. Pokud historie chybí, stanovíme výchozí období po ověření měření. Sezónní vánoční růst nebudeme automaticky připisovat SEO. Průběžné výsledky vyhodnotíme každý měsíc, souhrnně v březnu 2027. Číselné cíle stanovíme až nad výchozími daty.

## Technické SEO

### Souhrn technického crawlu

Crawl 2. 10. 2026: procházení webu od úvodní stránky po interních odkazech s respektováním robots.txt, doplněné o všechny adresy ze sitemapy. Celkem 1 988 adres.

| Kontrola | Výsledek | Stav |
|---|---|---|
| Adresy nalezené přes odkazy na webu | 889 | |
| Adresy jen v sitemapě, bez odkazu z webu | 1 099 | [[pozor]] |
| Stav 200 | 1 982 | [[ano]] |
| Přesměrování 3xx | 4, hlavně odkaz „Oblíbené“ z 884 stránek vede přesměrováním na přihlášení | [[v pořádku]] |
| Chyby 4xx | 1 rozbitý interní odkaz | [[pozor]] |
| Chyby 5xx | 0 | [[ano]] |
| Řetězce přesměrování v interních odkazech | 0 | [[ano]] |
| Chybějící title nebo popisek | 0 | [[ano]] |
| Popisek opakuje titulek | 1 799 adres | [[ne]] |
| Duplicitní title | 43 skupin, 91 adres, téměř vše filtry se stejnou větou ve dvou kategoriích | [[částečně]] |
| Duplicitní H1 | 81 skupin, 233 adres, hlavně produkt a jeho varianty | [[částečně]] |
| Title delší než 60 znaků | 1 494 adres, převážně filtry | [[částečně]] |
| Více H1 na stránce | O nás, Ayush pleťový krém a Kardamon včetně variant | [[nízké]] |
| Canonical | 1 978 indexovatelných adres odkazuje samo na sebe, včetně filtrů a variant; žádný canonical nevede na chybovou adresu; /compare canonical nemá | [[částečně]] |
| noindex | jen přihlášení a registrace, žádná noindex adresa v sitemapě | [[ano]] |
| Hloubka | vše do 3 kliknutí od úvodní stránky, produkty do 2 | [[ano]] |
| Externí odkazy | 13, z toho 4 z úvodní stránky na nápovědu Upgates | [[pozor]] |

Rozbitý odkaz je v popisu celé cejlonské skořice: odkaz na pepř je zapsaný bez https://, takže vede na neexistující adresu. Mezi adresami jen v sitemapě je 913 filtrů, 104 variant, 68 štítků, 2 filtry výrobce a 11 stránek: Dárkové balíčky, obě dárkové krabice, tři stránky oznámení, Naše výhody, Rádce s ukázkovým návodem a dva výrobci. Naopak porovnání /compare je odkazované a indexovatelné, ale v sitemapě není.

Technicky je web zdravý. Hlavní problém není chyba serveru, ale množství podobných adres, které vyhledávačům posíláme.

> **Oprava:** opravit odkaz na pepř, rozhodnout o adresách jen v sitemapě podle kapitoly Rozhodnutí o typech stránek a crawl zopakovat po každé větší změně.

**Hotovo znamená:** crawl nehlásí chyby 4xx a 5xx a sitemapa obsahuje jen adresy, které jsou zároveň odkazované z webu a mají být ve vyhledávání.

### [[vysoké]] Sitemapa obsahuje velké množství automatických URL

Sitemapa je seznam adres určených vyhledávačům. Zaznamenaná struktura obsahuje 1 979 URL:

| Typ adresy | Počet |
|---|---:|
| Filtry podle parametru (/caje/p-…) | 1 721 |
| Varianty produktů (/p/…/240) | 104 |
| Štítky (/caje/t-…) | 68 |
| Produkty | 59 |
| Kategorie a obsahové stránky | 12 |
| Pomocné stránky a ukázkový obsah | 13 |
| Filtry podle výrobce | 2 |

Mimo 59 základních produktů a 12 kategorií či obsahových stránek je tedy 1 908 adres dalších typů. Nejsou automaticky všechny zbytečné. Každý typ posoudíme podle obsahu, obchodního přínosu a dostupných dat.

Nejvíc filtrů vzniklo z textových parametrů Použití (344 adres), Tip (202), Příprava (191), Tradiční využití (171), Zajímavosti (168) a Chuťový profil (162). Kontrola uvádí také 808 různých odkazů z 52 produktů na filtry. Tyto odkazy a sitemapu je třeba řešit společně.

Velké množství podobných stránek může vytvářet zbytečné procházení a komplikovat správu indexace. Bez Search Console a logů ale nevíme, zda již omezuje procházení důležitých produktů. Samotný počet URL nedokazuje zhoršení hodnocení celého e-shopu.

> **Oprava:** textové informace, které nepomáhají filtrovat nabídku, ponechat jako obsah produktu a omezit z nich vznikající filtry a odkazy. U ostatních filtrů a štítků rozhodnout individuálně. Smysluplné vstupní stránky ponechat; u nepřínosných stránek nastavit odpovídající režim indexace a zkontrolovat jejich odstranění ze sitemapy. Chování Upgates ověřit v administraci a následným crawlem.

**Hotovo znamená:** existuje rozhodnutí pro jednotlivé typy URL, důležité stránky zůstávají dostupné přes interní odkazy a sitemapa obsahuje zvolené kanonické indexovatelné adresy.

### [[střední]] Varianty potřebují jednotnou strategii

Kontrola zaznamenala 104 URL variant s canonical na vlastní adresu. Canonical vyjadřuje preferovanou verzi pro vyhledávač; není to příkaz a Google může vybrat jinou adresu. Samostatná URL varianty ani canonical na sebe nejsou samy o sobě chyba.

> **Oprava:** u variant lišících se jen gramáží a téměř shodným obsahem posoudit sjednocení canonical na základní produkt. U samostatně užitečných variant lze zachovat vlastní indexaci. Rozhodnutí sladit s obsahem, strukturovanými daty a produktovými feedy. Odkaz ve feedu musí zákazníkovi otevřít správnou variantu, cenu a dostupnost. Technické možnosti ověřit s Upgates.

**Hotovo znamená:** vybrané vzorky mají konzistentní canonical, odpovídající sitemapu a funkční volbu varianty. Není porušeno nakupování ani cílové URL ve feedech.

### [[vysoké]] Pomocné stránky nejsou určené jako vstupní stránky z vyhledávání

/oznameni-dovolena, /oznameni-lista a /oznameni-kosik slouží jako zdroj obsahu pro šablonu. Kontrola dále uvádí prázdnou /why-us, porovnání /compare a přihlášení /newsletter. U /newsletter byl zaznamenán výskyt ve výsledcích Seznamu pro značku.

> **Oprava:** u stránek bez samostatného vyhledávacího přínosu nastavit noindex a vyřadit je ze sitemapy. Stránky používané šablonou nemažeme. Zkontrolovat návaznosti na lištu, košík a newsletter.

### [[nízké]] Přesměrování domény má mezikrok

Test uvádí přesměrování http://svetcejlonu.cz přes http://www.svetcejlonu.cz na https://www.svetcejlonu.cz.

> **Oprava:** požádat Upgates o přímé přesměrování na finální HTTPS adresu. Ověřit stavový kód, zachování cesty a absenci smyčky. Jde o technické zjednodušení, nikoli hlavní obchodní prioritu.

### [[střední]] Laboratorní test nevrátil LCP na některých šablonách

LCP popisuje dobu vykreslení největšího relevantního prvku stránky. Původní měření ho zaznamenalo na produktu, ale ne na úvodní stránce a kategoriích, a to při více variantách testu. Příčina není doložená. Tento výsledek neprokazuje, že Google nemá data od skutečných návštěvníků.

> **Oprava:** uložit konfiguraci a výstup testu, prověřit dokončení měření a vykreslování hlavního obsahu. Opravit až zjištěnou příčinu. Odděleně zkontrolovat dostupná terénní data; malý web jich nemusí mít dostatek pro samostatné vyhodnocení URL.

### [[vysoké]] Produktové obrázky zatěžují mobilní načítání

Původní test detailu mleté cejlonské skořice uvádí více než 4 MB přenesených dat, převážně obrázků, včetně PNG o velikosti 1,2 MB. Lighthouse na simulovaném pomalém mobilním připojení naměřil LCP 21–23 sekund a odhadl úsporu obrázků přibližně 2 MB. U kategorií zaznamenal CLS 0,15–0,21. Tyto laboratorní hodnoty nelze vydávat za průměrnou zkušenost všech zákazníků.

Medián odezvy testovaných adres byl podle kontroly 0,27 sekundy a nejpomalejší odezva 1,7 sekundy. Odezva serveru není celková doba načtení stránky.

> **Oprava:** dodat obrázky ve vhodných rozměrech a moderním formátu, ověřit responzivní varianty a rezervovat jejich rozměry v rozvržení. Hlavní viditelný obrázek nesmí zbytečně čekat na odložené načítání. Po úpravách porovnat stejné produktové a kategoriové šablony za shodných podmínek.

### [[v pořádku]] Funkční technický základ

Kontrola zaznamenala HTTPS a preferovanou doménu, přesměrování koncových lomítek, skutečnou chybu 404 na neexistující stránce, hlavní obsah čitelný bez JavaScriptu a canonical řazení produktů na kategorii. Produkty kategorií jsou dostupné bez stránkování. Smíšený HTTP obsah nebyl zaznamenán. Košík a pokladna jsou omezené v robots.txt; samotné toto omezení není zárukou neindexace ani zabezpečením obsahu.

## Interní odkazy

Interní odkazy ukazují vyhledávačům, které stránky jsou na webu důležité, a vedou k nim zákazníky. Struktura je mělká: všechny produkty jsou do dvou kliknutí od úvodní stránky.

### [[vysoké]] Na Dárkové balíčky nevede žádný odkaz

Stránka /darkove-balicky nemá při crawlu 2. 10. jediný odkaz z menu, úvodní stránky ani z žádné z 889 procházených stránek. Na úvodní stránce se dárkové balíčky nezmiňují ani ve vykresleném obsahu. Totéž platí pro velkou a malou dárkovou krabici. Vyhledávač je zná jen ze sitemapy a zákazník se k nim bez přímé adresy nedostane. Přitom jde o hlavní stránku pro Vánoce.

> **Oprava:** pokud je stránka připravená, přidat ji do hlavního menu, na úvodní stránku a do kategorií Čaje a Koření a odkázat z ní na hotové balíčky. Pokud připravená není, dokončit ji v říjnu podle plánu.

**Hotovo znamená:** crawl najde /darkove-balicky z menu a z úvodní stránky a obě dárkové krabice mají odkaz z dárkových balíčků.

### [[střední]] Kategorie se od sebe skoro neliší a neodkazují dál

Čaje, Koření, Doplňky stravy, Ájurvéda a Ostatní mají pod výpisem stejné bloky: Nejprodávanější, Náš příběh a recenze. Kromě výpisu produktů a dvou vět úvodu je obsah stejný. Mimo hlavní menu z nich nevedou odkazy na související obsah a návody na webu zatím nejsou.

> **Oprava:** bloky pod výpisem přizpůsobit kategorii. Z kategorií odkazovat na související návody a štítky, které zůstanou ve vyhledávání, z návodů zpět na produkty a kategorie.

**Hotovo znamená:** každá hlavní kategorie odkazuje na související návody a štítky a každý návod na konkrétní produkty.

## Klíčová slova a mapa stránek

Původní průzkum pracoval s našeptávači Googlu a Seznamu pro 58 výchozích slov a s první stránkou výsledků Seznamu. Hledanost nebyla doložena. Našeptávač není měření objemu poptávky; Search Console ukazuje výkon vlastního webu, nikoli celkovou tržní hledanost.

Ve sledovaném vzorku Seznamu byl web zaznamenán pro značku a na 8. místě u kari listů. Jde o jednorázové pozorování omezeného souboru dotazů, ne o úplnou viditelnost webu. Na první stránce Googlu web 2. 10. nebyl u žádného z 9 hlavních dotazů, podrobně v kapitole Konkurence.

| Téma | Doporučená role stránky | Další krok |
|---|---|---|
| Cejlonský čaj, čaje ze Srí Lanky | /caje | Zpřesnit titulek, výběr a interní odkazy |
| Černý čaj, Earl Grey; zelený a jasmínový čaj | Kategorie nebo produkty podle šíře nabídky | Ověřit poptávku a počet vhodných produktů |
| Modrý čaj, motýlí hrášek | /p/motyli-kvet a související návod | Oddělit nákup od přípravy a vysvětlení |
| Ibiškový čaj | /p/ibisek | Doplnit přípravu a prověřit zdravotní tvrzení |
| Čaj bez kofeinu | Užitečný filtr či vstupní stránka | Porovnat překryv štítků a skutečné složení nabídky |
| Cejlonská skořice, Alba | Přehled nabídky a jednotlivé produkty | Rozlišit celé svitky, mletou skořici a další varianty |
| Rozdíly mezi cejlonskou skořicí a kasií | Samostatný návod | Doložit fakta a propojit s nabídkou |
| Srílanské koření | /koreni | Doplnit použití a výběr |
| Kurkuma, kari listy, kardamom, garam masala | Produkty | Zpřesnit názvy, použití a recepty |
| Moringa, amla, ashwagandha | Produkty | Doplnit doložené vlastnosti a odborně posoudit tvrzení |
| Ájurvédské produkty a kosmetika | /ajurveda | Rozlišit typy výrobků a jejich určení |
| Ájurvédský balzám | Jednotlivé produkty, případně přehled | Nezaměňovat různé výrobky za duplicity |
| Dárkový balíček čaje | /darkove-balicky | Připravit v říjnu podle skutečné nabídky |
| Louhování, masala chai, recepty | Rádce | Návody s odkazy na konkrétní produkty |
| Svět Cejlonu | Úvodní stránka, O nás, Kontakt | Konzistentní a doložitelné informace o značce |

### [[střední]] Názvy mají odpovídat způsobu hledání

Do názvů a popisů přirozeně zahrnout relevantní varianty, například kardamom a prášek u mletých výrobků. Četnost a obchodní význam výrazů potvrdit daty. Z našeptávačů nevyvozovat, že pro Google jsou důležité jen návody a pro Seznam jen katalogy.

## On-page a konkrétní návrhy textů

### [[vysoké]] Úvodní stránka a kategorie nedostatečně představují sortiment

Zaznamenaný titulek úvodní stránky obsahuje jen značku a popisek obecné přivítání. Kategoriové popisky opakují titulky. Tyto prvky nevyužívají prostor k představení nabídky; samy o sobě ale nevylučují zobrazení webu na obecné dotazy.

### Pracovní návrhy titulků

| Stránka | Návrh titulku |
|---|---|
| Úvodní stránka | Cejlonské čaje a koření ze Srí Lanky – Svět Cejlonu |
| Čaje | Cejlonské a bylinné čaje – Svět Cejlonu |
| Koření | Koření ze Srí Lanky a cejlonská skořice – Svět Cejlonu |
| Dárkové balíčky | Dárkové balíčky s čajem a kořením – Svět Cejlonu |

### [[střední]] Produktové popisky a názvy sjednotit

U 35 produktů bylo zaznamenáno slovo psané verzálkami a u 18 celý název. Názvy sjednotit kvůli čitelnosti, běžné značky a zkratky zachovat. Vizuální verzálky lze případně řešit stylem šablony.

### [[nízké]] Hierarchie nadpisů

Kontrola uvádí pět H1 u krému Ayush a dva u kardamomu a stránky O nás. Počet H1 není sám o sobě důkaz sankce ve vyhledávání.

> **Oprava:** zachovat jasný hlavní nadpis stránky a v popisu používat přehledné podnadpisy podle struktury informací.

### [[vysoké]] Strukturovaná data obsahují chybné údaje

Strukturovaná data jsou údaje ve zdrojovém kódu, podle kterých vyhledávače poznají firmu, název webu, cenu a hodnocení. Upgates je vypisuje jako mikrodata. Kontrola zdrojového kódu 2. 10. zaznamenala:

| Údaj | Stav | Zjištění |
|---|---|---|
| Název webu | [[chybné]] | „Marek Bezdíček“ místo názvu značky |
| Firma v patičce | [[chybné]] | název „Marek Bezdíček“, cenová hladina „$$$$$$“, bez adresy |
| Recenze produktů | [[chybné]] | neplatný formát data a hodnocení recenze zapsané jako souhrnné hodnocení |
| Produkt a nabídka | [[částečně]] | název, cena, dostupnost a hodnocení ano; značka a EAN chybí |
| Odkazy na profily firmy | [[chybí]] | Facebook, Instagram, Heureka ani Firmy.cz |
| Doprava a vrácení zboží | [[chybí]] | Google je umí převzít z údajů o firmě |
| Drobečková navigace | [[ano]] | |
| Typ stránky pro sdílení (og:type) | [[chybné]] | na úvodní stránce neplatná hodnota „web“ |

Recenze má ve strukturovaných datech 38 z 59 produktů. Jestli Google hvězdičky ve výsledcích ukáže, rozhoduje sám, chybný zápis je ale může vyřadit.

> **Oprava:** v šabloně opravit název webu a údaje o firmě, zápis recenzí podle schema.org, doplnit odkazy na profily, značku a pravidla dopravy a vrácení. Ověřit v Rich Results Testu a validátoru schema.org na úvodní stránce, kategorii a dvou produktech.

**Hotovo znamená:** validátory nehlásí chyby a název webu i firmy odpovídá značce.

## Obsah a důvěryhodnost

### [[kritické]] Riziková zdravotní a léčebná tvrzení

Původní kontrola označila 15 produktů: deset čajů či doplňků a pět mastí, balzámů nebo zubních past. Jde o formulace spojené například s krevním tlakem, cholesterolem, průjmem, nespavostí, úzkostí, infekcemi, diabetem nebo plísněmi. U ibišku bylo zaznamenáno srovnání s léky.

U potravin včetně doplňků stravy je nutné rozlišovat nepřípustná léčebná tvrzení od zdravotních tvrzení a jejich podmínek. Vedle schválených tvrzení existuje u některých tvrzení režim on hold, jehož použitelnost vyžaduje individuální posouzení. Kosmetika a jiné typy výrobků mají odlišný právní režim. Přidání slov o tradičním používání samo o sobě problematický slib neřeší.

> **Oprava:** sestavit seznam URL s přesnou citací, typem výrobku a umístěním textu. Rizikové formulace přednostně řešit s odborníkem na příslušnou regulaci. Kontrolovat i parametry, filtry, krátké popisy a feedy, do kterých se texty přenášejí. Bez opory neslibovat léčbu ani prevenci onemocnění.

**Hotovo znamená:** existuje odborně posouzené znění a problematické formulace nejsou dál přebírány do dalších částí webu a produktových výstupů.

### [[vysoké]] Odstranit ukázkový obsah Upgates

Kontrola uvádí čtyři ukázkové aktuality, návod na brož a výrobce Upgates na /m/upgates. Návaznosti z úvodní stránky a sitemapy mohou tento obsah zbytečně zpřístupňovat zákazníkům i vyhledávačům.

> **Oprava:** odstranit ukázkové položky a jejich interní odkazy, ověřit sitemapu. U adres bez relevantní náhrady vracet skutečnou 404 nebo 410; nepřesměrovávat všechny nesouvisející stránky na úvodní stránku. Před odstraněním prověřit případné užitečné odkazy a závislosti v šabloně.

### [[vysoké]] Zpřístupnit úplné kontakty

Chyba 404 na /kontakt. Samotná chybějící adresa nevylučuje, že kontakty existují jinde, ale zákazník musí snadno najít provozovatele a cestu k řešení dotazu.

> **Oprava:** Vytvořit či doplnit přehlednou stránku Kontakt. Uvést správného provozovatele, IČO, e-mail, telefon a skutečné adresy s vysvětlením jejich účelu. Propojit stránku z navigace nebo patičky. Nezaměňovat sídlo za výdejnu či prodejnu.

### [[nízké]] Drobnosti, které stojí za opravu

- Obchodní podmínky označují provozovatele jako „obchodní společnost“, podle ARES jde o fyzickou osobu podnikatele.
- Lighthouse na detailu produktu hlásí nízký kontrast části textu a nadpisy mimo pořadí, na úvodní stránce ukazatel průběhu bez popisu pro čtečky.
- Soubory produktových fotek se jmenují čísly nebo automaticky vygenerovaným názvem. Nové fotky nahrávat s popisným názvem, staré kvůli tomu přejmenovávat nemusíme.

## Rozhodnutí o typech stránek

| Typ | Zaznamenaný počet | Doporučený postup |
|---|---:|---|
| Základní produkty | 59 | Zachovat užitečné produkty a zlepšit informace; odlišit vyřazené položky |
| Kategorie a obsahové stránky | 12 | Posoudit jednotlivé stránky, doplnit výběr a kontakty |
| Štítky | 68 | Ponechat obchodně užitečné vstupní stránky, ostatní posoudit pro noindex |
| Parametrické a výrobní filtry | 1 723 | Prioritně omezit filtry z celých textových vět; ostatní rozhodnout podle přínosu |
| Varianty | 104 | Canonical a indexace podle podobnosti obsahu, způsobu výběru a feedů |
| Pomocné stránky a ukázkový obsah | 13 | Oddělit nezbytné zdroje šablony od položek k odstranění; doložit úplný seznam |

Před hromadnou úpravou uložit seznam původních URL a navržených změn. Zkontrolovat dostupný výkon a odkazy. Nepřenášet automaticky jedno pravidlo na všechny produkty, filtry a varianty.

## Katalogy, profily a odkazy

Záznam veřejného průzkumu uvádí Heureku s 54 recenzemi a 100% doporučením, profily na Firmy.cz a Zboží.cz bez hodnocení, Facebook s 923 a Instagram se 423 sledujícími. Jde o časový snímek. Druhý profil na Firmy.cz se při opakovaném hledání nepodařilo potvrdit. Nenalezené články nebo kanál YouTube neznamenají prokázanou neexistenci.

### [[střední]] Konzistentní značka a správné kontakty

Profily používají varianty Svět Cejlonu a Svetcejlonu.cz, zatímco některé údaje uvádějí jméno provozovatele.

> **Oprava:** ověřit aktuální kontakty u majitele a opravit prokazatelně zastaralé údaje. Značku prezentovat konzistentně, právního provozovatele uvádět pravdivě.

### [[střední]] Hodnocení a relevantní odkazy

Zavést přiměřenou prosbu o autentické hodnocení po nákupu Heureka, firmy, zboží. Ověřit podmínky a potřebné měřicí integrace jednotlivých služeb; samotný produktový feed nezaručuje sběr recenzí. Odkazy budovat přes skutečné partnery, odběratele, čajovny a vlastní obsah, který má pro jejich publikum hodnotu.

## Konkurence

Srovnání s pěti e-shopy, které se v nabídce ze Srí Lanky opakovaně objevují ve výsledcích: cejlonskekoreni.cz, cejlonskycaj.cz, manutea.cz, caje-cejlon.cz a zdravizesrilanky.cz. Výsledky Googlu jsou z 2. 10. 2026, první stránka, jedno měření bez přihlášení. Pozice se mění podle místa, zařízení a času, jde o snímek.

### Co Google ukazuje na hlavní dotazy

| Dotaz | Typy stránek na první stránce Googlu | Svět Cejlonu |
|---|---|---|
| cejlonská skořice | 5 produktů, 4 návody, 1 srovnávač | [[ne]] |
| pravá cejlonská skořice | 6 produktů, 1 návod, 1 srovnávač, AI přehled | [[ne]] |
| cejlonský čaj | Wikipedie, 4 kategorie e-shopů, 2 návody, 1 srovnávač, 1 produkt | [[ne]] |
| sypaný čaj | 7 kategorií a úvodních stránek e-shopů, 1 návod | [[ne]] |
| modrý čaj | 5 produktů, 3 návody, 1 kategorie | [[ne]] |
| ájurvéda produkty | 10 kategorií a úvodních stránek e-shopů | [[ne]] |
| dárkový balíček čaje | 7 kategorií e-shopů, 1 srovnávač | [[ne]] |
| kari listy | 8 produktů, 1 srovnávač, 1 návod | [[ne]] (Seznam 8. místo) |
| kurkuma mletá | 6 produktů, 1 návod, 1 srovnávač | [[ne]] |

U skořice a modrého čaje Google míchá produkty a návody, protože lidé chtějí koupit i pochopit, co kupují. Na prvních místech jsou produkty, které samy vysvětlují rozdíl mezi cejlonskou skořicí a kasií a dokládají kvalitu, a návody, které odpovídají na otázky z bloku „Lidé se také ptají“. U obecných dotazů jako sypaný čaj, ájurvéda produkty nebo dárkový balíček čaje vedou kategorie a úvodní stránky větších specializovaných e-shopů. U jednotlivých surovin vedou detaily produktů a tam má Svět Cejlonu nejblíž.

### Srovnání s konkurencí

| Oblast | Svět Cejlonu | Konkurence |
|---|---|---|
| Kategorie | 6 hlavních, bez podkategorií | cejlonskycaj.cz dělí sypaný čaj na černý, zelený, aromatizovaný a bylinný; manutea.cz třídí podle druhu, země původu i byliny; caje-cejlon.cz má kategorii Čaje ze Srí Lanky |
| Články | žádné vlastní, jen ukázkový obsah | manutea.cz magazín s desítkami článků; cejlonskekoreni.cz 9 článků a 16 receptů; caje-cejlon.cz 6 článků |
| Title a H1 kategorie | „ČAJE :: Svět Cejlonu“ a „ČAJE“ | manutea.cz „Cejlonský čaj \| ManuTea.cz“, caje-cejlon.cz „Čaje ze Srí Lanky“ |
| Text kategorie | asi 30 slov nad výpisem | manutea.cz 161 slov o oblastech, nadmořské výšce a historii a odkazy na magazín; caje-cejlon.cz 37 slov; ostatní žádný |
| Otázky a odpovědi | žádné | caje-cejlon.cz u produktu a v článku, cejlonskekoreni.cz v článku o skořici |
| Interní odkazy | drobečková navigace a související produkty | cejlonskekoreni.cz propojuje produkt a článek oběma směry; manutea.cz odkazuje z kategorie na články a u produktu má záložku z magazínu |
| Původ a důvěra | příběh zakladatele, škola, Heureka, recenze; u produktu jen „Srí Lanka (Cejlon)“ | cejlonskekoreni.cz laboratorní test kumarinu a vlastní mletí; manutea.cz oblast, nadmořská výška a sklizeň; caje-cejlon.cz výrobce a dovozce |
| Strukturovaná data | produkt, nabídka, hodnocení a recenze | cejlonskekoreni.cz a cejlonskycaj.cz mají u produktu cenu dopravy, u cejlonskekoreni.cz ji Google ukazuje přímo ve výsledku; hodnocení nemají |

### [[vysoké]] Co konkurenti dělají lépe

- píšou články a návody, které odpovídají na otázky zákazníků,
- mají u kategorií a produktů otázky a odpovědi (FAQ),
- mají v kategoriích delší vlastní text,
- dokládají původ a kvalitu produktů,
- propojují články s produkty a kategoriemi,
- mají podrobnější členění kategorií,
- mají recepty,
- mají ve strukturovaných datech i cenu dopravy.

> **Oprava:** psát články a návody, doplnit FAQ ke kategoriím a hlavním produktům, rozšířit texty kategorií, doplnit informace o původu, propojit obsah s produkty a zvážit podrobnější členění kategorií. Uvádět jen to, co jde doložit.

**Hotovo znamená:** hlavní kategorie a produkty mají vlastní text a FAQ a na webu jsou první články propojené s produkty.

### [[v pořádku]] V čem je Svět Cejlonu napřed

Hodnocení produktů ve strukturovaných datech (cejlonskekoreni.cz a cejlonskycaj.cz je nemají), osobní příběh zakladatele, podpora tamilské školy a hodnocení z Heureky přímo na webu.

### Otázky, na které web zatím neodpovídá

Google u hlavních dotazů v bloku „Lidé se také ptají“ ukazoval otázky, kolik cejlonské skořice denně, jak poznat pravou skořici, jak připravit a jak chutná modrý čaj, jaké vedlejší účinky má kurkuma a jaké účinky má cejlonský čaj. Odpovědi o účincích a dávkování patří pod stejná pravidla jako zdravotní tvrzení u produktů.

## GEO

### [[vysoké]] AI musí poznat, kdo za značkou stojí

AI služby skládají informace o firmě z webu, katalogů a profilů. Dnes se značka objevuje ve více podobách, strukturovaná data uvádějí jméno provozovatele místo značky a na webu chybí stránka Kontakt (viz kapitoly On-page a Katalogy).

### [[vysoké]] Web zatím neodpovídá na otázky zákazníků

Citovat se dají stránky, které na položenou otázku přímo odpovídají. Na webu zatím nejsou návody ani odpovědi u kategorií. Silná stránka Světa Cejlonu jsou fakta z vlastní zkušenosti: oblast původu, pěstitelé, sklizeň, zpracování. Ta jiné e-shopy nemají.

### [[vysoké]] Zdravotní tvrzení se mohou dostat do odpovědí AI

Formulace o léčebných účincích (kapitola Obsah a důvěryhodnost) mohou AI služby převzít do odpovědí o značce nebo produktu.

### [[střední]] Produktová data pro nákupní odpovědi

Nákupní odpovědi Googlu pracují s produktovými daty z Merchant Center. Úplný a pravdivý feed s názvem, cenou, dostupností, variantou a obrázkem je proto podklad i pro AI.

### [[střední]] Bing a IndexNow

Copilot čerpá z Bingu. Založit Bing Webmaster Tools, sledovat přehled AI citací a ověřit, jestli Upgates podporuje IndexNow.

## Měření a kontrola výsledků

### [[vysoké]] Ověřit účty a nákupní události

Přístup do všech účtů, GA4, GTM, Google search console apod.

## Vánoce, feedy a další příležitosti

### [[vysoké]] Dárkové balíčky připravit během října

Obsah, dostupnost, fotografie, titulky a interní odkazy připravit co nejdříve v říjnu. Listopad využít k doladění podle prvních výsledků.

## Od vyhledávání k objednávce

SEO přivede člověka na produkt, objednávka ale vzniká až v košíku. Cestu jsme 2. 10. prošli na mobilu od detailu produktu po košík.

### [[v pořádku]] Nákupní cesta funguje

Detail produktu ukazuje ceny variant, dostupnost, hodnocení, dopravu zdarma od 1 400 Kč, dárek ke každé objednávce a odesílání každý den. Přidání do košíku proběhlo. Košík ukazuje, kolik zbývá do dopravy zdarma, a objednávka má tři kroky.

### [[nízké]] Drobnosti na mobilu

- Tlačítko pro přidání do košíku je až pod prvním zobrazením, nad ním je galerie a výběr varianty.
- Cena dopravy pod hranicí pro dopravu zdarma není na detailu produktu vidět.
- Počítadlo pro tamilskou školu v košíku ukazovalo 0 Kč z cíle 10 000 Kč.

> **Oprava:** po zprovoznění nákupních událostí v GA4 zjistit, kde lidé z vyhledávání odcházejí, a teprve podle toho měnit. Ověřit, že počítadlo pro školu počítá správně.

## Správa e-shopu po úklidu

Většina zbytečných adres vznikla jedním hromadným importem parametrů. Bez pravidel se stejný stav vrátí s dalším importem.

### [[vysoké]] Pravidla pro přidávání a úpravu produktů

- Tipy, přípravu, použití a upozornění psát do popisu, ne jako filtrovatelný parametr.
- Název psát běžným písmem a se slovem, které lidé hledají.
- Krátký popis začínat větou, co produkt je. Prvních asi 155 znaků má dávat smysl samo o sobě.
- Žádná léčebná tvrzení v názvu, popisu, parametrech ani ve feedu.
- Hlavní fotku nahrávat v rozumné velikosti, s popisným názvem souboru a alt textem.
- Variantu zakládat jen pro skutečně jinou gramáž nebo balení.

> **Oprava:** sepsat pravidla do jednoho dokumentu pro každého, kdo produkty zakládá, a po každém hromadném importu zkontrolovat počet adres v sitemapě.

**Hotovo znamená:** pravidla jsou sepsaná a další import nepřidá do sitemapy nové filtry z textových parametrů.

### [[střední]] Vyprodané a vyřazené produkty

Zboží ze Srí Lanky závisí na dovozu a část produktů bude občas nedostupná. Bez pravidla se pokaždé rozhoduje jinak.

- Dočasně vyprodaný produkt: stránku nechat, uvést, že je momentálně nedostupný, a odkázat na podobné zboží.
- Trvale vyřazený produkt s nástupcem: přesměrovat na nástupce.
- Trvale vyřazený produkt bez náhrady: vrátit 404 nebo 410 a odstranit odkazy na něj. Nepřesměrovávat na úvodní stránku.

### [[střední]] Kontrola po změnách

Šablona, nastavení Upgates i hromadné importy můžou nenápadně změnit indexaci. Jednou měsíčně zkontrolovat robots.txt, počet adres v sitemapě podle typu, noindex a canonical na vzorku hlavních stránek, stavové kódy hlavních kategorií a produktů a chyby v Search Console. Výsledek patří do měsíčního přehledu.

## Plán a varianty spolupráce

Hlavní cíl do března je dostat e-shop v Googlu výš u dotazů, které přivádějí objednávky. Proto má přednost to, co Google při hodnocení stránek používá: co má v indexu, titulky a popisky, obsah kategorií, články a odkazy mezi nimi. Ostatní nálezy z auditu, třeba GEO, profily v katalozích a drobnosti, řešíme, až na ně zbude prostor, nebo ve větší variantě.

Konkrétní pozice předem neslibujeme. Úpravy se v Googlu obvykle projeví po několika týdnech až měsících a výsledek ovlivňuje i konkurence.

Po celou dobu průběžně sledujeme pozice hlavních dotazů v Googlu, indexaci a chyby v Search Console. Každý měsíc pošleme krátký přehled: co je hotové, co se změnilo v pozicích a návštěvnosti a co je na řadě. Podle toho pořadí úkolů upravujeme.

::: box Varianta A
### Péče o pozice v Googlu

**5 000 Kč** / měsíc, říjen až březen

- úklid indexu a sitemapy
- titulky, popisky a strukturovaná data
- texty a FAQ pro kategorie Čaje a Koření
- dva články v Rádci (blog) propojené s produkty
- průběžné sledování pozic, indexace a chyb
- měsíční přehled
:::

::: box Varianta B
### Rozšířená péče

**7 000 Kč** / měsíc, říjen až březen

- vše z varianty A
- texty a FAQ pro všechny kategorie
- čtyři články místo dvou
- podkategorie Černé čaje a Zelené čaje
- zmenšení fotek u hlavních produktů
- produktové feedy pro Google a Zboží.cz
:::

### Co se kdy udělá

| Měsíc | Varianta A | Navíc ve variantě B |
|---|---|---|
| Říjen | Search Console a Seznam Webmaster, výchozí stav pozic a návštěvnosti, úklid indexu a sitemapy, odstranění ukázkového obsahu, oprava rozbitého odkazu | produktové feedy pro Google a Zboží.cz |
| Listopad | titulky a popisky úvodní stránky, kategorií a dárkových balíčků, oprava strukturovaných dat | zmenšení fotek u hlavních produktů |
| Prosinec | kontrola indexace a pozic, pravidla pro přidávání produktů; v sezóně nic velkého neměníme | první článek |
| Leden | vyhodnocení Vánoc, texty a FAQ pro kategorie Čaje a Koření | texty a FAQ pro ostatní kategorie |
| Únor | článek o cejlonské skořici propojený s produkty, zákaz filtrů v robots.txt po jejich vyřazení z indexu | podkategorie Černé čaje a Zelené čaje |
| Březen | druhý článek nebo FAQ k hlavním produktům, vyhodnocení proti říjnu a plán na další měsíce | další článek |

### Co od vás budeme potřebovat

- přístupy do administrace Upgates, Google Analytics a k ověření Search Console,
- fakta k produktům a kategoriím: oblasti původu, pěstitelé, zpracování, vlastní zkušenosti,
- texty článků; stačí nám samotný text, na web ho vložíme, graficky upravíme a propojíme s produkty,
- posouzení zdravotních tvrzení odborníkem; seznam rizikových formulací vám předáme, samotné posouzení v ceně není.

### Co v nabídce není

Texty u všech produktů, recepty, oslovování médií a blogů, videa na YouTube a větší úpravy rychlosti celého webu. Pokud na ně bude prostor nebo chuť, domluvíme je zvlášť.
