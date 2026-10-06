# Reklamy klientů

Interní nástroj pro správu menších klientů s rozpočtem v řádu tisíců měsíčně.
Každé ráno stáhne čísla z reklamních účtů, uloží je, zkontroluje, co se pokazilo,
a připraví podklad pro týdenní report. Pavel pak nemusí každý účet otevírat ručně
a na jednoho klienta stačí pár minut týdně.

Panel nástrojů → skupina **Reklamy**:

| Obrazovka | Adresa | Co tam je |
|---|---|---|
| Přehled klientů | `/nastroje/reklamy` | karta na klienta: šest čísel (vybírají se třemi tečkami), sparklines, čerpání rozpočtu, upozornění |
| Detail klienta | `/nastroje/reklamy/klient/{id}` | dlaždice se srovnáním, graf po dnech, cesta k nákupu, kampaně, domluva s klientem, účty, reporty |
| Upozornění | `/nastroje/reklamy/upozorneni` | všechna živá upozornění, akce Řeším / Vyřešeno / Zamítnout |
| Reporty | `/nastroje/reklamy/reporty` | koncepty a odeslané reporty |
| Hodiny | `/nastroje/reklamy/hodiny` | zapsaný čas u klientů, filtr podle měsíce, hromadně „vyfakturováno“ |
| Fakturace (v CRM) | `/nastroje/fakturace` | paušál + hodiny nad paušál po klientech za měsíc, vyhrané jednorázové zakázky, co je vyfakturované, kdo kolik odpracoval |
| Nastavení reklam | `/nastroje/reklamy/nastaveni` | stav napojení, příjemci souhrnu, prahy upozornění, log synchronizace |

## Jak se napojí klient

Agenturní model: klient nám účet **nasdílí**, jeho přihlašovací údaje ani tokeny
nikde neukládáme.

1. Klient v Meta Business Suite → Nastavení firmy → Partneři přidá Business Manager
   Taveo a nasdílí mu reklamní účet (stačí oprávnění „Zobrazit výkon“).
2. V Business Manageru Taveo přiřadíme účet systémovému uživateli.
3. V nástrojích Reklamy → Přehled klientů → **Přidat klienta**. Účet se vybírá
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

Období se volí nahoře na přehledu i detailu: přednastavená (7 dní, 30 dní, tento
a minulý měsíc) nebo **Vlastní období** od–do. Volba čte jen z naší databáze, na Metu
nejde žádný dotaz. Když období začíná dřív, než máme čísla, stránka na to upozorní.

Další účet téhož klienta: detail klienta → Další → Propojit účet. Vypnout nebo
odpojit účet jde v Checklisty → Klienti → klient → Reklamní účty.

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

5. `php artisan config:cache` a v Nastavení reklam kliknout na **Otestovat spojení**.

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

## Ochrana před přetížením API

Čísla čteme oficiálním Marketing API a pouze pro čtení (`ads_read`): nic v účtech
neměníme a žádné AI ani MCP do účtů nesahá. Claude u „Návrhu úprav“ dostává jen
čísla z naší databáze. Aby Meta ani Google nikdy nevyhodnotily provoz jako
nadměrný:

| Pojistka | Jak funguje |
|---|---|
| Historie | Při propojení jednou 3 měsíce (1 dotaz), pak už nikdy. Delší historie je vypnutá. |
| Jednou denně | `ads:sync` běží jen v 6:00. Na účet Mety jdou tři dotazy: stav účtu, čísla za 7 dní a dosah za přednastavená období. U 20 klientů zhruba 60 dotazů denně. |
| Ruční načtení | Tlačítko **Načíst čísla znovu** na detailu klienta, s potvrzením a pauzou 30 minut. Stejné tři dotazy na účet. |
| Žádné opakování po omezení | Po odpovědi „moc dotazů“ se nic neopakuje. Znovu se zkouší jen výpadek spojení nebo chyba serveru, nejvýš 2×. |
| Samo se zastaví | Když Meta omezí dotazy nebo v hlavičkách hlásí vytížení nad 75 %, stahování z ní se pozastaví do zítřejšího rána (ruční tlačítko také). |
| Denní strop | Nejvýš 200 dotazů na platformu za den (`META_DAILY_CALL_LIMIT`). Běžný provoz je necelá třetina. Strop chytí chybu v kódu dřív než Meta. |
| Bez souběhu | Jeden účet se nikdy nestahuje dvakrát současně (zámek). |
| Strop stránek | Nejvýš 20 stránek výsledku na dotaz, víc znamená chybu. |
| Seznam účtů | Nabídka při propojování se drží 5 minut v paměti, i když skončí chybou. Test spojení jde jednou za 5 minut. |

Stav je v Nastavení reklam → Ochrana před přetížením: kolik dotazů dnes odešlo
a jestli je některá platforma pozastavená. Implementace:
`App\Support\Ads\Platforms\ApiGuard`.

Přístup je přes systémového uživatele Business Manageru Taveo, ne přes osobní
facebookový profil Pavla ani Toma.

## Plánované běhy

| Čas | Příkaz | Co dělá |
|---|---|---|
| denně 6:00 | `ads:sync` | přepíše posledních 7 dní všech zapnutých účtů (Meta konverze zpětně dopočítává) |
| denně 6:40 | `ads:alerts` | vyhodnotí pravidla, otevře nová upozornění, zavře ta, která přestala platit |
| všední dny 7:15 | `ads:digest` | e-mail „Reklamy: N upozornění“ se včerejší útratou. Když není co hlásit, neodejde |
| pondělí 7:30 | `ads:reports weekly` | koncepty týdenních reportů za minulý týden |
| 1. v měsíci 7:30 | `ads:reports monthly` | koncepty měsíčních reportů |

