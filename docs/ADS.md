# Reklamy klientů

Interní nástroj pro správu menších klientů s rozpočtem v řádu tisíců měsíčně.
Každé ráno stáhne čísla z reklamních účtů, uloží je, zkontroluje, co se pokazilo,
a připraví podklad pro týdenní report. Pavel pak nemusí každý účet otevírat ručně
a na jednoho klienta stačí pár minut týdně.

Panel nástrojů → skupina **Reklamy**:

| Obrazovka | Adresa | Co tam je |
|---|---|---|
| Přehled klientů | `/nastroje/reklamy` | nahoře ranní souhrn („2 problémy s daty, 3 výkonové odchylky, 1 končící licence…“), karta na klienta: stav dat, výsledek proti cíli, nejdůležitější upozornění, šest čísel podle šablony cíle, skupiny kampaní, čerpání rozpočtu, nejbližší termín |
| Detail klienta | `/nastroje/reklamy/klient/{id}` | stav dat, přepínač celý účet / skupina / mimo skupiny, výsledek proti cíli, dlaždice se srovnáním, graf po dnech se zásahy a termíny, události po cestě k nákupu, termíny, historie zásahů, reklamy a kreativy, tržby e-shopu, kvalita leadů, kampaně se skupinami, domluva, účty, reporty |
| Upozornění | `/nastroje/reklamy/upozorneni` | záložky Problémy s daty / Výkon proti cíli, stavy Nové / Řeším / Čekám na klienta / Vyřešeno, odložení, zamítnutí |
| Termíny | `/nastroje/reklamy/terminy` | kalendář: konce licencí UGC, akce, příprava Black Friday, konce kampaní, čekání na podklady |
| Reporty | `/nastroje/reklamy/reporty` | koncepty a odeslané reporty |
| Hodiny | `/nastroje/reklamy/hodiny` | zapsaný čas u klientů, filtr podle měsíce, hromadně „vyfakturováno“ |
| Fakturace (v CRM) | `/nastroje/fakturace` | paušál + hodiny nad paušál po klientech za měsíc, vyhrané jednorázové zakázky, co je vyfakturované, kdo kolik odpracoval |
| Nastavení reklam | `/nastroje/reklamy/nastaveni` | stav napojení, stav dat po účtech, příjemci souhrnu, prahy upozornění, šablony čísel, log synchronizace |

Postavené podle zpětné vazby Pavla z 23. 10. 2026 (pilot na Světě Cejlonu a MIRNIX Hockey).
Hlavní cíl: ráno vědět, čemu věřit, kde zasáhnout a na co nezapomenout.

## Jak se napojí klient

Agenturní model: klient nám účet **nasdílí**, jeho přihlašovací údaje ani tokeny
nikde neukládáme.

1. Klient v Meta Business Suite → Nastavení firmy → Partneři přidá Business Manager
   Taveo a nasdílí mu reklamní účet (stačí oprávnění „Zobrazit výkon“).
2. V Business Manageru Taveo přiřadíme účet systémovému uživateli.
3. V nástrojích Reklamy → Přehled → **Přidat klienta**. Účet se vybírá
   ze seznamu toho, co token vidí, nic se neopisuje. Při propojení se vyplní cíl
   (nákupy, poptávky, návštěvnost), měsíční rozpočet, cílová cena za konverzi,
   cílový ROAS a náš paušál.
4. Na pozadí se stáhne historie za 3 měsíce (`ADS_BACKFILL_MONTHS`), jeden dotaz.

Delší historie je připravená, ale vypnutá, dokud se nedomluvíme s Pavlem kvůli
zatížení API. Zapíná se v `.env`:
- `ADS_BACKFILL_MONTHS=37`: při propojení celá historie, kterou Meta vydá
  (po čtvrtletích, asi 13 dotazů na účet jednou provždy),
- `ADS_HISTORY_BACKFILL=true`: tlačítko **Další → Doplnit starší historii** na detailu
  klienta. Stáhne jen chybějící úsek, jde použít jednou za hodinu.

Období se volí nahoře na přehledu i detailu: přednastavená (včera, předevčírem,
poslední 2 dny, 7 dní, 30 dní, tento a minulý měsíc) nebo **Vlastní období** od–do.
Pod tím **Porovnat**: s předchozím obdobím (u měsíce stejně dlouhá část minulého
měsíce), nebo se stejnými dny před týdnem (včerejší úterý s úterým minulého týdne).
Volba čte jen z naší databáze, na Metu nejde žádný dotaz. Když období začíná dřív,
než máme čísla, stránka na to upozorní a srovnání neukáže.

## Čemu věřit: stav dat

U každého účtu je `data_through`: poslední den, za který máme čísla kompletně stažená.
Posune ho jen stažení, které navazuje na to, co už máme (ranní běh, ruční obnova).
Stav klienta počítá `App\Support\Ads\DataStatus` (u víc účtů platí nejslabší):

