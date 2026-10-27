# Automatizace marketingu

Postavené podle dotazníku Pavla (22. 10. 2026, nástroje → Dotazníky). Princip:
**čísla počítá systém, agent je jen vykládá.** Kampaně spouští a rozpočty mění
Pavel ručně, nic neodchází klientovi bez člověka.

## Co kde je

| Co | Kde | K čemu |
|---|---|---|
| Karta klienta („mozek“) | Klienti → klient → Karta klienta | cíle, KPI, rozsah, rozpočet, zdroj pravdy, omezení, schvalování, tón, sezona, kontakty, rozhodnutí se zdrojem a platností, cílové stránky, vyúčtování hodin |
| Ranní přehled | Nástroje → Ranní přehled | co agent ráno zjistil u klientů + moje priority (termíny, vysoká priorita) + návrhy úkolů ke schválení |
| Návrhy úkolů | Klient → Návrhy úkolů | agent je připraví ze schůzek a zpráv, klient je nevidí, dokud je někdo neschválí |
| Kvalita leadů | Klient → Kvalita leadů | zpětná vazba od klienta (ozvali se, relevantní, obchody, hodnota) |
| Kreativní testy | Klient → Kreativní testy | co jsme testovali, proč, výsledek, „málo dat“ ≠ neúspěch |
| Upozornění pro klienta | Reklamy → klient → Cíle a paušál → Upozornění | vlastní prahy, ztlumená pravidla, naléhavost |
| Podklad k vyúčtování | Fakturace → klient → Podklad k vyúčtování | text podle přání klienta, zkopírovat nebo odeslat tlačítkem |
| Checklisty | Klienti → Checklisty → šablony | Onboarding marketingového klienta, Kontrola před spuštěním kampaně |
| Fakta / příčiny / doporučení | Reporty → koncept | agent předvyplní, Pavel upraví, klient je vidí pod komentářem |
| Boosting / awareness | Cíl klienta nebo skupiny „Dosah a interakce“ | dosah, frekvence, CPM, interakce; bez pravidel ceny za konverzi |
| Skupiny kampaní | Reklamy → klient → Nová skupina kampaní | prodej, boosting, nábor v jednom účtu zvlášť: vlastní cíl, rozpočet, čísla, upozornění, report |
| Termíny | Reklamy → Termíny | konce licencí UGC, akce, Black Friday, čekání na podklady; visí v ranním přehledu do vyřešení |
| Historie zásahů | Reklamy → klient → Zapsat zásah | co jsme změnili; graf, report a agent z toho vychází |
| Tržby e-shopu, kvalita leadů | Reklamy → klient | skutečné objednávky a tržby, relevantní leady a obchody vedle čísel z reklam |

## Ráno, krok za krokem (Europe/Prague)

| Čas | Co | Kde běží |
|---|---|---|
| 6:00 | `ads:sync` stáhne čísla | Laravel (cron uživatele `taveo1015`) |
| 6:20 | `marketing:landing-check` zkontroluje cílové stránky | Laravel |
| 6:40 | `ads:alerts` vyhodnotí pravidla | Laravel |
| 6:45 | koncepty reportů (pondělí, 1. v měsíci) | Laravel |
| 6:50 | **ranní přehled od agenta** (všední dny) | kontejner `taveo-agent`, `claude -p` |
| 7:15 | ranní e-mail: nová vs. trvající upozornění + odkaz na ranní přehled | Laravel |

## Limity a ban: rozpočet všech automatických dotazů

Nesmíme dostat ban od Mety, Googlu ani Anthropicu. **Na Metu sahá jen ranní
`ads:sync` v 6:00**, čísla ukládá do databáze a všechno ostatní (administrace,
reporty, upozornění, agent) čte jen z ní. Jinde to `ApiGuard` nepustí (docs/ADS.md,
Jediný vstup na platformy). Každý automat má navíc pevný rozpočet a pojistku, která
ho zastaví dřív, než by se k limitu přiblížil.

