# Interní CRM

Obchodní evidence pro Toma a Pavla: koho oslovujeme, co jsme mu poslali, kdy se ozvat
znovu a jak to dopadlo. Žije v panelu **`/nastroje`** vedle checklistů, s webem Taveo
nemá nic společného a do vyhledávačů se nedostane.

## Kudy do toho

`https://taveo.cz/nastroje` → přihlášení stejným účtem jako do administrace.
Po přihlášení se otevře **Dnes**, tedy seznam toho, co dneska čeká.

Účty se zakládají na serveru, registrace v aplikaci není:

```bash
php artisan crm:user Tom tom@taveo.cz            # heslo se vygeneruje a vypíše
php artisan crm:user Pavel pavel@taveo.cz --password=tajne
```

Hromadně jde totéž z `.env` a seederu:

```dotenv
CRM_USERS="Tom:tom@taveo.cz:heslo,Pavel:pavel@taveo.cz:heslo"
```

```bash
php artisan db:seed --class=CrmSeeder
```

Seeder je opakovaně spustitelný. Existující účet nechá být včetně hesla, aby
nasazení nepřepsalo heslo změněné v aplikaci. Ukázková data zakládá jen v prostředí
`local` a `development`.

**Šablony zpráv seeder nezakládá, přiváží je datová migrace.** Seeder se pouští při
zakládání instance, takže na běžící web by se šablony přidané později nikdy nedostaly.
Nasazení spouští `migrate`, proto jedou tudy, stejně jako obsah webu (viz
[CONTENT-MODEL.md](CONTENT-MODEL.md)).

Přístup má jen účet s rolí **Správce** (`App\Enums\UserRole::Admin`), viz
`User::canAccessPanel()`. `crm:user` i `CrmSeeder` ji nastavují samy.

## První nasazení

Nasazení spouští `migrate`, ale **žádné seedery**. Po deployi na čerstvý server je
tedy potřeba doplnit dvě věci ručně, zbytek přijede sám:

| Co | Jak | Kdy |
|---|---|---|
| Tabulky a šablony zpráv | samo přes `migrate` | při každém nasazení |
| Účty Tom a Pavel | `php artisan crm:user …` přes SSH | jednou |
| Prospekty a poptávky | dva importy v prohlížeči, viz níž | při každé nové rešerši |

Data se mezi prostředími nikdy nepřenášejí. Lokální databáze je pískoviště,
ostrá data bydlí jen na produkci.

## Obrazovky

| Stránka | K čemu je |
|---|---|
| **Dnes** | Po termínu, dnes, zbytek týdne, fronta k oslovení (nahoře nejvyšší skóre), nové poptávky, firmy bez pohybu. U každého řádku „Hotovo" a odklad o 3 nebo 7 dní |
| **Přehled** | Čísla za týden, oslovené firmy a jejich reakce, graf za 8 týdnů. Jen informativní, bez cílů |
| **Firmy** | Seznam s hledáním a filtry, karta firmy s kontakty, obchody a časovou osou |
| **Pipeline** | Kanban obchodů, přetahování karet mezi fázemi |
| **Obchody** | Tabulkový pohled na tytéž obchody, součet hodnoty, export |
| **Poptávky** | Co přiteklo z portálů, rychlé „Reagováno" a „Založit firmu" |
| **Import firem** | Nahrání tabulky prospektů z rešerše |
| **Import poptávek** | Nahrání listu s otevřenými poptávkami z portálů |
| **Šablony zpráv** | Texty s dosazovanými údaji firmy |
| **Nastavení CRM** | Nabídka odkladů, příjemci ranního souhrnu |

### Karta firmy

Všechno na jedné obrazovce. Aktivita se zapisuje tlačítkem **Zalogovat aktivitu**
nebo klávesou **`n`**. Ve formuláři jsou předvyplněné všechny hodnoty kromě předmětu,
takže záznam jsou tři kliknutí.