| Stav | Kdy | Co se děje |
|---|---|---|
| Data do 22. 10. | stažení prošlo, data až do včerejška | vše běží, poslední 3 dny jsou v grafu světlejší („ještě se dopočítává“, Meta dohání konverze) |
| Data jen do … | chybí aspoň den | chybějící dny jsou v grafu šrafované („chybí data, není nula“), srovnání se neukazuje, čerpání rozpočtu a odhad se počítají jen do posledního kompletního dne, výkonová pravidla neběží a jejich upozornění se nezavírají, svítí „Nestahují se čísla“ |
| Chyba stahování | poslední stažení selhalo | jako výš, a s textem chyby |
| Čeká na první stažení | účet propojený, ještě nestažený | žádná čísla, ne nuly |
| Ukázková data | jen ukázkové účty | štítek na kartě i v detailu, ať se nepletou s ostrými |

Nula se ukáže jako nula jen tam, kde je den stažený. Jinak „čísla chybí, není to nula“.
Ranní běh sám doplní díru od posledního kompletního dne (nejvýš `ADS_CATCH_UP_DAYS`, 92 dní = 1 dotaz).

**Ruční obnova:** detail klienta → Další → **Obnovit čísla**. Odsud se na Metu nesahá:
zapíše se `refresh_requested_at` a žádost do 5 minut vyřídí `ads:sync --requested`
(stejné období jako ráno i s dosahem, stavem kampaní a reklamami, pak přepočítá upozornění).
Jeden účet nejvýš jednou za `ADS_REFRESH_COOLDOWN` minut (výchozí 120).

**Export:** Další → **Stáhnout CSV**: každý den a kampaň, součty po kampaních, nebo po dnech.
Středník a desetinná čárka (otevře se rovnou v Excelu), poměry ze součtů řádku, platí zvolené
období a skupina.

## Skupiny kampaní

Jeden účet často míchá prodej, boosting, testy a nábor. **Skupina** (`ad_campaign_groups`)
má vlastní typ (e-shop, leady, návštěvnost, boosting, nábor), cíl, rozpočet, cílovou cenu
a ROAS, výběr čísel, prahy upozornění a report. Kampaň je nejvýš v jedné skupině, ať se
útrata nepočítá dvakrát.

- Založení: detail klienta → **Nová skupina kampaní**, kampaně se zaškrtnou.
- **Pravidlo z názvu** („sales, prodej“): nová kampaň, jejíž název slovo obsahuje, se při
  ranním stažení zařadí sama. Ostatní nové kampaně **čekají na zařazení** (detail, ranní souhrn).
  Ruční „Nechat mimo skupiny“ má přednost, pravidlo ji zpátky nepřidá.
- Nahoře na detailu přepínač **Celý účet / skupiny / Mimo skupiny** s útratou každé části.
  Celý účet sčítá všechno, ať se neztratí náklady mimo výkonnostní kampaně.
- Výběr platí pro čísla, graf, kampaně, čerpání, report, export i návrh od Clauda.
- Upozornění běží za každou skupinu s jejím cílem a prahy (titulek „Prodej: …“). Za celý účet
  jen problémy s daty, rozpočet účtu a nulová útrata účtu.
- **Dosah skupiny** se přes kampaně sčítat nedá: ranní stažení pošle za skupinu jeden dotaz
  s filtrem kampaní (`ad_group_reach`). Řádek nese otisk složení skupiny; po změně skupiny
  dosah do rána nemá (pomlčka). „Mimo skupiny“ dosah nemá.
- Kód: `AdsScope` (na co se díváme), `CampaignGroups` (zařazování), `GroupReach`.

Další účet téhož klienta: detail klienta → Další → Propojit účet. Vypnout nebo
odpojit účet jde v Klienti → Klienti → klient → Reklamní účty.

## Ukázková data

Dokud nejsou přístupy k Metě a Googlu, běží pět ukázkových klientů („Ukázka: …“)
s vymyšlenými čísly. Každý ukazuje jinou situaci: Pražírna Zrnko má Metu, Google Ads
i GA4, Levandule rozbité měření, Fitness Hradec přečerpaný rozpočet, Čajovna Lístek
drahé nákupy. Čísla dává ukázková platforma a denní `ads:sync` je doplňuje, takže
nestárnou. Dva volné ukázkové účty (Knihkupectví Stránka, Květinářství Pivoňka) jdou
zkusmo propojit přes Přidat klienta → Odkud: Ukázková data.

Na produkci je založila datová migrace. Po napojení skutečných účtů:

```bash
php artisan ads:demo --remove   # smaže jen klienty, kteří mají výhradně ukázkové účty
```

a do `.env` `ADS_DEMO=false`. Znovu založit: `php artisan ads:demo`.

## Přístup k Metě (jednorázově)

