# Trezor přístupů

Nástroje → **Trezor**. Hesla a klíče klientů (FTP, WordPress, Shoptet, Upgates,
jiné administrace, API klíče) na jednom místě, uložená do kolonek. Lidé je
zobrazí a zkopírují, **agent je nedostane nikdy**: podle „Co smí agent“ za něj
s přístupem pracuje web.

## Proč to tak je

Agent v kontejneru přečte každý soubor a proměnnou svého uživatele. Heslo, které
by dostal do zadání nebo do souboru, by mohl vypsat do textu, poslat dál nebo si
ho zapamatovat. Trezor proto hesla drží na webu TAVEO a agent dostane jen výsledek
práce: výpis složky, soubor, odpověď WordPressu, cookies přihlášení.

## Co je kde

| | |
|---|---|
| Data | `vault_entries` (heslo, aplikační heslo, klíč 2FA a poznámka šifrované klíčem aplikace, cast `encrypted`), `vault_uploads`, `vault_logs` |
| Stránka | `App\Filament\Tools\Resources\VaultEntries` (seznam, přidání, úpravy, Nahrání od agenta, Historie) |
| Práce za agenta | `App\Support\Vault`: `FtpConnector`/`NativeFtpClient` (FTP/FTPS, rozšíření PHP `ftp`), `FtpZipper`, `FtpUploader`, `WordPressApi`, `LoginBroker` |
| Rozhraní pro agenta | `/nastroje/api/agent/trezor…` (token agenta), `App\Http\Controllers\Agent\VaultApiController` |
| Příkazy | `taveo trezor`, `ftp`, `ftp-soubor`, `ftp-stahnout`, `ftp-nahrat`, `nahrani`, `wp`, `prihlasit` (`infra/agent/taveo.sh`) |

## Pro lidi

- **Přidat přístup:** druh, klient (nebo TAVEO), adresa, uživatel, heslo a „Co smí agent“.
  Formulář se mění podle druhu (FTP: port, FTPS, složka webu; WordPress: aplikační heslo;
  přihlášení: adresa přihlášení a klíč 2FA).
- **Zobrazit:** chce heslo do nástrojů, pak je trezor 10 minut odemčený (v tomhle přihlášení).
  Každé zobrazení jde do historie. Uložená hesla se do formuláře úprav nenačítají,
  prázdné pole při uložení = beze změny.
- **Kód 2FA:** když je uložený tajný klíč (text pod QR kódem), Zobrazit ukáže aktuální kód.
- **Vyzkoušet:** ověří spojení (FTP výpis složky, WordPress aplikační heslo, jinak přihlášení),
  nic nemění a heslo neukazuje.
- **Nahrání od agenta** (u FTP): co agent chce nahrát, s popisem. Nahrát na web / Zahodit,
  po nahrání Vrátit. Číslo v menu u Trezoru = kolik nahrání čeká.
- **Historie:** kdo (člověk nebo agent) kdy co udělal. Zůstává i po smazání přístupu.
- Smazání přístupu v trezoru heslo u služby nezruší. Zrušit ho je potřeba tam
  (FTP účet, uživatel ve WordPressu, aplikační heslo).

## Co smí agent

| Druh | Úrovně |
|---|---|
| FTP | nic · číst (výpis, soubor, ZIP složky) · nahrávat po schválení · nahrávat hned |
| WordPress | nic · číst přes aplikační heslo + přihlásit se · i měnit přes aplikační heslo |
| Shoptet, Upgates, jiná administrace | nic · přihlásit se |
| API klíč, jiné heslo | jen pro lidi (zatím) |

- **FTP:** cesty jsou vždy od složky webu, `..` neprojde. ZIP vynechá `wp-content/uploads`,
  cache, zálohy, `.git`, `node_modules` a soubory nad 10 MB (seznam v `_vynechano.txt`).
  Nahrání: nejvýš 200 souborů a 30 MB najednou. Před nahráním se uloží, co bylo na webu
  (`storage/app/private/trezor/{id}/puvodni`), Vrátit to nahraje zpět a nově přidané soubory
  smaže. Mazat soubory na webu agent nemůže.
- **WordPress:** aplikační heslo (Uživatelé → profil → Aplikační hesla) projde i přes ochranu
  „nejsem robot“ a jde kdykoli zrušit. Zápis jen s „i měnit“ a nikdy `/wp/v2/users`,
  aplikační hesla ani `/wp/v2/settings` (agent by si jinak mohl založit vlastní přístup,
  který trezor nezruší). Aktualizace pluginů REST API WordPressu neumí, ty jdou přes FTP
  nebo přihlášení.
- **Přihlášení** (`LoginBroker`): web otevře přihlašovací stránku, najde formulář s heslem,
  vyplní ho (i s kódem 2FA, když je uložený klíč) a agentovi vrátí jen cookies. Agent si je
  vloží do prohlížeče (`browser_run_code_unsafe`: `await page.context().addCookies(cookies)`).
  Ověření „nejsem robot“ (Turnstile, reCAPTCHA) projít neumí, to hlásí srozumitelně.
  Přihlášený agent smí v administraci, co smí ten účet: dávat mu vlastní účet s menšími právy.

## Mezery a co ověřit

- Token agenta je jeden pro všechny projekty v kontejneru. Trezor proto nerozliší, jestli
  přístup Pizza di Gomma použil agent projektu `pizzadigomma`, nebo jiný. Kontrolou je historie.
- Úspěšné přihlášení naostro zatím neproběhlo: Shoptet ověřen jen na formuláři a odmítnutí
  špatného hesla (classic.shoptet.cz), Upgates vůbec (adresa přihlášení je odhad, `/admin/`).
  Při prvním skutečném účtu zkontrolovat Vyzkoušet a případně doplnit adresu přihlášení.
- WordPress Pizza di Gomma (pizzadigomma.eu) má na přihlášení Turnstile, agent tam jde jen
  přes aplikační heslo a FTP.
- FTP potřebuje na serveru rozšíření PHP `ftp` (v kontejneru je, na produkčním lsphp84 ověří
  první Vyzkoušet, jinak hláška „chybí rozšíření“). Ověřeno proti skutečnému FTP serveru
  (pyftpdlib) včetně nahrání a vrácení, FTPS jen v kódu.
- Hesla šifruje `APP_KEY`. Kdo má `.env` i databázi, hesla přečte, proto `.env` jen pro vlastníka
  (docs/SECURITY.md, zatím ve větvi `agent/zabezpeceni-2`). Změna `APP_KEY` bez `APP_PREVIOUS_KEYS` by trezor znečitelnila.