**Použít šablonu** vyskládá text s dosazenými údaji firmy, nechá ho zkopírovat do
schránky (klepnutím na předmět nebo text) a jedním potvrzením ho zapíše jako aktivitu.

### Jak se počítá „další krok"

`companies.next_action_at` se nikdy nevyplňuje ručně, odvozuje se z follow-upů
na aktivitách (`Company::recalculateNextAction()`):

- platí **nejbližší follow-up, který ještě nikdo nevyřídil**,
- vyřídí ho až **další kontakt** s firmou (e-mail, hovor, schůzka, LinkedIn, reakce
  na poptávku), ne pouhý běh času,
- poznámka ani úkol termín neruší. Dopsat si k firmě zjištění není totéž co ozvat se.

Díky tomu propásnutý follow-up zůstane viset v bloku „Po termínu", dokud se s firmou
opravdu něco neudělá. Odklad (`+3`, `+7`) posouvá původní follow-up, nezakládá další.

## Posouzení firem a audit jako prodejní argument

Rešerše plnila CRM ručně a bez filtru, takže se do fronty dostali elektrikáři
vedle zavedených e-shopů. Každá firma se proto **proklepne**: změří se veřejná
část webu, z toho vzniknou nálezy a skóre 0–100 a z nálezů jde jedním
tlačítkem udělat audit s checklistem pro klienta.

### Proklepnutí webu

Karta firmy → **Audit → Proklepnout web**, v seznamu firem hromadná akce,
nebo příkaz:

```bash
php artisan crm:scout 141 melichar.cz      # konkrétní firmy (ID nebo doména)
php artisan crm:scout --new --park         # celá fronta, nevhodné odložit
php artisan crm:scout --unscored           # jen dosud neproklepnuté
php artisan crm:scout --older-than=60      # přeměřit starší než 60 dní
```

Co se měří (`App\Support\Crm\Scout\WebScout`): odpověď serveru, HTTPS
a přesměrování, platforma, titulek, popisek, H1, viewport, kanonická adresa,
strukturovaná data, rok v patičce, měřicí a reklamní kódy (GA4, GTM, Meta pixel,
Google Ads, Sklik, Heureka, Zboží), robots.txt, sitemapa (i zabalená `.gz`),
přístup robotů GPTBot, ClaudeBot a PerplexityBot a Google PageSpeed pro mobil.
Z webu se zároveň vezme e-mail a telefon, když firma kontakt nemá, a platforma
a bolest, když chybí. Co někdo vyplnil ručně, zůstane.

**Skóre** (`FitScorer`) vychází z kapitol 4.1 a 4.3 v
[BRAND-STRATEGY.md](BRAND-STRATEGY.md):

| Kritérium | Body |
|---|---|
| e-shop / web služeb | +25 / +5 |
| platforma, na které umíme dodat (Shoptet, Upgates, Shopify, WooCommerce, WordPress, PrestaShop) | +15 |
| stavebnice (Webnode, Wix, Eshop-rychle…) | −10 |
| Meta pixel | +10 |
| Google Ads nebo Sklik | +10 |
| GA4 nebo GTM | +5 |
| Heureka nebo Zboží.cz | +5 |
| 100+ adres v sitemapě / 30+ / pod 10 u webu služeb | +10 / +5 / −10 |
| patička letos či loni / 5+ let stará | +5 / −5 |
| 2+ závažné nálezy / 1 | +10 / +5 |

Verdikt: 60+ **Silný kandidát**, 40–59 **Zvážit**, pod 40 **Nehodí se**,
nedostupný web **Web nejde načíst**.

**Hledáme jen e-shopy** (rozhodnutí z 27. 9. 2026). Firma ze segmentu jiného
než E-shop, Bývalý klient nebo Jiné (tedy lokální služby, zdraví, SVJ,
konference, agentury) a web, který podle měření e-shop není, dostane
**Nehodí se** bez ohledu na skóre. Skóre zůstává vidět, kdyby se rozhodnutí
změnilo. Starší verdikt **Partner** se už nepřiřazuje a odkládá se stejně.