1. [developers.facebook.com](https://developers.facebook.com) → vytvořit aplikaci
   typu Business, přiřadit ji k Business Manageru Taveo, přidat produkt Marketing API.
2. Business Manager Taveo → Uživatelé → Systémoví uživatelé → nový (role Admin),
   přiřadit mu aplikaci a reklamní účty klientů.
3. Vygenerovat token s oprávněním `ads_read` (volba „Nikdy nevyprší“).
4. Na server do `.env`:

   ```
   META_SYSTEM_USER_TOKEN=…
   META_APP_SECRET=…        # Nastavení aplikace → Základní, podepisuje volání
   META_API_VERSION=v25.0
   ```

5. `php artisan config:cache`. Spojení ověří další ranní stažení, výsledek je v Nastavení
   reklam → Napojení (kolik nasdílených účtů Meta ráno viděla, nebo chyba).

Na denní čtení ~20 účtů stačí úroveň přístupu Limited (dřív Standard). Když
začne Meta omezovat počet dotazů (chyba „Meta omezila počet dotazů“), požádat
v aplikaci o Full Access k `ads_read` (App Review a ověření firmy).

## Přístup ke Google Ads (jednorázově)

Od 10. 9. 2026 se úroveň přístupu k Ads API řídí Google Cloud projektem, ne developer
tokenem. Basic Access se schvaluje automaticky po ověření značky.

1. Google Cloud Console → projekt Taveo → zapnout Google Ads API, ověřit značku
   a požádat o Basic Access.
2. OAuth klient (typ Web nebo Desktop) → client ID a secret.
3. Refresh token pro uživatele s přístupem k MCC Taveo (třeba přes OAuth Playground
   se scope `https://www.googleapis.com/auth/adwords`).
4. Do `.env`: `GOOGLE_ADS_CLIENT_ID`, `GOOGLE_ADS_CLIENT_SECRET`, `GOOGLE_ADS_REFRESH_TOKEN`,
   `GOOGLE_ADS_LOGIN_CUSTOMER_ID` (číslo MCC). `GOOGLE_ADS_DEVELOPER_TOKEN` jen pokud
   ho API ještě chce.
5. Klient přijme žádost o propojení s MCC Taveo a jeho účet se objeví v nabídce.

Google má jednu metriku „konverze“. U e-shopu ji ukládáme jako nákupy (s hodnotou),
u klienta s cílem Poptávky jako poptávky.

## Přístup ke GA4 (jednorázově)

1. Google Cloud Console → projekt Taveo → zapnout Google Analytics Data API a Admin API.
2. Servisní účet → klíč JSON.
3. Do `.env` `GA4_CREDENTIALS` s cestou k souboru (mimo `public/`, třeba
   `storage/app/ga4.json`) nebo celým JSON.
4. Klient v GA4 → Správce → Správa přístupu přidá e-mail servisního účtu jako čtenáře
   (e-mail ukazuje Nastavení reklam).

GA4 se se čísly reklam **nesčítá**. Na detailu i v reportu má vlastní sekci „Co naměřil
web“: návštěvy, nákupy a tržby ze všech zdrojů a rozpad podle kanálů. Vedle toho věta,
kolik si nákupů připsaly reklamy. Rozdíl je normální, reklamní systémy si konverze
připisují samy a často dvakrát.

## Jediný vstup na platformy: ranní `ads:sync`

**Na Metu, Google Ads a GA4 sahá jen příkaz `ads:sync` (denně v 6:00).** Stáhne čísla
do databáze a všechno ostatní pracuje jen s ní: přehledy klientů, reporty, upozornění,
ranní e-mail, rozhraní a ranní přehled agenta, „Návrh úprav od Clauda“. V administraci
není žádné tlačítko, které by na Metu sáhlo.

Vynucuje to `ApiGuard`: dotaz odejde jen v procesu, kde `ads:sync` zavolal
`ApiGuard::openForSync()`. Odkudkoli jinud (web, Livewire, fronta, tinker) skončí
výjimkou „mimo ranní stažení se na platformu nevolá“ a nic neodejde. Token Mety je
jen v `.env` webu, agent v kontejneru ho nemá.

| Co | Jak to jde bez Mety |
|---|---|
| Nabídka účtů při propojování | Ranní stažení uloží seznam nasdílených účtů do `ad_shared_accounts` (1 dotaz na platformu). Nově nasdílený účet se v nabídce objeví druhý den ráno. |
| Nový účet | Propojení jen zapíše `backfill_from`. Čísla i historii stáhne další ranní běh, do té doby je přehled prázdný. |
| Doplnit starší historii | Jen zapíše `backfill_from`, chybějící úsek stáhne ranní běh. |
| Ověření spojení | Výsledek ranního stažení v Nastavení reklam → Napojení a Stav dat po účtech. |
| Aktuálnější čísla přes den | Tlačítko Obnovit čísla jen zapíše žádost, vyřídí ji `ads:sync --requested` (tentýž příkaz, do 5 minut, jednou za 2 h na účet). |

Ruční `./bin/art ads:sync` na serveru jde (je to tentýž příkaz), účty stažené ten den
ale přeskočí. Znovu je stáhne jen `--force`, používat výjimečně.

## Ochrana před přetížením API

Čísla čteme oficiálním Marketing API a pouze pro čtení (`ads_read`): nic v účtech
neměníme a žádné AI ani MCP do účtů nesahá. Claude u „Návrhu úprav“ dostává jen
čísla z naší databáze. Aby Meta ani Google nikdy nevyhodnotily provoz jako
nadměrný:

| Pojistka | Jak funguje |
|---|---|
| Jen ranní stažení | Viz výše. Z webu ani od agenta na platformu nic neodejde. |
| Historie | Nový účet jednou 3 měsíce (1 dotaz navíc v ranním běhu), pak už nikdy. Delší historie je vypnutá. |
| Jednou denně | `ads:sync` běží v 6:00. Seznam nasdílených účtů 1 dotaz, na účet Mety šest: stav účtu, čísla za 7 dní (a chybějící dny), dosah za přednastavená období, zamítnuté reklamy (jedna stránka, nejvýš 50), stav kampaní (jedna stránka) a čísla reklam za 30 dní (jedna stránka, 50 reklam). Na skupinu kampaní jeden dotaz (dosah). U 20 klientů se dvěma skupinami zhruba 160 dotazů denně. Účet stažený ten den se podruhé nestahuje. |
| Ruční obnova | `ads:sync --requested` každých 5 minut, ale jen se žádostí z tlačítka. Jeden účet nejvýš jednou za 2 hodiny. |
| Žádné opakování po omezení | Po odpovědi „moc dotazů“ se nic neopakuje. Znovu se zkouší jen výpadek spojení nebo chyba serveru, nejvýš 2×. |
| Samo se zastaví | Když Meta omezí dotazy nebo v hlavičkách hlásí vytížení nad 75 %, stahování z ní se pozastaví do zítřejšího rána. |
| Denní strop | Nejvýš 300 dotazů na Metu za den (`META_DAILY_CALL_LIMIT`, Google a GA4 200). Strop chytí chybu v kódu dřív než Meta. |
| Bez souběhu | Jeden účet se nikdy nestahuje dvakrát současně (zámek). |
| Strop stránek | Nejvýš 20 stránek výsledku na dotaz, víc znamená chybu. |

Stav je v Nastavení reklam → Ochrana před přetížením: kolik dotazů dnes odešlo
a jestli je některá platforma pozastavená. Implementace:
`App\Support\Ads\Platforms\ApiGuard`.

Přístup je přes systémového uživatele Business Manageru Taveo, ne přes osobní
facebookový profil Pavla ani Toma.

## Plánované běhy

| Čas | Příkaz | Co dělá |
|---|---|---|
| denně 6:00 | `ads:sync` | jediný přístup na platformy: seznam nasdílených účtů, přepíše posledních 7 dní všech zapnutých účtů (Meta konverze zpětně dopočítává) a chybějící dny, doplní požadovanou historii, stav kampaní, čísla reklam, dosah skupin, nové kampaně zařadí podle pravidel skupin |
| každých 5 min | `ads:sync --requested` | jen ruční obnovy z tlačítka Obnovit čísla, bez žádosti nic |
| denně 6:40 | `ads:alerts` | vyhodnotí pravidla za klienty i skupiny, otevře nová upozornění, zavře ta, která se vrátila do normálu |
| všední dny 7:15 | `ads:digest` | e-mail „Reklamy: N upozornění“ s ranním souhrnem, termíny, co se vrátilo do normálu a včerejší útratou. Když není co hlásit, neodejde |
| pondělí 6:45 | `ads:reports weekly` | koncepty týdenních reportů za minulý týden (i za skupiny se zapnutým reportem) |
| 1. v měsíci 6:45 | `ads:reports monthly` | koncepty měsíčních reportů |
| denně 6:20 | `marketing:landing-check` | kontrola cílových stránek (jeden dotaz na adresu) |

Produkce nemá frontu, všechno běží přes scheduler (`schedule:run` v cronu).

Ruční příkazy:

```bash
./bin/art ads:sync --account=123456789 --from=2026-01-01   # historie jednoho účtu
./bin/art ads:sync --days=30 --force                        # přepsat měsíc všem i po ranním běhu
./bin/art ads:alerts --date=2026-09-30
./bin/art ads:digest --dry-run
./bin/art ads:demo                                          # jen lokálně: ukázkoví klienti s vymyšlenými čísly
```

## Data

| Tabulka | Obsah |
|---|---|
| `ad_accounts` | účet klienta (platforma, číslo účtu, měna, stav, poslední synchronizace a chyba, `data_through` = do kdy jsou data kompletní, žádost o ruční obnovu) |
| `ad_campaigns` | kampaně, jejich stav (běží, vypnutá) a skupina (`grouped_by`: manual / rule) |
| `ad_campaign_groups` | skupiny kampaní: typ, cíl, rozpočet, cílová cena a ROAS, pravidlo z názvu, čísla, prahy, reporty |
| `ad_group_reach` | přesný dosah skupiny za přednastavená období, s otiskem složení skupiny |
| `ad_daily_stats` | součty na kampaň a den: útrata, zobrazení, dosah, kliknutí, prokliky, nákupy, hodnota, poptávky (z toho formulář na Metě a web), košík, pokladna, zobrazení cílové stránky, interakce + celé `actions` z API v `raw` |
| `ad_top_ads` | součty reklam za posledních 30 dní (50 s největší útratou), přepisuje se každé ráno |
| `ad_events` | kalendář termínů |
| `ad_changes` | historie zásahů |
| `ad_business_results` | objednávky a tržby z e-shopu, zapsané ručně |
| `ad_period_reach` | přesný dosah, zobrazení, frekvence a unikátní prokliky účtu za přednastavená období, přepisuje se každé ráno |
| `ad_client_settings` | cíl, rozpočet, cílové CPA a ROAS, paušál, hodiny, sazba, příjemci reportů, poslední návrh od Clauda |
| `ad_alerts` | upozornění (pravidlo, kategorie data/výkon, skupina, co se změnilo, z jakých dat, doporučení, stav, odloženo do, na co se čeká, výskyty, návrat do normálu, deník) |
| `ad_reports` | reporty se zmrazenými čísly (`snapshot`), skupina, osnova, schválení |
| `ad_sync_runs` | log stahování |

Pravidla, na kterých to stojí:

- **Ukládají se jen součty.** CTR, CPC, CPM, cena za konverzi a ROAS se počítají
  až ze součtů za období (`App\Support\Ads\Metrics`). Průměr denních poměrů by lhal.
- **Nákupy = `omni_purchase`**, jako sloupec „Nákupy“ ve Správci reklam. Pořadí
  typů akcí je v `MetaAds::ACTIONS`. Atribuce je ta, kterou má nastavený účet.
- **Zobrazení cílové stránky = `landing_page_view`**. Sloupec přibyl později, starší
  řádky doplnila migrace ze surových `actions` v `raw`.
- **Dosah, frekvence a unikátní CTR se ze součtů nepočítají.** Tentýž člověk by se
  za každý den a kampaň započítal znovu. Ranní běh proto pošle na účet Mety jeden
  dotaz navíc (`level=account`, `time_ranges`) a uloží přesná čísla za 7 a 30 dní,
  tento a minulý měsíc, minulý týden pondělí až neděle a pro každé z nich i srovnávací
  období (`PeriodReach::ranges()`). U klienta s víc účty se sčítají, frekvence
  = zobrazení / dosah. U vlastního období od–do ukážou dlaždice pomlčku s poznámkou
  „jen u přednastavených období“. Google Ads dosah nemá, do součtu nevstupuje.
  Pravidlo `creative_fatigue` dál používá odhad frekvence ze součtu denních dosahů
  (`Metrics::frequency()`).
- **Synchronizace přepisuje celé období** (smaže a vloží), opakované spuštění nic
  nezdvojí.
- **Report má čísla zmrazená.** Klient za měsíc v tomtéž odkazu uvidí stejná čísla,
  o kterých jsme spolu mluvili. Před odesláním jde koncept přepočítat tlačítkem
  „Obnovit čísla“.
- Detail klienta i report čtou stejný tvar dat (`ClientPerformance::build()`)
  a stejné zobrazení (`PerformanceView`).

## Upozornění

Pravidla jsou v `app/Support/Ads/Rules/`, pořadí v `AlertEngine::RULES`. Každé
upozornění říká **co se změnilo a proti čemu** (`facts`), **z jakého množství dat**
(`basis`, u výkonu týden, útrata, konverze, zobrazení) a **co prověřit** (`recommendation`).

- **Dvě kategorie**: problémy s daty (`AlertEngine::DATA_RULES`: stahování, stav účtu,
  měření, cílové stránky, zamítnuté reklamy, zpětná vazba k leadům) a výkon proti cíli
  (`PERFORMANCE_RULES`). Ranní souhrn i seznam upozornění je počítají zvlášť.
- **Skupiny kampaní**: výkonová pravidla běží za každou skupinu s jejím cílem, rozpočtem
  a prahy. Prahy skupiny přebíjí klienta, klient globální nastavení.
- **Neúplná data**: výkonová pravidla neběží a jejich upozornění se nezavírají.
- **Minimum dat**: cena, ROAS, únava kreativ a skok útraty mlčí pod minimální útratou
  za týden (`min_spend`, výchozí 500 Kč) a cena s ROAS pod `min_conversions`.
- **Pokles CTR** se nehlásí, když skupina plní cíl ceny nebo ROAS. Vysoká frekvence ano.
- **Chybějící pixel** se nehlásí, když Meta za týden dostává z webu události (pixel přes GTM).
- **Stavy**: Nové / Řeším / Čekám na klienta (s poznámkou, na co) / Vyřešeno / Zamítnuto
  (týden se neozve). **Odložit** na 1, 3, 7 nebo 14 dní: do té doby nesvítí v přehledu
  ani e-mailu, pak se ozve, když problém trvá.
- **Jedno upozornění na problém**: trvající se denně jen obnoví (stav zůstane). Když
  pravidlo přestane platit, zavře se jako **„vrátilo se do normálu“** (`recovered_on`,
  vidět na detailu a v ranním e-mailu). Vrátí-li se problém do 14 dní, otevře se totéž
  upozornění znovu a počítá výskyty. Každá změna se zapíše do krátkého deníku (`history`).

| Pravidlo | Kdy | Závažnost |
|---|---|---|
| `sync_failed` | stahování selhalo nebo účet dva dny nestažený | Hoří |
| `account_status` | účet zablokovaný, nezaplacený, v kontrole | Hoří |
| `no_conversions` | N dní útrata bez konverze, přitom předtím chodily. Doporučí zkontrolovat měření | Hoří |
| `no_spend` | včera nula, předchozí týden běželo | Pozor |
| `budget_pacing` | čerpání pod 80 % nebo nad 110 % plánu (od 5. dne v měsíci) | Pozor / Hoří |
| `cost_above_target` | cena za konverzi za týden o 30 % nad cílem | Pozor |
| `roas_below_target` | ROAS za týden o 30 % pod cílem | Pozor |
| `creative_fatigue` | CTR spadlo o 30 % proti minulému týdnu nebo frekvence ≥ 3,5 | Tip |
| `spend_spike` | včera o 50 % víc než průměr týdne | Tip |
| `measurement_value` | nákupy bez hodnoty objednávky (problém měření) | Hoří |
| `measurement_duplicates` | výrazně víc nákupů než zahájených objednávek (zdvojené měření?) | Pozor |
| `analytics_mismatch` | web (GA4) nehlásí nákupy, nebo si reklamy připisují mnohem víc | Hoří / Tip |
| `ad_issues` | zamítnuté nebo problémové reklamy (ranní dotaz na Metu) | Pozor / Tip |
| `landing_page` | cílová stránka neodpovídá (`marketing:landing-check`) | Hoří |
| `landing_pixel` | na cílové stránce chybí Meta pixel | Pozor |
| `lead_feedback` | leadová kampaň bez zpětné vazby od klienta přes 3 týdny | Tip |

Prahy, ztlumení a naléhavost jde nastavit u každého klienta zvlášť (Cíle a paušál
→ Upozornění pro tohoto klienta). Výpadek stahování a stav účtu ztlumit nejde.
Ranní e-mail odděluje nová upozornění od trvajících, přidá ranní souhrn, termíny
a co se vrátilo do normálu. Viz docs/MARKETING.md.

Prahy se mění v Nastavení reklam. Cena za konverzi a ROAS se hodnotí až od
10 konverzí za týden, u malého účtu dělá jedna objednávka desítky procent.

Živé upozornění se páruje podle pravidla, klienta a účtu: další den se jen obnoví.
Když pravidlo přestane platit, zavře se samo jako vyřešené. Zamítnuté se týden neozve.

## Reporty

1. Koncept („Ke kontrole“) vznikne v pondělí ráno (u klientů a skupin se zapnutým
   týdenním reportem), nebo tlačítkem **Připravit report** na detailu klienta, kde jde
   vybrat i skupinu kampaní („Za co“).
2. Osnova: **Cíl** (sám z cílů klienta nebo skupiny), čísla, náš komentář, **Výsledky**,
   **Co fungovalo**, **Co nefungovalo**, **Co jsme změnili** (sám z historie zásahů),
   **Možné příčiny** (hypotézy, kde je nevíme jistě) a **Co uděláme dál**. Ranní agent
   předvyplní výsledky, příčiny a další kroky, Pavel upraví.
3. **Náhled** ukáže report přesně tak, jak ho uvidí klient.
4. **Schválit** zamkne čísla: když Meta později dopočítá konverze, schválený report se
   nezmění (Obnovit čísla jde jen u konceptu). Nad formulářem je vidět, o kolik se čísla
   v datech od zmrazení změnila. Vrátit ke kontrole jde, dokud report neodešel.
5. **Odeslat klientovi** zapne sdílení a pošle e-mail se třemi hlavními čísly
   a odkazem. Odeslaný report je zároveň schválený. Adresy jsou v Cíle a paušál,
   jinak kontaktní e-mail klienta. Odpověď klienta jde na kontaktní e-mail webu.

„Cesta k nákupu“ se jmenuje **Události po cestě k nákupu** a místo „X % z předchozího
kroku“ ukazuje „poměr k předchozímu kroku“: Meta počítá události, ne lidi, a poměr
neříká, že titíž lidé prošli celým funnelem.

Report se nikdy neodešle sám. Otevření klientem se počítá a u klienta propojeného
s CRM se zapíše jako aktivita (stejně jako u auditu).

Report se dá vytisknout do PDF z prohlížeče, graf je SVG a tisk ho zachová.

## Hodiny nad paušál a fakturace

- Čas se zapisuje na detailu klienta (Zapsat čas) nebo v Klienti → Hodiny. Délka
  jako `1:30`, `1,5` nebo `45m`.
- Paušál, hodiny v paušálu a sazba za práci navíc jsou u klienta v Cíle a paušál.
- Fakturace (Klienti → Fakturace) ukáže za měsíc paušál, odpracované hodiny, hodiny
  navíc a částku. Tlačítko Vyfakturováno uloží měsíc do `client_invoices` i s částkou
  a označí zapsané hodiny, takže jde i u klienta jen s paušálem. Hodiny dopsané
  po faktuře svítí oranžově. Nefakturovatelný čas (oprava naší chyby, interní
  porada) se do částky nepočítá, jen do kapacity.
- Pod paušály jsou vyhrané jednorázové obchody z CRM (vše kromě Průběžné správy,
  ta se fakturuje paušálem). Nevyfakturované visí, dokud je někdo neoznačí
  (`crm_deals.invoiced_at`).
- Fakturace je rozdělená po oblastech (vývoj webu, marketing), protože zatím
  fakturuje každý sám: kdo kterou oblast, říká `users.billing_area` (administrace →
  Uživatelé → Fakturuje oblast). Přihlášenému se otevře jeho oblast. Paušál
  z nastavení reklam patří marketingu. Hodina patří oblasti podle úkolu, zápisu,
  nebo jediné oblasti klienta; u klienta s oběma oblastmi bez vyplněné oblasti
  spadne do „Bez oblasti“ a není v žádné faktuře, dokud se nedoplní. Zakázka
  bere oblast z obchodu, jinak z balíčku (`DealPackage::area()`).
- Číslo u Fakturace v menu = kolik klientů za minulý měsíc a kolik zakázek
  ještě čeká na fakturu, v oblasti přihlášeného.
- Pod tím kdo kolik odpracoval (kapacita Pavla a Toma, BRAND-STRATEGY §16.1).

## Čísla

Všechna čísla jsou v `App\Support\Ads\MetricCatalog`: název (u konverzí podle cíle
klienta), výpočet, formát, jestli je růst dobře a pro které cíle dávají smysl.

| Klíč | Číslo | Cíle |
|---|---|---|
| `spend` | Útrata | všechny |
| `conversions` | Nákupy / Poptávky / Uchazeči / Prokliky na web / Interakce | všechny |
| `form_leads`, `web_leads` | Poptávky z formuláře na Metě (instant form) a z webu (pixel) | leady, nábor |
| `cost_per_conversion` | Cena za nákup / poptávku / proklik | všechny |
| `roas` | ROAS (hodnota nákupů / útrata) | nákupy |
| `purchase_value` | Hodnota nákupů | nákupy |
| `average_order_value` | Průměrná hodnota nákupu | nákupy |
| `conversion_rate` | Konverzní poměr (konverze / prokliky) | nákupy, poptávky |
| `add_to_cart`, `cost_per_add_to_cart` | Přidání do košíku a cena za něj | nákupy |
| `checkouts`, `cost_per_checkout` | Zahájené objednávky a cena za ně | nákupy |
| `link_clicks`, `cpc` | Prokliky na web a cena za proklik | nákupy, poptávky |
| `landing_page_views`, `cost_per_landing_page_view` | Zobrazení cílové stránky a cena za něj | všechny |
| `landing_page_view_rate` | Zobrazení stránky z prokliků (%) | všechny |
| `landing_page_conversion_rate` | Nákupy / poptávky ze zobrazení stránky (%) | nákupy, poptávky |
| `reach`, `frequency`, `unique_ctr` | Dosah, frekvence, unikátní CTR odkazu (jen přednastavená období) | všechny |
| `impressions` | Zobrazení | všechny |
| `ctr`, `ctr_all` | CTR odkazu (prokliky na web / zobrazení) a CTR všech kliknutí | všechny |

U každého čísla je popis, co přesně znamená (`MetricCatalog::DESCRIPTIONS`, u konverzí
`PrimaryGoal::conversionDefinition()`): ikona „i“ u dlaždice a nápověda na kartě.
Cíl **Nábor** (`recruitment`) počítá konverze jako leady a jmenuje je Uchazeči.
| `cpm` | CPM | všechny |

## Co ukazovat

Detail klienta → Cíle a paušál → Co ukazovat: které dlaždice s čísly a které sekce
(graf, cesta ke konverzi, kampaně, rozpad podle účtů, GA4) se ukážou. Platí pro detail
i pro reporty, které klient dostane.

Bez výběru se ukáže **šablona podle cíle** (e-shop, leady, návštěvnost, boosting, nábor),
upravuje se v Nastavení reklam → Šablony čísel (`AdsSettings::$tile_templates`).
U e-shopu útrata, nákupy, cena za nákup, ROAS, hodnota nákupů, prokliky, CTR a CPM.
Skupina kampaní jede podle šablony svého typu, nebo vlastního výběru. Ostatní čísla (dosah, frekvence, košík,
zobrazení cílové stránky…) se zapínají zaškrtnutím. Výběr shodný s výchozí sadou se
neukládá, uložený výběr ze starších klíčů (`cost`, `value`, `clicks`) platí dál.
Zmrazené reporty se starším tvarem čísel se zobrazují beze změny.

## Přehled klientů: šest čísel

Každá karta v přehledu má šest čísel podle **šablony cíle klienta** (`AdsSettings::$card_templates`,
e-shop: útrata, nákupy, cena za nákup, ROAS, CTR odkazu, CPM; leady: útrata, poptávky, cena,
z formuláře, z webu, CTR). Tři tečky u čísla nabídnou celý katalog; výběr mění šablonu
(platí pro všechny klienty s tímhle cílem a pro všechny uživatele). Číslo, které u cíle
nedává smysl (ROAS u poptávek), ukáže pomlčku. `overview_tiles` už se nečte.

Karta dál ukazuje stav dat, výsledek proti cíli (cena, ROAS), nejdůležitější upozornění,
skupiny kampaní s útratou a výsledkem, čerpání rozpočtu a nejbližší termín. Když data
za období nejsou kompletní, změny v procentech se neukazují.

## Kalendář termínů

Reklamy → **Termíny** (`ad_events`) a sekce Termíny na detailu klienta: klient, případně
skupina, kampaň a konkrétní reklamy nebo videa, datum, druh (konec licence UGC, začátek
a konec akce, příprava Black Friday nebo sezony, konec kampaně, čekání na podklady), poznámka,
u licence odkaz na podmínky a odkazy na videa, a kolik dní předem upozornit
(`remind_on` = datum − dny). Od té doby je termín v ranním souhrnu, v ranním e-mailu,
v Ranním přehledu i u agenta a visí tam i po datu, dokud ho někdo neoznačí **Vyřešeno**.
Reklamu vypíná Pavel ručně, nástroj do účtů nesahá.

## Historie zásahů

Detail klienta → **Zapsat zásah** (`ad_changes`): kdy, co (rozpočet, kreativa, cílení,
nabídka, web, měření, spuštění a vypnutí kampaně), popis, co od toho čekáme, skupina
nebo kampaň. Zásahy jsou v grafu jako značky (písmeno nad dnem, termíny jako ◆),
v reportu v části „Co jsme změnili“ a u agenta (`zasahy`, `zasahy_v_obdobi`). Agent má
v pravidlech výkladu, že souvislost změny čísel se zásahem je hypotéza.

## Obchodní výsledky

- **Tržby e-shopu** (detail, cíl nebo skupina e-shop): objednávky a tržby z administrace
  e-shopu za období, zapsané ručně (`ad_business_results`), dokud nemáme napojení na
  Shoptet a Upgates. Vedle nich útrata, **PNO** (útrata / tržby), tržby na korunu reklamy
  a kolik si připsaly reklamy (a jaký je to podíl tržeb). Konverze z Mety a Googlu se
  nesčítají jako tržby.
- **Kvalita leadů a obchody** (cíl nebo skupina leady či nábor): z `lead_feedback` (leady
  u klienta, relevantní, obchody, hodnota) a útraty za stejné období: cena za relevantní
  lead a cena za obchod.
- U klienta se skupinami se útrata bere ze skupiny s odpovídajícím cílem (`BusinessResults::scopeFor`).

## Reklamy a kreativy

Ranní stažení jedním dotazem na účet uloží součty 50 reklam s největší útratou za
posledních 30 dní (`ad_top_ads`, `HasAdInsights`). Detail klienta je ukazuje seřazené
podle konverzí, se štítkem „nejlevnější konverze“ (aspoň 2 konverze) a „utrácí bez
konverze“. U skupiny jen reklamy jejích kampaní. Kreativní testy (hypotéza, výsledek,
poučení) se dál zapisují u klienta.

## Návrh úprav od Clauda

Detail klienta → Další → **Návrh úprav od Clauda**. Claude dostane čísla za
zvolené období po kampaních a živá upozornění a napíše nejvýš šest doporučení
pro Pavla. Placené volání (jednotky korun), jen se zapnutým `ANTHROPIC_ENABLED`.
Poslední návrh zůstane na detailu klienta.

## Co je dál

- Klientský portál s přihlášením (zatím stačí sdílený odkaz na report).
- Sklik (Seznam) pro české e-shopy: nová třída implementující
  `App\Support\Ads\Platforms\AdsPlatform`, případ v `AdPlatform` a řádek v `Platforms::for()`.
  Bez API tokenu a společného vyzkoušení zatím ne.
- Objednávky a tržby přímo z API Shoptetu a Upgates místo ručního zápisu (`ad_business_results`):
  každý e-shop potřebuje vlastní přístup.
- Google Ads je napsaný a čeká na přístup (výš). Konverze z Mety a Googlu se v součtu
  účtu sčítají jako „připsané reklamám“, ne jako objednávky; skutečné tržby jsou zvlášť.
