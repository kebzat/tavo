# Automatizace klientů

Web TAVEO dělá pro e-shopy klientů práci, která se jinak dělá ručně. Zatím jedna:
**připomínky vyzvednutí zásilek** pro e-shopy na Upgates, které posílají přes Zaslat.cz
(první klient Svět Cejlonu, říjen 2026).

- Nástroje → Klienti → **Automatizace**: technické nastavení (správce).
- **Účet klienta** `/muj-ucet` (role Klient): přehled, přepínače, zásilky a co se poslalo.

## Jak to funguje

Upgates umí sám poslat e-mail nebo SMS, když se objednávce změní stav. Stav objednávky
umí změnit **import objednávek** z CSV. Automatizace proto nic neposílá sama: jen každou
hodinu připraví CSV „objednávka → nový stav“, nahraje ho do importu v Upgates a Upgates
zbytek udělá sám. Ověřeno naostro 27. 10. 2026.

Pravidelně z adresy Upgates objednávky importovat **neumí** (u objednávek je jen
„Jednorázově“ ze souboru). Web se proto přihlásí přístupem z trezoru (druh Upgates)
a nahraje soubor do existujícího importu stejně jako tlačítko „Zopakovat s novým souborem“:
`POST /setup/export-import/default/orders/?do=runAgainWithNewFile` s `file` a `import_id`,
odpověď `{"success": true}` (`App\Support\Automations\UpgatesImportUpload`). Import se
zpracuje ve frontě Upgates za 1–5 minut.

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
3. **Import objednávek** (Objednávky → Import, Upgates CSV, Jednorázově) jednou založit ručně se
   souborem s hlavičkou `[ORDER_NUMBER];[ORDER_STATUS]`, pak v jeho nastavení: oddělovač středník,
   jen aktualizace existujících, sloupce Zaplaceno, Sledovací číslo, Vyřešeno = nezpracovávat.
   Jeho číslo (#12 u Světa Cejlonu) dát do automatizace. Web do něj pak nahrává soubory sám.
4. **Přihlášení do Upgates v trezoru** (druh Upgates, adresa administrace, adresa přihlášení
   `…/login/`), ideálně vlastní účet jen pro automatizace. Vybrat ho v automatizaci.

## CSV pro Upgates

`GET /automatizace/{import_token}/upgates-import.csv` (40 znaků, bez přihlášení, throttle 60/min,
`noindex`). Hlavička + všechny čekající zprávy (`číslo;cílový stav`), Windows-1250, CRLF.
Při stažení se u řádků zapíše `exported_at` (pozná se, jestli Upgates import vůbec běží).
Vypnutá automatizace = jen hlavička. Neznámý token = 404. Kdo adresu zná, vidí jen čísla
objednávek a stavy. Slouží ke kontrole a k ručnímu importu, kdyby nahrávání selhávalo.
Novou adresu vygeneruje tlačítko **Nová adresa CSV**.

## Upozornění a souhrn

- Chyba běhu nebo zpráva, kterou Upgates do 3 h od nahrání nepotvrdil → e-mail správcům (role Správce)
  a účtům klienta (`App\Mail\AutomationProblem`). Stejný problém nejvýš 1× za 6 h (cache).
- Ranní souhrn v 7:00 (`automations:summary`) účtům klienta: kolik zpráv se včera potvrdilo
  podle druhu i s čísly objednávek, chyby, kolik zásilek leží na výdejních místech. Jen u zapnutých automatizací.
- **Vrácená zásilka ve stavu Vyřešeno** → stejné upozornění, u objednávky jen jednou. Vyřešeno v Upgates spouští
  fakturu a žádost o hodnocení. Viz níže „Hlídání vrácených zásilek“.

## Účet klienta (`/muj-ucet`)

- Panel `client` (`ClientPanelProvider`), stejné téma jako nástroje, `noindex`.
- Role **Klient** (`UserRole::Client`) s povinným klientem (`users.client_id`), zakládá se
  v administraci → Uživatelé. Klient se nedostane do `/admin` ani `/nastroje`
  (`User::canAccessPanel`). Správce do `/muj-ucet` smí (náhled, výběr klienta `?klient=`).
- Klient vidí jen automatizace svého klienta a mění jen: zapnuto, připomínka ano/ne, kolik dní
  předem (1–6), SMS poslední den, e-mail k vrácené, hodiny posílání. Technické nastavení jen v nástrojích.
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
php artisan automations:boxes                    # přehled dárkových balíčků (plánovač každých 10 minut)
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

## Úpravy po prvním běhu nanečisto (27. 10. 2026)

- **Na výdejním místě** = u Zásilkovny stav `PARCELSHOP` (datum dá její sledování), u ostatních dopravců navíc
  poslední událost `PARCELSHOP`. Zaslat nechává `PARCELSHOP` i u zásilky, která se vrátila do rozvozu (DPD).
- **Jede zpět** podle textu událostí (bude vrácena, odmítl převzetí, uplynula doba uskladnění, return…) = žádná připomínka
  ani SMS. E-mail k vrácené zásilce až podle `returned` ze Zaslatu.
- **SMS jen přesně v poslední den** (zbývá 0 dní). Po konci úložní doby už se nic neposílá.
- **`reminders_since`**: při zapnutí automatizace se uloží dnešní den a připomínka e-mailem jde jen zásilkám, které
  na výdejní místo dorazily od něj. Starším ji klient posílal ručně. SMS v poslední den a e-mail k vrácené zásilce platí pro všechny.
- Zaslat sám mění stavy objednávek v Upgates („API Zaslat“: Připraveno na odeslání, Odeslána, někdy Vráceno a zpět Odeslána).
- Úložní doba PPL a DPD je odhad (7 dní od příchodu). PPL boxy mají možná 72 hodin, neověřeno. Mění se v nástrojích u automatizace.

## Hlídání vrácených zásilek (27. 10. 2026)

Zaslat zásilku vrácenou odesílateli občas označí jako doručenou (`DELIVERED`, `returned: false`) a objednávce dá
**Vyřešeno**: PPL, objednávka 03896, poslední událost „We have returned the shipment to the sender.“. Jindy dá klient
Vyřešeno vrácené objednávce ručně (04004, odmítnutá Zásilkovna). Vyřešeno spouští fakturu (u dobírky vznikne
k nezaplacené objednávce) a za 3 dny žádost o hodnocení.

- Každá aktivní zásilka, která se vrací (`returned` nebo text událostí), se zapíše do `snapshot.returning`
  (číslo zásilky, objednávka, den) a hlídá se 21 dní, i když z aktivních zmizí.
- Když má její objednávka v exportu stav `status_resolved` (výchozí **Vyřešeno**), web se zeptá Zaslatu
  (`POST /shipments/tracking`). Příznak `returned` nebo poslední událost o vrácení = upozornění správcům a klientovi
  (jednou), zásilka se přestane hlídat. Doručená příjemci (vrácení se nakonec nekonalo) se jen přestane hlídat.
- Automatizace sama nic nemění: přepnutí na Vráceno by poslalo e-mail o nevyzvednuté zásilce podruhé.
  Upozornění radí přepnout Vyřešeno na Nevyřešeno (žádost o hodnocení pak neodejde).

## Dárkové balíčky k zabalení (28. 10. 2026)

Skládačka dárkových balíčků na e-shopu (Svět Cejlonu) píše do poznámky krabice v objednávce obsah balíčku,
odkaz na obrázek poskládané krabice (`https://svetcejlonu.tomaskebza.cz/b/<id>`) a značky `[balicek][…]`.
Export objednávek má poznámku v `ITEMS/ITEM/NOTE`, takže ji `UpgatesOrders::parse` čte rovnou z něj
(`GiftBoxes::fromNote`). Z exportu se k objednávce s balíčkem uloží jen název krabice, obsah, počet kusů,
odkaz a čas vytvoření, nic o zákazníkovi (ani poznámka zákazníka).

- Každý běh uloží do `snapshot.boxes` přehled (`GiftBoxes::overview`): **k zabalení** = stav není odeslaný
  (`status_sent`, `status_reminder`, `status_last_day`, `status_returned`, `status_resolved`), nejstarší nahoře.
  Odeslané se ukazují ještě 14 dní, stornované vůbec.
- Kompletní export se kvůli tomu stahuje znovu (verze cache 2), jinak by starší balíčky chyběly až 6 hodin.
- **Častěji než hodinový běh:** `automations:boxes` každých 10 minut (v :05, :15…, mimo :40 kvůli sdílené cache exportu)
  obnoví jen `snapshot.boxes` (`GiftBoxes::refresh`), u zapnutých automatizací, které balíčky mají. Zákazníkům nic nepošle.
  V účtu klienta je u sekce tlačítko **Aktualizovat** (to samé hned, nejvýš 1× za minutu na automatizaci). Upgates export
  za 24 h obnovuje zhruba po 5 minutách, rychleji to nejde. Popisek sekce ukazuje čas posledního obnovení.
- V účtu klienta je sekce jen tehdy, když nějaký balíček je. Karta: obrázek `/b/<id>.jpg` (kreslí server
  náhledů, `server-sbirka/balicek.js` v repu Svět Cejlonu), číslo a stav objednávky, krabice a obsah s počty.
  Když obrázek není (server ho ještě neumí), karta ho vynechá a zůstane odkaz na stránku `/b/<id>`.

## Hodiny posílání a vrácené zásilky (28. 10. 2026)

Po prvním ostrém dni (Marek): e-maily „Čeká na vyzvednutí“ odešly ve 2:00 (běh v 1:40, Upgates import zpracoval ve 2:00),
ranní souhrn psal „1 vrácená“ bez čísla a vrácené se ztrácely mezi posledními 50 zprávami.

- **Hodiny posílání** `send_from`–`send_until` (výchozí 8–20, Praha, od včetně, do vyjma; od ≥ do = bez omezení).
  Engine běží dál každou hodinu a zprávy zakládá, ale do importu Upgates je nahraje jen v povolených hodinách
  (`Automation::canSendNow`). Noční změny odejdou v 8:40. Chyba „Upgates stav nezměnil“ se počítá 3 h od nahrání
  (`exported_at`), čekání přes noc chybou není. SMS v poslední den tak přijde ráno téhož dne.
  Klient hodiny mění v účtu (Upravit), správce v nástrojích. `automations:run` vypíše, kolik změn čeká.
- **Vrácené zásilky** v účtu klienta: tabulka z `snapshot.returning` (hlídání 21 dní, viz výše), u každé zásilky
  nově dopravce, `returned` (už dorazila zpět) a stav objednávky z exportu. „Vrátila se“ = `returned` nebo stav
  objednávky `status_returned`, jinak „Na cestě zpět“. Zásilka prověřená kvůli Vyřešeno (`checked`) v přehledu
  zůstává, upozornění jde dál jen jednou. Záznamy zapsané před 28. 10. dopravce nemají, dokud je běh znovu neuvidí mezi aktivními.
- **Ranní souhrn** u každého druhu zprávy vypíše čísla objednávek.
- Odpověď na SMS: Upgates (Nastavení → SMS) odesílatele nastavit neumí, na SMS odpovědět nejde. Kontakt patří do textu SMS.