**Claude** (s `ANTHROPIC_API_KEY`) posuzuje jen e-shopy. Přečte si měření a text úvodní stránky,
napíše, co firma prodává, a posune skóre nejvýš o ±20 bodů. Postřeh k oslovení
uloží jako bolest. Bez klíče všechno běží jen z měření.

**Automatické odkládání** (`--park`) odloží jen firmu ze zdroje *Rešerše*
ve stavu *Nová* s verdiktem *Nehodí se* nebo *Web nejde načíst* a zapíše
k ní poznámku s důvody. Poptávky, doporučení a rozjednané firmy nechává být.
Vrátit jde změnou stavu.

### Audit z karty firmy

**Audit → Vytvořit audit** (`App\Support\Crm\AuditFromCompany`):

1. firma dostane klienta v nástroji checklistů (`clients.crm_company_id`),
2. vznikne audit: dlaždice s čísly, shrnutí, stav podle oblastí, nejzávažnější
   nález, pak řádek `::: zámek` a pod ním pořadí úkolů, všechny nálezy
   a kapitola *Co zvenku nevidíme*,
3. vznikne checklist úkolů: kategorie podle oblasti, priorita podle závažnosti.

**Podrobný audit od Clauda.** S `ANTHROPIC_API_KEY` se po vytvoření konceptu
na pozadí spustí `App\Jobs\WriteDeepAudit`. Claude si nástrojem web_fetch sám
projde až 12 stránek e-shopu (úvod, kategorie, produkty, košík, kontakt,
podmínky), spojí to s měřením a napíše audit ve stylu Světa Cejlonu včetně
dlaždic a úkolů do checklistu (`App\Support\Crm\Ai\DeepAuditPrompt`). Trvá
3 až 6 minut, běží po odeslání odpovědi (bez fronty). Stav, počet stránek
a přibližnou cenu ukazuje hlavička auditu. Na už existujícím auditu to jde
spustit znovu tlačítkem **Projít web Claudem**. Když se přepis nepovede,
koncept z měření zůstane a v hlavičce je důvod.

**Audit na předplatném (doporučeno pro víc klientů).** API se platí za tokeny,
podrobný audit vyjde zhruba na 1 až 2 dolary. Totéž jde udělat v Claude Code,
které jede na předplatném: skill **`/audit-eshopu <doména>`** web změří
(`crm:measure`), projde ho v prohlížeči i na mobilu, napíše audit ve stejném
formátu a nahraje ho přes `POST /nastroje/api/audits/import` do ostrého CRM.
Potřebuje v lokálním `.env` `TAVEO_PROD_CRM_TOKEN` (hodnota `CRM_IMPORT_TOKEN`
ze serveru). Audit vznikne stejně jako z tlačítka a firma se v CRM založí,
když tam není.

Audit je **neveřejný a v omezeném režimu**. Než odkaz odejde, Tom nebo Pavel
ho přečte, upraví a zapne *Zpřístupnit přes odkaz*. Každé číslo v textu
pochází z měření. Claude smí napsat jen úvodní odstavec.

**Omezený režim** (`audits.is_teaser`): klient vidí text nad `::: zámek`,
z kapitol pod ním jen nadpisy se zámkem a pod nimi cihlový pruh s výzvou
k hovoru a formulářem poptávky. Checklist se neukazuje. Po vypnutí omezeného
režimu se zpřístupní celý text i checklisty klienta.

**Otevření auditu** se počítá (`view_count`, `first_viewed_at`,
`last_viewed_at`). První otevření, a další po 12 hodinách ticha, se zapíše
k firmě jako poznámka *Otevřeli audit* s follow-upem na příští pracovní den
v 9:00. Nepočítá se přihlášený správce ani náhledy odkazů a roboti.

### Hledání nových firem

