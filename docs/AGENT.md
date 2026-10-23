# Agent na serveru (Claude Code)

Claude Code běží na serveru TAVEO (88.198.31.29) v Docker kontejneru
`taveo-agent`. Každý projekt klienta má vlastní složku a vlastní vlákna,
která běží dál i po zavření terminálu. Soubory instalace jsou v
[`infra/agent/`](../infra/agent/), na web se nenasazují.

## V nástrojích

**Nástroje → Agent** (`/nastroje/agent`): projekty a u každého terminál s Claudem
přímo v prohlížeči. Vidí je každý přihlášený do nástrojů (Tom, Pavel).

- Po otevření projektu se terminál připojí k naposledy běžící konverzaci. Když
  žádná neběží, sama se založí nová a dá se rovnou psát. U živého webu před tím
  proběhne snímek souborů a záloha databáze (pár vteřin).
- **Nová konverzace** nad terminálem založí další. Pod terminálem je seznam
  konverzací: běžící jde **otevřít**, uspanou **probudit** (`claude --resume`).
  Název je první zpráva z konverzace.
- **Uspat / Připnout:** nepřipnutá konverzace se po 12 hodinách bez pohybu sama
  uspí (proces skončí a uvolní asi 300–400 MB paměti, konverzace zůstane uložená).
  **Připnutá (trvalá)** běží pořád, takže je dostupná i v aplikaci Claude na mobilu,
  kde uspanou probudit nejde. Doba se mění proměnnou `USPAT_PO_HODINACH`.
- Konverzace běží dál i po zavření stránky. V jedné může být víc lidí zároveň
  a každý vidí totéž, mezi konverzacemi si ale každý přepíná sám.
- **Přidat projekt** (v seznamu projektů) umí repozitář z GitHubu a projekt bez
  kódu. Web na serveru připojí jen root přes SSH, protože dává agentovi přístup
  k živému webu.
- Klient má v kartě pole **Projekt agenta**, pak se v seznamu ukazuje jeho jméno
  a z karty klienta vede tlačítko Agent.
- **Aplikace Claude (desktop, claude.ai/code, mobil):** každá konverzace z nástrojů
  se do ní sama připojí (Remote Control, `remoteControlAtStartup` v
  `managed-settings.json`) a dá se v ní pokračovat. Každý projekt má navíc okno
  se serverovým režimem (`claude remote-control`, relace „TAVEO · projekt“), takže
  v aplikaci jde zakládat i nové konverzace, které běží na serveru. Tlačítko
  **Otevřít v aplikaci Claude** na stránce projektu. Když serverový režim spadne,
  služba ho do tří minut spustí znovu.
- **Kopírování:** výběr myší text rovnou zkopíruje (v rohu se ukáže „Zkopírováno“).
  Když prohlížeč zápis odmítne (Safari), dokončí ho ⌘C. Výběr s ⌥ funguje klasicky.
  Dlouhé odkazy zalomené přes více řádků jdou otevřít kliknutím kamkoli na ně.
- **Databáze webu na serveru:** kontejner má socket databáze serveru (jen pro čtení
  souboru, `/run/mysqld`). Agent se přihlásí údaji z `.env` připojeného webu,
  takže `php artisan` a `wp` fungují jen pro jeho databázi. Web je v kontejneru
  i pod serverovou cestou (`/home/taveo.cz/grillnor`), kvůli cache konfigurace.
- **Poprvé** se Claude zeptá na vzhled (Enter) a přihlášení: zvolit předplatné,
  otevřít odkaz, přihlásit se a vložit kód do terminálu. Pak už nikdy. Důvěru
  ke složkám projektů a tmavý vzhled nastavuje služba předem.
- **Využití předplatného** je uprostřed hlavičky celých nástrojů: relace (5 h)
  a týden (7 dní), procento a pruh. Stejná čísla jako `/usage` v Claude Code.
  Kliknutím se rozbalí podrobnosti: týdenní limit pro jeden model, kdy se který
  limit obnoví, a ryska, kolik z okna už uběhlo (pruh za ryskou = limit dojde
  dřív). Barva: zelená, od 75 % oranžová, od 90 % červená. Obnovuje se
  každé 2 minuty. Služba čte token, kterým je Claude v kontejneru přihlášený
  (`~/.claude/.credentials.json`), a ptá se `api.anthropic.com/api/oauth/usage`.
  Token sama neobnovuje (dělá to Claude Code), ven pustí jen čísla. Když
  přihlášení vyprší, ukazatel ukáže „Claude –“ a důvod v rozbalení.

Jak to drží pohromadě:

