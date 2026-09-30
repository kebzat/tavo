# Reklamy klientů

Interní nástroj pro správu menších klientů s rozpočtem v řádu tisíců měsíčně.
Každé ráno stáhne čísla z reklamních účtů, uloží je, zkontroluje, co se pokazilo,
a připraví podklad pro týdenní report. Pavel pak nemusí každý účet otevírat ručně
a na jednoho klienta stačí pár minut týdně.

Panel nástrojů → skupina **Reklamy**:

| Obrazovka | Adresa | Co tam je |
|---|---|---|
| Přehled klientů | `/nastroje/reklamy` | karta na klienta: útrata, konverze, cena za konverzi, ROAS, CTR, sparklines, čerpání rozpočtu, upozornění |
| Detail klienta | `/nastroje/reklamy/klient/{id}` | dlaždice se srovnáním, graf po dnech, cesta k nákupu, kampaně, domluva s klientem, účty, reporty |
| Upozornění | `/nastroje/reklamy/upozorneni` | všechna živá upozornění, akce Řeším / Vyřešeno / Zamítnout |
| Reporty | `/nastroje/reklamy/reporty` | koncepty a odeslané reporty |
| Hodiny | `/nastroje/reklamy/hodiny` | zapsaný čas u klientů, filtr podle měsíce, hromadně „vyfakturováno“ |
| Fakturace | `/nastroje/reklamy/fakturace` | paušál + hodiny nad paušál po klientech za měsíc, kdo kolik odpracoval |
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
4. Na pozadí se stáhne historie za 90 dní (`ADS_BACKFILL_DAYS`).

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
| `ad_daily_stats` | součty na kampaň a den: útrata, zobrazení, dosah, prokliky, nákupy, hodnota, poptávky, košík, pokladna + celé `actions` z API v `raw` |
| `ad_client_settings` | cíl, rozpočet, cílové CPA a ROAS, paušál, hodiny, sazba, příjemci reportů, poslední návrh od Clauda |
| `ad_alerts` | upozornění (pravidlo, závažnost, doporučení, stav) |
| `ad_reports` | reporty se zmrazenými čísly (`snapshot`) |
| `ad_sync_runs` | log stahování |

Pravidla, na kterých to stojí:

- **Ukládají se jen součty.** CTR, CPC, CPM, cena za konverzi a ROAS se počítají
  až ze součtů za období (`App\Support\Ads\Metrics`). Průměr denních poměrů by lhal.
- **Nákupy = `omni_purchase`**, jako sloupec „Nákupy“ ve Správci reklam. Pořadí
  typů akcí je v `MetaAds::ACTIONS`. Atribuce je ta, kterou má nastavený účet.
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
- Fakturace ukáže za měsíc paušál, odpracované hodiny, hodiny navíc a částku.
  Tlačítko Vyfakturováno označí záznamy měsíce. Nefakturovatelný čas (oprava naší
  chyby, interní porada) se do částky nepočítá, jen do kapacity.
- Pod tím kdo kolik odpracoval (kapacita Pavla a Toma, BRAND-STRATEGY §16.1).

## Co ukazovat

Detail klienta → Cíle a paušál → Co ukazovat: které dlaždice s čísly a které sekce
(graf, cesta ke konverzi, kampaně, rozpad podle účtů, GA4) se ukážou. Platí pro detail
i pro reporty, které klient dostane. Bez výběru se ukáže všechno.

## Návrh úprav od Clauda

Detail klienta → Další → **Návrh úprav od Clauda**. Claude dostane čísla za
zvolené období po kampaních a živá upozornění a napíše nejvýš šest doporučení
pro Pavla. Placené volání (jednotky korun), jen se zapnutým `ANTHROPIC_ENABLED`.
Poslední návrh zůstane na detailu klienta.

## Co je dál

- Klientský portál s přihlášením (zatím stačí sdílený odkaz na report).
- Sklik (Seznam) pro české e-shopy: nová třída implementující
  `App\Support\Ads\Platforms\AdsPlatform`, případ v `AdPlatform` a řádek v `Platforms::for()`.