| Kam | Běžně za den | Pojistka |
|---|---|---|
| **Meta** (jen čtení, `ads_read`) | v 6:00: 1 dotaz na seznam účtů + 6 na účet (stav, čísla, dosah, zamítnuté reklamy, stav kampaní, čísla reklam) + 1 na skupinu kampaní (dosah). 13 klientů ≈ 80–100. Ruční obnova z detailu klienta: jeden účet, nejvýš jednou za 2 h | `ApiGuard`: mimo `ads:sync` nic neodejde, strop 300/den, při vytížení nad 75 % nebo „moc dotazů“ pauza do rána, žádné opakování, účet stažený ten den se podruhé nestahuje. Nikdy nezapisujeme do reklamních účtů. |
| **Google Ads** | 2 na účet | stejná pojistka, strop 200/den |
| **GA4** | 2 na property | stejná pojistka, strop 200/den |
| **Weby klientů** (kontrola stránek) | 1 dotaz na adresu, nejvýš 5 adres na klienta, pauza 0,5 s | timeout 20 s, žádné opakování, vlastní User-Agent |
| **Claude (předplatné)** | 1 automatický běh ve všední dny (ranní přehled), postupně klient po klientu | značka „hotovo“ na den hned na startu (ani pád nespustí druhý běh), strop 150 kroků, limit 40 min, jen `taveo` a čtení souborů (`--permission-mode dontAsk`). Narazí-li na limit předplatného, zaloguje to a nic neopakuje. |
| **Rozhraní agenta** `/nastroje/api/agent` | desítky dotazů ráno | vlastní token `AGENT_API_TOKEN`, 120 dotazů/min |

Co ban riskuje i tak, a není to technika:

- **Sdílený účet Claude.** Pavel pracuje pod předplatným Toma. Podmínky
  Anthropicu sdílení účtu nepovolují, je to největší riziko banu. Řešení:
  Team plán s místem pro každého (viz konverzace 22. 10.).
- **Osobní údaje zákazníků v AI.** Pavel podle dotazníku nemá s klienty
  zpracovatelské smlouvy. Agent pracuje s agregovanými čísly a pravidla
  mu osobní údaje zakazují, ale exporty zákazníků do AI nepatří vůbec.

## Agent

- Projekt `marketing` (prázdný, bez kódu): paměť `infra/agent/marketing/CLAUDE.md`
  (Pavlova pravidla: fakta vs. hypotézy, zdroj a období u každého závěru,
  měření ≠ výkon, nic nedomýšlet, nic neposílat). Poznámky ze schůzek ukládá
  do `kod/klienti/<slug>/`.
- Příkaz `taveo` v kontejneru (`infra/agent/taveo.sh`) volá rozhraní
  `App\Http\Controllers\Agent\AgentApiController`. Data pro agenta skládá
  `App\Support\Marketing\ClientContext` se zdrojem a čerstvostí u každého bloku.
- Ranní běh: `infra/agent/terminal/server.js` (`ranniPrehled`), zadání
  `infra/agent/terminal/ranni-prehled.md`. Log v kontejneru `~/.taveo-ranni/<den>.log`.
  Ručně (ověření): `curl -X POST -H "Authorization: Bearer <AGENT_SECRET>" http://127.0.0.1:7681/api/ranni-prehled`
  na serveru. Běží nejvýš jednou denně.
- Pavel v nástrojích → Agent → `marketing`: „tady je zápis ze schůzky
  s MIRNIX…“ a agent uloží poznámku, dohody do karty a návrhy úkolů.
- Stav schváleného úkolu agent mění sám (od 26. 10. 2026, chtěl uživatel): `taveo ukoly-seznam <slug>`
  vypíše úkoly s id, `taveo ukol-stav <id> hotovo|rozpracovano|ceka|v-planu ["poznámka"]` stav změní.
- Úkoly rovnou do plánu klienta (od 27. 10. 2026, chtěl uživatel: „já chci, aby jsi rovnou zapisoval“):
  `taveo ukoly <slug>` s `"schvaleno": true` a u úkolu `"v_planu_na": "2026-10"` je založí schválené,
  `taveo ukol-upravit <id> --mesic 2026-10 [--termin …] [--schvalit]` přesune úkol nebo schválí návrh.
  Jen když to člověk v konverzaci řekne, jinak dál návrhy ke schválení. Každá změna jde do interní poznámky.
  Hotový úkol klient uvidí na přehledu v „Co jsme udělali“ (měsíc podle data dokončení), poznámka
  s datem a „agent“ se připíše jen do interní poznámky. Návrhy úkolů dál schvaluje člověk (409).

## Co zatím chybí a proč

- **Sklik**: potřebuje API token a společné vyzkoušení, odhad bez přístupu
  by byl kód naslepo.
- **Objednávky z e-shopů (Shoptet, Upgates)** přes API: každý e-shop
  potřebuje vlastní přístup, domluvit u konkrétních klientů. Do té doby se
  objednávky a tržby zapisují ručně (Reklamy → klient → Zapsat tržby e-shopu).
- **Fronta příspěvků k boostnutí**: Meta k tomu chce další oprávnění (stránky,
  App Review). Čísla jednotlivých reklam už stahujeme (`ads_read` stačí, jeden
  dotaz na účet a den).
- **Vyprodané produkty** v upozorněních: potřebují data z e-shopu. Končící akce
  a licence hlídá kalendář termínů.