| Kus | Kde | K čemu |
|---|---|---|
| Služba v kontejneru | `infra/agent/terminal/server.js`, port 7681 jen na 127.0.0.1 | API pro vlákna (tajemství `AGENT_SECRET`) a WebSocket terminálu (vstupenka platná minutu) |
| LiteSpeed | `websocket /agent-terminal/` ve `vhost.conf` webu taveo.cz | pošle terminál z prohlížeče do kontejneru |
| Fronta | `/srv/agent/fronta`, `taveo-agent-fronta.path` + `agent-fronta.py` | co potřebuje roota (záloha databáze, nový projekt), kontejner jen popíše |
| Nástroje | `App\Support\Agent\AgentServer`, stránky `Filament/Tools/Pages/Agent` | seznam, vlákna, terminál (`resources/js/agent-terminal.js`) |
| Předplatné | `GET /api/predplatne` ve službě, `ClaudeUsage` + `UsageLimit`, `Filament/Tools/Livewire/ClaudeUsageMeter` | ukazatel v hlavičce, odpověď drží služba minutu a Laravel 2 minuty |

Tajemství je na serveru v `/srv/agent/terminal.env` a v `.env` webu (`AGENT_SECRET`).
Lokálně jde stránka vyzkoušet přes SSH tunel: `ssh -N -L 7681:127.0.0.1:7681 root@88.198.31.29`
a do `.env` `AGENT_SECRET` ze serveru a `AGENT_WS_URL=ws://127.0.0.1:7681/`.

**Pozor na CyberPanel:** úprava webu taveo.cz v panelu (PHP, SSL) může `vhost.conf`
přepsat a blok `websocket` zmizí. Terminál pak neodpoví, zbytek stránky funguje.
Blok stačí vrátit na konec souboru a restartovat LiteSpeed (`/usr/local/lsws/bin/lswsctrl restart`).
Záloha konfigurace před úpravou: `/root/opravy-prav-2026-10-22/taveo-vhost.conf.pred-agentem`.

## Hotová práce (karta v nástrojích)

Nad terminálem každého projektu je karta s tím, co agent dokončil. Kdo nezná
git (Pavel), v ní zkontroluje snímky a popis. Co karta umí, záleží na typu:

| Typ | Karta | Tlačítka |
|---|---|---|
| git | Hotová práce | Zkontrolovat, **Zveřejnit** (testy → main → nasazení), Zahodit, Vrátit poslední zveřejnění |
| server (živý web) | Co agent změnil | Zkontrolovat, **V pořádku**, **Vrátit** web do stavu před změnou |
| prazdny (Shoptet, Upgates) | Hotové podklady | Zkontrolovat s tlačítkem **Zkopírovat** u každého kódu, Hotovo |

Podklady přikládá agent do `/projekty/<projekt>/predani/<krátký-popis>/`
(`popis.md`, snímky `*.png`, u živého webu `zacatek`, u Shoptetu kódy jako
`*.html`, `*.css`, `*.txt`). Návod pro agenta je v paměti každého projektu.
Shoptet ani Upgates šablonu přes API měnit nedovolí, proto se kódy kopírují
ručně.

### Živý web: Vrátit

Agent před změnou zapíše do předání čas (`zacatek`). **Vrátit** přes frontu
spustí na serveru `agent obnovit <projekt> <čas> [databaze]`:

1. uloží současný stav (snímek souborů a záloha databáze), takže jde vrátit i vrácení,
2. vrátí soubory z posledního snímku před tím časem (přidané po něm smaže; co
   snímky neukládají, např. nahraná média a cache, zůstane),
3. jen když se zaškrtne **Vrátit i databázi**: nahraje zálohu databáze před tím
   časem. Smaže tím i objednávky, zprávy z formulářů a komentáře od té doby,
   proto je to vypnuté.

Výsledek si nástroje vyzvednou (`GET /api/fronta/<id>`) a ukážou na kartě.
Vrácení po ruce: `agent vratit <projekt>` ukáže snímky.

### Git: Zveřejnit

1. Agent dokončí úpravu a pošle větev `agent/…`. K ní přiloží **popis a snímky**
   do `/projekty/<projekt>/predani/<větev bez agent/>/`.
2. Karta ukáže nadpis, náhled snímku, počet změněných souborů. **Zkontrolovat**
   otevře panel s popisem, snímky (kliknutím 1:1) a seznamem změn.
