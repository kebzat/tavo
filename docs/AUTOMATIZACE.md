# Automatizace klientů

Web TAVEO dělá pro e-shopy klientů práci, která se jinak dělá ručně. Zatím jedna:
**připomínky vyzvednutí zásilek** pro e-shopy na Upgates, které posílají přes Zaslat.cz
(první klient Svět Cejlonu, říjen 2026).

- Nástroje → Klienti → **Automatizace**: technické nastavení (správce).
- **Účet klienta** `/muj-ucet` (role Klient): přehled, přepínače, zásilky a co se poslalo.

## Jak to funguje

Upgates umí sám poslat e-mail nebo SMS, když se objednávce změní stav. Stav objednávky
umí změnit **pravidelný import objednávek** z CSV na adrese. Automatizace proto nic
neposílá sama: jen každou hodinu připraví CSV „objednávka → nový stav“ a Upgates zbytek
udělá sám. Ověřeno naostro 27. 10. 2026.

Každou hodinu v :40 (`automations:run`, `App\Support\Automations\ParcelPickup`):

1. Stáhne stavy objednávek z XML exportu Upgates (`UpgatesOrders`). Z exportu bere jen
   číslo objednávky, stav a čas změny. Když export nejde stáhnout, běh skončí chybou a nic
   se nemění.
2. Vezme ze Zaslatu všechny aktivní zásilky (`/shipments/list`, po 50) přes API klíč z trezoru.
   Číslo objednávky je u zásilky v `reference`.
3. Čekající zprávy: objednávka už má cílový stav = **potvrzeno**. Objednávka mezitím
   v jiném stavu (někdo ji vyřídil ručně) = **nahrazeno**, už se neposílá. Čeká déle než
   3 hodiny = **chyba** a upozornění.
4. Pro každou zásilku, jejíž objednávka je v exportu a má stav ze „source_statuses“:
   - vrácená (`tracking_information.returned`) → stav **Vráceno** (e-mail o nevyzvednuté zásilce),
   - na výdejním místě (`PARCELSHOP`) a dnes je poslední den nebo už po něm → **Poslední den k vyzvednutí** (SMS),
   - na výdejním místě, do konce zbývá nejvýš „kolik dní předem“ (výchozí 2) a objednávka je
     ve stavu **Odeslána** → **Čeká na vyzvednutí** (e-mail s připomínkou).

   Důležitost: vrácená > poslední den > připomínka. Každá zpráva u objednávky jen jednou
   (unikátní klíč automatizace + objednávka + druh). Důležitější zpráva nahradí méně důležitou,
   která ještě čeká, takže CSV má u objednávky vždy jen jeden řádek.
5. Uloží přehled zásilek na výdejních místech (číslo objednávky, dopravce, do kdy, zbývá dní,
   co se poslalo) a čas běhu. Bez jmen, adres, e-mailů a telefonů.

Upgates si CSV stáhne (import 1× za hodinu), změní stav a pošle e-mail/SMS přiřazený ke stavu.
Další běh změnu v exportu uvidí a zprávu potvrdí.

**Opakovaný import je neškodný** (ověřeno naostro 27. 10. 2026): když import nastaví
objednávce stav, který už má, Upgates nic nezmění a e-mail ani SMS znovu nepošle (v historii
objednávky není ani záznam). Čekající řádky proto můžou v CSV zůstat, dokud je export
nepotvrdí.

### Konec úložní doby

Zaslat ho neposílá (`delivery_date` je jen odhad doručení).

- **Zásilkovna:** veřejné sledování (`packages[0].carrier_tracking`, tracking.packeta.com).
  V HTML je payload Nuxtu s klíčem `storedUntil` (`PacketaTracking::parse`, test s ukázkou).
  Web chodí jen na domény Zásilkovny, výsledek si pamatuje 3 hodiny.
- **Ostatní (PPL, DPD…) nebo když sledování datum neřekne:** den první události `PARCELSHOP`
  + úložní doba dopravce z nastavení (výchozí 7 dní u všech).
- Dny se počítají v Europe/Prague. Poslední den = 0 dní.

### Export objednávek

V automatizaci může být víc adres exportu (každá na řádek). **První = kompletní export**
(u Světa Cejlonu ~4 300 objednávek, v Upgates 1× denně): stahuje se nejvýš jednou za 6 h
a drží se v `storage/app/private/automatizace/{id}/objednavky.json` (jen číslo, stav, čas
změny). **Další = změny za 24 h** (v Upgates každých 5 minut), stahují se při každém běhu.
Pro každé číslo vyhraje záznam s novějším `UPDATED_AT`, bez času pozdější adresa.

- Nejde stáhnout 24h export → běh skončí chybou.
- Nejde stáhnout kompletní, ale v cache je mladší než 30 h → jen varování, běh pokračuje.
- Jediná adresa = stahuje se při každém běhu.
- Názvy stavů se porovnávají po `trim()`, Upgates nechává mezery na konci („Odeslat znovu až se vrátí “).
- Parser čte `ORDER_NUMBER` (vlastní export) nebo `HEADER/CODE` (vzorový formát
  files.upgates.com/examples/orders.xml), `STATUS` a `UPDATED_AT` přímo v objednávce nebo
  v `HEADER` (ne `STATUS` souhlasů).