Produkce nemá frontu, všechno běží přes scheduler (`schedule:run` v cronu).

Ruční příkazy:

```bash
./bin/art ads:sync --account=123456789 --from=2026-01-01   # historie jednoho účtu
./bin/art ads:sync --days=30                                # přepsat měsíc všem
./bin/art ads:alerts --date=2026-09-30
./bin/art ads:digest --dry-run
./bin/art ads:demo                                          # jen lokálně: ukázkoví klienti s vymyšlenými čísly
```

## Data

| Tabulka | Obsah |
|---|---|
| `ad_accounts` | účet klienta (platforma, číslo účtu, měna, stav, poslední synchronizace a chyba) |
| `ad_campaigns` | kampaně |
| `ad_daily_stats` | součty na kampaň a den: útrata, zobrazení, dosah, kliknutí, prokliky, nákupy, hodnota, poptávky, košík, pokladna, zobrazení cílové stránky + celé `actions` z API v `raw` |
| `ad_period_reach` | přesný dosah, zobrazení, frekvence a unikátní prokliky účtu za přednastavená období, přepisuje se každé ráno |
| `ad_client_settings` | cíl, rozpočet, cílové CPA a ROAS, paušál, hodiny, sazba, příjemci reportů, poslední návrh od Clauda |
| `ad_alerts` | upozornění (pravidlo, závažnost, doporučení, stav) |
| `ad_reports` | reporty se zmrazenými čísly (`snapshot`) |
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
vrací titulek a konkrétní doporučení, co udělat.

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

Prahy se mění v Nastavení reklam. Cena za konverzi a ROAS se hodnotí až od
10 konverzí za týden, u malého účtu dělá jedna objednávka desítky procent.

Živé upozornění se páruje podle pravidla, klienta a účtu: další den se jen obnoví.
Když pravidlo přestane platit, zavře se samo jako vyřešené. Zamítnuté se týden neozve.

## Reporty

1. Koncept vznikne v pondělí ráno (u klientů se zapnutým týdenním reportem), nebo
   tlačítkem **Připravit report** na detailu klienta.
2. Pavel doplní komentář: co jsme za období udělali, co z čísel plyne, co chystáme.
3. **Náhled** ukáže report přesně tak, jak ho uvidí klient.
4. **Odeslat klientovi** zapne sdílení a pošle e-mail se třemi hlavními čísly
   a odkazem. Adresy jsou v Cíle a paušál, jinak kontaktní e-mail klienta.
   Odpověď klienta jde na kontaktní e-mail webu.

Report se nikdy neodešle sám. Otevření klientem se počítá a u klienta propojeného
s CRM se zapíše jako aktivita (stejně jako u auditu).

Report se dá vytisknout do PDF z prohlížeče, graf je SVG a tisk ho zachová.

## Hodiny nad paušál a fakturace

- Čas se zapisuje na detailu klienta (Zapsat čas) nebo v Reklamy → Hodiny. Délka
  jako `1:30`, `1,5` nebo `45m`.
- Paušál, hodiny v paušálu a sazba za práci navíc jsou u klienta v Cíle a paušál.
- Fakturace (CRM → Fakturace) ukáže za měsíc paušál, odpracované hodiny, hodiny
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
| `conversions` | Nákupy / Poptávky / Prokliky na web | všechny |
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
| `ctr`, `ctr_all` | CTR odkazu a CTR všech kliknutí | všechny |
| `cpm` | CPM | všechny |

## Co ukazovat

Detail klienta → Cíle a paušál → Co ukazovat: které dlaždice s čísly a které sekce
(graf, cesta ke konverzi, kampaně, rozpad podle účtů, GA4) se ukážou. Platí pro detail
i pro reporty, které klient dostane.

Bez výběru se ukáže výchozí sada podle cíle: u e-shopu útrata, nákupy, cena za nákup,
ROAS, hodnota nákupů, prokliky, CTR a CPM. Ostatní čísla (dosah, frekvence, košík,
zobrazení cílové stránky…) se zapínají zaškrtnutím. Výběr shodný s výchozí sadou se
neukládá, uložený výběr ze starších klíčů (`cost`, `value`, `clicks`) platí dál.
Zmrazené reporty se starším tvarem čísel se zobrazují beze změny.

## Přehled klientů: šest čísel

Každá karta v přehledu má šest čísel. Tři tečky u čísla nabídnou celý katalog;
výběr platí pro tu pozici u všech klientů a pro všechny uživatele nástrojů
(`AdsSettings::$overview_tiles`, výchozí útrata, konverze, cena za konverzi, ROAS,
CTR, CPM). Číslo, které u cíle klienta nedává smysl (ROAS u poptávek), ukáže pomlčku.

## Návrh úprav od Clauda

Detail klienta → Další → **Návrh úprav od Clauda**. Claude dostane čísla za
zvolené období po kampaních a živá upozornění a napíše nejvýš šest doporučení
pro Pavla. Placené volání (jednotky korun), jen se zapnutým `ANTHROPIC_ENABLED`.
Poslední návrh zůstane na detailu klienta.

## Co je dál

- Klientský portál s přihlášením (zatím stačí sdílený odkaz na report).
- Sklik (Seznam) pro české e-shopy: nová třída implementující
  `App\Support\Ads\Platforms\AdsPlatform`, případ v `AdPlatform` a řádek v `Platforms::for()`.