3. **Zveřejnit** (Tom i Pavel):
   *Testy* (pull request, CI na GitHubu) → sloučení do `main` → *Nasazení*
   (workflow Deploy) → *Na webu*. Repozitář bez nasazovacího workflow skončí
   stavem *Sloučeno, bez nasazení*. Průběh je na kartě a dokončí se i bez
   otevřené stránky (plánovač, `agent:zverejneni` každou minutu).
4. **Vrátit** u poslední zveřejněné úpravy: GitHub založí opačný pull request
   a ten jde stejnou cestou. **Zahodit** smaže větev jen ze schránky.

Pojistky: sloučí se jen commit, který člověk viděl (když agent mezitím
pokračoval, GitHub sloučení odmítne a karta řekne „zkontrolujte znovu“).
Bez zelených testů se nic neslučuje. Úprava souborů v `.github/` nebo `infra/`
dostane varování „Mění nastavení serveru“. Token GitHubu má jen web, agent
ho nevidí, a do `main` sám pořád nemůže.

Kód už před zveřejněním nikdo nečte, kontroluje se podle snímků, popisu
a testů. Tak to chtěl Tom (23. 10. 2026), náhradou je tlačítko Vrátit.

**Služba agenta se aktualizuje sama:** po sloučení u projektu `taveo`
(`AGENT_INFRA_PROJECT`) web pošle do fronty `aktualizovat`. Root
(`agent aktualizovat`) stáhne `main` z GitHubu do `/srv/agent/zdroj-taveo.git`
a když se změnil `infra/agent/terminal/server.js`, nahraje ho a službu restartuje.
Bere jen tenhle soubor (běží v kontejneru bez práv). Co běží jako root
(`agent.sh`, `agent-fronta.py`), Dockerfile a ostatní se z GitHubu samo
nenahrazuje, to dál nahrává Tom (oddíl Úprava instalace).

| Kus | Kde |
|---|---|
| Hotová práce ve službě | `GET/DELETE /api/projekty/<projekt>/predani`, snímky `…/predani/<jméno>/<soubor>`, vrácení webu `POST …/predani/<jméno>/vratit` |
| Vrácení živého webu | `App\Support\Agent\SiteRestorer` → fronta → `agent obnovit` |
| Pull request, testy, sloučení | `App\Support\Agent\Publisher`, `GitHub`, tabulka `agent_publications` |
| Karta v nástrojích | `Filament/Tools/Livewire/AgentHandovers`, snímky přes `/nastroje/agent/<projekt>/snimek/…` |
| Aktualizace služby | `POST /api/aktualizovat` → fronta → `agent aktualizovat` |

### Zapnutí (jednou, Tom)

1. **Token GitHubu:** github.com → Settings → Developer settings → Fine-grained
   tokens → Generate new token. Repository access: jen `kebzat/tavo` (a repozitáře
   dalších git projektů, když přibudou).
   Permissions: *Contents* Read and write, *Pull requests* Read and write,
   *Actions* Read-only. Platnost nejdéle rok, pak vytvořit nový.
2. Na serveru do `.env` webu (`/home/taveo.cz/public_html/.env`):
   `GITHUB_TOKEN=github_pat_…`. Nasazení si ho samo načte (`artisan optimize`).
3. Sloučit tuhle změnu do `main` (naposledy ručně).
4. Nahrát službu a skripty pro roota (z Macu, v repozitáři na `main`):
   ```bash
   git pull
   rsync -a infra/agent/ root@88.198.31.29:/srv/agent/docker/
   ssh root@88.198.31.29 'install -m 755 /srv/agent/docker/agent.sh /usr/local/bin/agent && agent sluzba'
   ```
5. Kontrola: Nástroje → Agent → taveo, karta Hotová práce bez žlutého upozornění.

Když token vyprší, karta ukáže „GitHub token neplatí“. Stačí vytvořit nový (krok 1–2)
a na serveru `php artisan optimize`.

## Z terminálu přes SSH

Z Macu (přístup roota má klíč `~/.ssh/id_rsa`):

```bash
ssh -t root@88.198.31.29 agent            # seznam projektů
ssh -t root@88.198.31.29 agent grillnor   # otevře vlákna projektu
```

Uvnitř běží tmux: **Ctrl+b c** nové vlákno, **Ctrl+b n / p** přepínání,
**Ctrl+b d** odejít (vlákna běží dál). Claude se spustí v prvním vlákně sám,
v dalších příkazem `claude`. `claude --resume` vrátí starší konverzaci.

Přihlášení Claude Code (jednou, předplatné Toma): `ssh -t root@88.198.31.29 agent prihlasit`.

## Přidání projektu