## Nastavení v Upgates

1. **Stavy objednávek** (názvy přesně tak, jak jsou v nastavení automatizace):
   `Odeslána` (výchozí stav odeslané objednávky), `Čeká na vyzvednutí` (e-mail připomínky),
   `Poslední den k vyzvednutí` (SMS), `Vráceno` (e-mail o nevyzvednuté zásilce). K cílovým
   stavům přiřadit e-mail nebo SMS, který se má poslat.
2. **Export objednávek XML** (pravidelný, na adrese): kompletní a „Posledních 24 hodin“.
   Adresy vložit do automatizace v nástrojích (ukládají se zašifrovaně).
3. **Import objednávek → Pravidelně (malý import)** z adresy CSV z nástrojů, 1× za hodinu:
   oddělovač středník, kódování Windows-1250, jen aktualizace existujících objednávek.
   Sloupce Zaplaceno, Sledovací číslo, Vyřešeno = nezpracovávat. Hlavička CSV je
   `[ORDER_NUMBER];[ORDER_STATUS]`.

## CSV pro Upgates

`GET /automatizace/{import_token}/upgates-import.csv` (40 znaků, bez přihlášení, throttle 60/min,
`noindex`). Hlavička + všechny čekající zprávy (`číslo;cílový stav`), Windows-1250, CRLF.
Při stažení se u řádků zapíše `exported_at` (pozná se, jestli Upgates import vůbec běží).
Vypnutá automatizace = jen hlavička. Neznámý token = 404. Kdo adresu zná, vidí jen čísla
objednávek a stavy. Novou adresu vygeneruje tlačítko **Nová adresa CSV** (pak ji vložit do Upgates).

## Upozornění a souhrn

- Chyba běhu nebo zpráva, kterou Upgates do 3 h nepotvrdil → e-mail správcům (role Správce)
  a účtům klienta (`App\Mail\AutomationProblem`). Stejný problém nejvýš 1× za 6 h (cache).
- Ranní souhrn v 7:00 (`automations:summary`) účtům klienta: kolik zpráv se včera potvrdilo
  podle druhu, chyby, kolik zásilek leží na výdejních místech. Jen u zapnutých automatizací.

## Účet klienta (`/muj-ucet`)

- Panel `client` (`ClientPanelProvider`), stejné téma jako nástroje, `noindex`.
- Role **Klient** (`UserRole::Client`) s povinným klientem (`users.client_id`), zakládá se
  v administraci → Uživatelé. Klient se nedostane do `/admin` ani `/nastroje`
  (`User::canAccessPanel`). Správce do `/muj-ucet` smí (náhled, výběr klienta `?klient=`).
- Klient vidí jen automatizace svého klienta a mění jen: zapnuto, připomínka ano/ne, kolik dní
  předem (1–6), SMS poslední den, e-mail k vrácené. Technické nastavení jen v nástrojích.
- **Zapomenuté heslo** jen pro účty s rolí Klient (`App\Filament\Client\Pages\Auth\RequestPasswordReset`),
  účty týmu si heslo touhle cestou obnovit nemůžou. Neznámý e-mail dostane stejnou odpověď.
- Dvoufázové ověření si klient může zapnout v profilu, povinné není.
- Účty klientů nedostávají souhrny CRM a reklam a nejsou ve výběrech lidí v nástrojích (`User::staff()`).

## Příkazy

```bash
php artisan automations:run                      # zapnuté automatizace (plánovač každou hodinu v :40)
php artisan automations:run --automation=1       # jen jedna
php artisan automations:run --automation=1 --dry-run   # co by se stalo, nic neuloží ani nepošle
php artisan automations:summary [--dry-run]      # ranní souhrn (plánovač v 7:00)
```

V nástrojích u automatizace je i **Vyzkoušet nanečisto** (stejné jako `--dry-run`).

## Soubory

| | |
|---|---|
| Data | `automations` (nastavení, zašifrovaná adresa exportu, token CSV, poslední běh, přehled), `automation_events` (zprávy) |
| Engine | `App\Support\Automations\ParcelPickup`, `UpgatesOrders`, `PacketaTracking`, `AutomationAlerts` |
| CSV | `App\Http\Controllers\AutomationCsvController` |
| Nástroje | `App\Filament\Tools\Resources\Automations` |
| Účet klienta | `App\Filament\Client\Pages\Automations`, `resources/views/filament/client/pages/automations.blade.php` |
| Testy | `tests/Feature/AutomationsTest.php`, `tests/Feature/ClientPanelTest.php` |

## Co ověřit při prvním ostrém běhu

- Stránka sledování Zásilkovny se pro server může chovat jinak než v prohlížeči (ochrana proti
  robotům). Když `storedUntil` nenajde, použije se výpočet (první den na výdejním místě + 7).
- Skutečné XML z exportu Svět Cejlonu (tvar `ORDER_NUMBER`) se v testech jen napodobuje.
  Vyzkoušet nanečisto v nástrojích ukáže, jestli export čte.
