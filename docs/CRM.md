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
| **Dnes** | Po termínu, dnes, zbytek týdne, fronta k oslovení, nové poptávky, firmy bez pohybu. U každého řádku „Hotovo" a odklad o 3 nebo 7 dní |
| **Přehled** | Týdenní čísla proti cílům, graf za 8 týdnů, rozpad podle zdroje a segmentu |
| **Firmy** | Seznam s hledáním a filtry, karta firmy s kontakty, obchody a časovou osou |
| **Pipeline** | Kanban obchodů, přetahování karet mezi fázemi |
| **Obchody** | Tabulkový pohled na tytéž obchody, součet hodnoty, export |
| **Poptávky** | Co přiteklo z portálů, rychlé „Reagováno" a „Založit firmu" |
| **Import firem** | Nahrání tabulky prospektů z rešerše |
| **Import poptávek** | Nahrání listu s otevřenými poptávkami z portálů |
| **Šablony zpráv** | Texty s dosazovanými údaji firmy |
| **Nastavení CRM** | Týdenní cíle, nabídka odkladů, příjemci ranního souhrnu |

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
├─ Console/Commands/           CrmUser, CrmDailyDigest
├─ Enums/Crm/                  číselníky (viz tabulka výš)
├─ Filament/Tools/
│  ├─ Actions/                 LogActivityAction, UseTemplateAction
│  ├─ Pages/                   Today, Overview, Pipeline, ImportCompanies,
│  │                          ImportDemands, ManageCrm
│  └─ Resources/               Companies, Deals, Demands, MessageTemplates, Tags
├─ Http/Controllers/Crm/       DemandImportController, PipelineExportController
├─ Http/Middleware/            VerifyCrmToken
├─ Mail/CrmDailyDigest
├─ Models/Crm/                 Company, Contact, Deal, Activity, Demand,
│                              MessageTemplate, Tag
├─ Observers/Crm/              ActivityObserver, DealObserver
├─ Settings/CrmSettings        týdenní cíle, odklady, příjemci souhrnu
└─ Support/Crm/
   ├─ Domain                   normalizace webu na doménu
   ├─ CompanyCsvImporter       import prospektů z rešerše
   ├─ DemandCsvImporter        import poptávek z rešerše
   ├─ DemandImporter           upsert poptávek podle adresy
   ├─ TemplateRenderer         dosazení do šablon
   ├─ WeeklyKpi                týdenní čísla
   ├─ ChannelBreakdown         výkon kanálů a segmentů
   └─ CsvExport                stahování CSV

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

Testy: `tests/Feature/CrmTest.php`, `CrmImportTest.php`, `CrmDigestTest.php`.