```bash
php artisan crm:discover                         # výchozí zadání: e-shopy s reklamou
php artisan crm:discover --brief="…" --count=20
php artisan crm:discover --dry-run               # jen vypíše návrhy
```

Claude přes webové vyhledávání navrhne firmy, každá se hned proklepne.
Nevhodné se založí rovnou odložené, ať je další hledání nenavrhne znovu.

### Plánovač

| Kdy | Příkaz |
|---|---|
| denně 5:30 | `crm:scout --unscored --park --limit=60` — posoudí, co přibylo importem nebo přes API |
| pondělí 5:00 | `crm:discover` — bez `ANTHROPIC_API_KEY` nic nedělá |

### Proměnné prostředí

Po každé úpravě `.env` na serveru **Nastavení → Údržba → Obnovit cache**,
nasazení si konfiguraci ukládá. Tamtéž **Otestovat napojení** ověří oba klíče
a vypíše chybu tak, jak ji služba vrátila.

Placené volání Clauda z CRM je ve výchozím stavu **vypnuté**, i když je klíč
vyplněný. Bez `ANTHROPIC_ENABLED=true` se nezobrazí „Projít web Claudem“,
vytvoření auditu nespustí podrobný audit, proklepnutí jede jen z měření
a `crm:discover` nic nedělá. Audity se píšou v Claude Code na předplatném
(`/audit-eshopu`).

```dotenv
ANTHROPIC_ENABLED=false     # true = CRM smí volat placené Claude API
ANTHROPIC_API_KEY=          # úsudek, shrnutí auditu, hledání firem
ANTHROPIC_MODEL=claude-opus-5
ANTHROPIC_WORKSPACE_ID=     # jen u klíče mimo workspace (wrkspc_…)
PAGESPEED_API_KEY=          # bez klíče Google PageSpeed narazí na sdílený limit
PAGESPEED_ENABLED=true
```

## Import firem z CSV

**CRM → Import firem.** Soubor v UTF-8, oddělovač čárka nebo středník (rozpozná se sám,
BOM z Excelu taky). Očekávaná hlavička:

```
segment,firma,mesto,obor,web,platforma,bolest,balicek,reference,kontakt,priorita
```

Sloupce se před importem namapují, předvolba na tuhle hlavičku sedí sama. Následuje
náhled prvních pěti řádků a po importu souhrn.

- **`firma`** je jediný povinný sloupec. Řádek bez názvu se přeskočí.
- **`segment`** se překládá z češtiny: `Lokální firma`, `Zubní / zdraví`, `SVJ / správa`,
  `Konference`, `E-shop`, `Agentura`, `Bývalý klient`. Neznámý text spadne do `Jiné`.
- **`kontakt`** je volný text, například `info@firma.cz, +420 777 123 456 (Jan Novák)`.
  Vznikne z něj hlavní kontakt: první e-mail, první telefon a jméno **jen ze závorky**.
  Zbytek jde do poznámky, u kontaktu bez jména s předsazeným `(kontakt z rešerše)`.
- **Duplicity** se poznají podle domény webu, normalizované bez protokolu a bez `www`.
  Hlídají se proti databázi i uvnitř souboru. Přeskočené firmy souhrn vypíše jmenovitě.
- **Zástupné znaky** `-`, `–`, `?` a `n/a` se berou jako prázdná hodnota. Rešerše se
  píše ručně a neznámá platforma v ní bývá otazník; uložit ho by znamenalo filtr
  „Platforma: ?" v seznamu firem.