| Projekt | Příkaz | Co agent dostane |
|---|---|---|
| Repozitář na GitHubu | `agent pridat taveo git git@github.com:kebzat/tavo.git` | klon s právem čtení, odesílá jen větve `agent/…` |
| Web na tomto serveru | `agent pridat grillnor server /home/taveo.cz/grillnor grillnor` | živou složku webu (poslední argument je databáze pro zálohy) |
| Shoptet, Upgates, marketing | `agent pridat iq-hracky prazdny` | prázdnou pracovní složku |

U repozitáře příkaz napoprvé vypíše dva klíče, které se přidají na GitHubu
(Settings → Deploy keys): první bez zápisu, druhý se zápisem. Pak se příkaz
spustí znovu.

Každý projekt má **paměť** `CLAUDE.md` o složku výš než kód. Claude ji načte,
na web se nedostane a vidí ji každý, kdo s projektem pracuje.

`agent odebrat <projekt>` agentovi přístup vezme. Snímky, zálohy a paměť zůstanou.

## Pojistky

| Riziko | Pojistka |
|---|---|
| Zveřejnění tlačítkem pustí na web chybu | Jen po zelených testech, jen zkontrolovaný commit, tlačítko Vrátit. Úpravy `.github/` a `infra/` mají varování. Viz Zveřejnění. |
| Agent sáhne na jiný web, poštu nebo databázi | Kontejner vidí jen `/srv/agent/projekty`. Weby, `/home`, pošta ani databáze serveru v něm nejsou. |
| Rozbije živý web | Před každým otevřením vláken snímek souborů (git mimo složku webu) a záloha databáze. `agent vratit <projekt>` ukáže, jak se vrátit. |
| Pošle změnu rovnou na produkci | Klíč v kontejneru umí jen číst. Na GitHub posílá server ze schránky jen větve `agent/…` (cron, `agent preposlat`). Do `main` se agent nedostane. |
| Změní data na živém webu | Zápis do databáze, migrace a mazání jedou bez ptaní (zvládne to i marketér). Claude běží v automatickém režimu, jehož kontrola by zápis do databáze živého webu sama zablokovala. Proto `autoMode.environment` a `autoMode.allow` v `managed-settings.json` popisují kontejner a říkají, že úprava připojeného webu je přidělená práce. Výchozí ochrany automatického režimu zůstávají (`$defaults`). Ověří se příkazem `claude auto-mode config`. Háček `zaloha-pred-zmenou` (PreToolUse) těsně před nimi uloží zálohu databáze a snímek souborů, nejvýš jednou za dvě minuty. Když záloha selže, příkaz se nespustí. |
| Spustí ničivý příkaz | `/etc/claude-code/managed-settings.json` v kontejneru: zákaz `migrate:fresh`, `db:wipe`, `rm -rf`, force push, `ssh`; na push, migrace a mazání se ptá. Agent soubor nepřepíše. Režim „bez ptaní“ je vypnutý. Pravidla jsou druhá vrstva, hlavní je kontejner. |
| Vytíží server | Strop 4 jádra, 8 GB RAM. |

Zálohy databází: `/srv/agent/zalohy/<projekt>/` (posledních 50, jen root).
Snímky: `/srv/agent/snimky/<projekt>.git`. Log přeposílání: `/var/log/taveo-agent.log`.

## Úprava instalace

Jen služba (`terminal/server.js`): `rsync` souboru do `/srv/agent/docker/terminal/`
a `agent sluzba`. Služba v kontejneru běží ve smyčce, konverzace v tmuxu ji přežijí.

Cokoli jiného (Dockerfile, tmux.conf, entrypoint) vyžaduje přestavění:

```bash
rsync -a infra/agent/ root@88.198.31.29:/srv/agent/docker/
ssh root@88.198.31.29 'install -m 755 /srv/agent/docker/agent.sh /usr/local/bin/agent \
  && cd /srv/agent/docker && docker compose up -d --build'
```

Přestavění kontejneru ukončí běžící vlákna. Konverzace zůstanou
(`claude --resume`), přihlášení také: domovská složka agenta je na serveru
v `/srv/agent/domov`.

## Bezpečnostní úpravy serveru (22. 10. 2026)

- `wp-config.php`, `.env` a zálohy databází byly čitelné pro všechny uživatele
  serveru (61 souborů u všech webů). Teď jen pro vlastníka. Původní práva
  a skript na vrácení: `/root/opravy-prav-2026-10-22/`.
- Domovské složky webů mají 711 (juliatom.cz měla 777).
- GitHub nasazuje TAVEO jako `taveo1015`, ne jako root. Klíč GitHubu na roota
  odebrán.
- `db:zaloha` ukládá zálohy s právy 600.