- Když ve sloupci `kontakt` není jméno, e-mail ani telefon (typicky „nešlo ověřit"
  nebo „přes formulář"), **kontaktní karta se nezaloží** a text se uloží k firmě jako
  poznámka. Informace se neztratí a v kontaktech nezůstane prázdný řádek.

## Import poptávek z CSV

**CRM → Import poptávek.** Druhý list téže tabulky rešerše. Hlavička:

```
Priorita,Zdroj,URL,Datum,Co chtějí,Odhad ceny,Stav,Datum reakce,Poznámka
```

- **`URL`** je jediný povinný sloupec a zároveň identita poptávky. Podle něj se
  pozná, jestli se řádek zakládá, nebo aktualizuje, takže tentýž soubor jde nahrát
  opakovaně a duplicity nevzniknou.
- **`Co chtějí`** se dělí na název a shrnutí v místě první pomlčky obklopené
  mezerami. Z „4horse.cz – přebíraný e-shop" bude název „4horse.cz". Rozsah v ceně
  („25–50k") zůstane celý, protože tam pomlčka mezery nemá.
- **`Zdroj`** rozumí názvům „Shoptet Partneři", „Webtrh", „Na volné noze",
  „Upgates". Neznámý portál spadne do „Jiné".
- **`Stav`, `Datum reakce` a `Poznámka`** se převezmou **jen u nově zakládané
  poptávky**. U té, kterou už vedeme, zůstane náš stav beze změny, i kdyby v tabulce
  bylo něco jiného. Tabulka je vstupní branou, ne zdrojem pravdy o naší práci.

Kdo dává přednost strojové cestě, může místo toho použít endpoint níž. Dělá totéž.

## Strojové rozhraní

Obojí ověřuje token z `.env`. **Bez nastaveného tokenu endpointy vracejí 404**, aby
zapomenutý řádek v `.env` neudělal z dat veřejná data.

```dotenv
CRM_IMPORT_TOKEN=nahodny-dlouhy-retezec
```

Token se posílá hlavičkou `X-Crm-Token`, jako Bearer token, nebo v parametru `?token=`.

### Import poptávek

`POST /nastroje/api/demands/import` — sem tlačí ranní automatizace výpis z portálů.

```bash
curl -X POST https://taveo.cz/nastroje/api/demands/import \
  -H "Content-Type: application/json" \
  -H "X-Crm-Token: $CRM_IMPORT_TOKEN" \
  -d '{
    "demands": [
      {
        "source": "shoptet_partners",
        "url": "https://partners.shoptet.cz/poptavky/2481",
        "title": "Migrace e-shopu s 1 200 produkty na Shoptet",
        "summary": "Stávající řešení na míru, napojení na Pohodu.",
        "posted_at": "2026-09-01",
        "budget_estimate": "80 000 až 120 000 Kč",
        "priority": "A"
      }
    ]
  }'
```

Odpoví `{"created":1,"updated":0,"skipped":0}`.

- Identitou poptávky je **`url`**, podle ní se rozhoduje mezi založením a aktualizací.
  Celý dnešní výpis jde poslat opakovaně, duplicity nevzniknou.
- Řádek bez `url` se zahodí a započítá do `skipped`.
- Neznámý `source` spadne do `Jiné`, neznámá `priority` do `B`, nečitelné `posted_at`
  do prázdné hodnoty. Import kvůli tomu nespadne.
- **Náš stav poptávky import nikdy nepřepisuje** (`status`, `replied_at`, `company_id`,
  `notes`). Ten patří nám, ne portálu.
- Nejvýš 500 poptávek na požadavek, limit 60 požadavků za minutu.

### Firmy k proklepnutí

`POST /nastroje/api/companies/import` — pro automatizaci, která hledá firmy
jinde (Claude Code, agent). Bez měření se firmy jen založí jako nové
a posoudí je ranní `crm:scout`.

```bash
curl -X POST https://taveo.cz/nastroje/api/companies/import \
  -H "Content-Type: application/json" \
  -H "X-Crm-Token: $CRM_IMPORT_TOKEN" \
  -d '{"companies": [{"website": "eshop.cz", "name": "E-shop", "city": "Hradec Králové", "segment": "eshop", "note": "Proč sedí"}]}'
```

K firmě jde poslat i `measurements` (pole `measurements` z `crm:measure`)
a `assessment` (`summary`, `adjustment` −20 až 20, `note`, `hook`). Pak se
skóre spočítá hned, bez dotazu na Claude API, a nevhodná firma se odloží.
Když v měření chybí PageSpeed, server ho doměří po odeslání odpovědi
a skóre přepočítá.

Odpoví `{"created":1,"restored":0,"updated":0,"skipped":0,"skipped_websites":[],"pagespeed_pending":0}`.
Duplicity se poznají podle domény. Známá firma se přeskočí, s měřením se jí
posouzení přepíše. Smazaná firma se obnoví jako nová. Nejvýš 200 firem na požadavek.

### Skóre firmy

`GET /nastroje/api/companies/scout?website=eshop.cz` vrátí skóre, verdikt,
stav a celé `scout_data` (měření včetně PageSpeed, nálezy, důvody skóre).
Skill `/audit-eshopu` si odsud bere PageSpeed.

### Export pipeline

`GET /nastroje/api/export/pipeline` — firmy, obchody a aktivity za posledních 30 dní.

```bash
curl "https://taveo.cz/nastroje/api/export/pipeline?token=$CRM_IMPORT_TOKEN"
```

## Ranní souhrn e-mailem

Follow-upy po termínu, dnešní follow-upy, nové poptávky a firmy bez pohybu.

```bash
php artisan crm:daily-digest            # rozešle
php artisan crm:daily-digest --dry-run  # jen vypíše, komu by šel
```

Plánovač už příkaz zná (`routes/console.php`): **všední dny v 7:00, Europe/Prague**.
Na serveru stačí jeden cron pro celý Laravel:

```cron
* * * * * cd /cesta/k/webu && php artisan schedule:run >> /dev/null 2>&1
```

Příjemce řídí **Nastavení CRM → Ranní souhrn**. Prázdný seznam znamená všechny účty.
Nefunkční mailer příkaz neshodí, chybu jen zapíše do logu.

## Export do CSV

Tlačítko **Export CSV** je na seznamu firem, na obchodech a v přehledu. Exportuje se to,
co je zrovna vyfiltrované. Soubor má středník a BOM, aby ho český Excel otevřel správně.

Hlavička u firem je stejná jako u importu, doplněná o `stav`, `vede`, `dalsi_krok`
a `posledni_aktivita`.

## Číselníky

Všechny žijí v `app/Enums/Crm/` a ukládají se jako řetězec, ne jako databázový enum.
Přidání hodnoty je tedy změna v PHP, ne migrace tabulky.

| Enum | Hodnoty |
|---|---|
| `Priority` | `A`, `B`, `C` |
| `CompanySegment` | `local`, `dental_health`, `svj`, `conference`, `eshop`, `agency`, `former_client`, `other` |
| `CompanyStatus` | `new`, `contacted`, `follow_up`, `replied`, `call`, `proposal`, `won`, `lost`, `parked` |
| `FitVerdict` | `strong`, `maybe`, `poor`, `partner`, `unreachable` |
| `CompanySource` | `research`, `shoptet_demands`, `webtrh`, `navolnenoze`, `upgates`, `referral`, `inbound_form`, `linkedin`, `other` |
| `DealPackage` | `migration_shoptet`, `integration_pohoda_carrier`, `measurement_audit`, `new_website`, `eshop_redesign`, `retainer`, `subcontracting`, `other` |
| `DealStage` | `lead`, `contacted`, `replied`, `call`, `proposal_sent`, `negotiation`, `won`, `lost` |
| `ActivityType` | `email`, `call`, `meeting`, `linkedin`, `demand_reply`, `note`, `task` |
| `ActivityOutcome` | `no_answer`, `positive`, `negative`, `neutral` |
| `DemandSource` | `shoptet_partners`, `webtrh`, `navolnenoze`, `upgates`, `other` |
| `DemandStatus` | `new`, `replied`, `call`, `proposal`, `won`, `lost`, `closed_elsewhere`, `ignored` |
| `TemplateChannel` | `email`, `linkedin`, `demand_reply`, `call_script` |

Výchozí pravděpodobnost podle fáze obchodu (`DealStage::defaultProbability()`): lead 5,
contacted 10, replied 25, call 40, proposal_sent 50, negotiation 70, won 100, lost 0.
Předvyplní se při změně fáze, ale ručně zadaná hodnota má vždycky přednost.

## Šablony zpráv

Text s dosazovanými údaji firmy. Zástupné texty:

| Zástupný text | Dosadí se |
|---|---|
| `{{firma}}` | název firmy |
| `{{jmeno}}` | jméno hlavního kontaktu |
| `{{bolest}}` | pozorovaná bolest z karty firmy |
| `{{reference}}` | reference, kterou argumentujeme |
| `{{web}}` | doména firmy |
| `{{mesto}}` | město |

Prázdné hodnoty se z textu odstraní i s okolní interpunkcí, takže z nevyplněné bolesti
nezůstane osiřelá čárka ani dvojitá tečka.

Šablon je šest: teplý kontakt, studený e-mail s postřehem, odpověď na poptávku,
follow-up, nabídka subdodávky agentuře a osnova telefonu. Přiváží je migrace
`2026_09_02_110000_seed_crm_message_templates`, která zakládá podle názvu — co si
v administraci upravíš, ti příští nasazení nepřepíše. Novou šablonu přidej v aplikaci,
nebo pro všechny instance další migrací.

## Kde co v kódu je

```
app/
├─ Console/Commands/           CrmUser, CrmDailyDigest, CrmScout, CrmDiscover
├─ Enums/Crm/                  číselníky (viz tabulka výš)
├─ Filament/Tools/
│  ├─ Actions/                 LogActivityAction, UseTemplateAction,
│  │                          ScoutCompanyAction, CreateAuditAction
│  ├─ Pages/                   Today, Overview, Pipeline, ImportCompanies,
│  │                          ImportDemands, ManageCrm
│  └─ Resources/               Companies, Deals, Demands, MessageTemplates, Tags
├─ Http/Controllers/Crm/       DemandImportController, CandidateImportController,
│                              PipelineExportController
├─ Http/Middleware/            VerifyCrmToken
├─ Mail/CrmDailyDigest
├─ Models/Crm/                 Company, Contact, Deal, Activity, Demand,
│                              MessageTemplate, Tag
├─ Observers/Crm/              ActivityObserver, DealObserver
├─ Settings/CrmSettings        odklady, příjemci souhrnu
└─ Support/Crm/
   ├─ Domain                   normalizace webu na doménu
   ├─ CompanyCsvImporter       import prospektů z rešerše
   ├─ DemandCsvImporter        import poptávek z rešerše
   ├─ DemandImporter           upsert poptávek podle adresy
   ├─ TemplateRenderer         dosazení do šablon
   ├─ WeeklyKpi                týdenní čísla
   ├─ OutreachLog              oslovené firmy a jejich reakce
   ├─ CsvExport                stahování CSV
   ├─ AuditFromCompany         audit a checklist z proklepnuté firmy
   ├─ Scout/                   WebScout (měření), Findings (nálezy),
   │                          FitScorer (skóre), ProspectScout (vše dohromady)
   └─ Ai/                      ProspectAi, ClaudeProspectAi, NullProspectAi

database/
├─ migrations/
│  ├─ 2026_09_02_100000_create_crm_tables.php
│  └─ 2026_09_02_110000_seed_crm_message_templates.php
├─ settings/2026_09_02_000001_create_crm_settings.php
├─ factories/Crm/
└─ seeders/CrmSeeder
```

Panel nástrojů má vlastní téma (`resources/css/filament/tools/theme.css`). Filament
dodává jen ty utility, které používá sám, a stránky CRM stojí na vlastním rozvržení.
Po zásahu do jejich šablon je proto potřeba `npm run build`.

Testy: `tests/Feature/CrmTest.php`, `CrmImportTest.php`, `CrmDigestTest.php`, `CrmScoutTest.php`.
