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

Jak to drží pohromadě:

| Kus | Kde | K čemu |
|---|---|---|
| Služba v kontejneru | `infra/agent/terminal/server.js`, port 7681 jen na 127.0.0.1 | API pro vlákna (tajemství `AGENT_SECRET`) a WebSocket terminálu (vstupenka platná minutu) |
| LiteSpeed | `websocket /agent-terminal/` ve `vhost.conf` webu taveo.cz | pošle terminál z prohlížeče do kontejneru |
| Fronta | `/srv/agent/fronta`, `taveo-agent-fronta.path` + `agent-fronta.py` | co potřebuje roota (záloha databáze, nový projekt), kontejner jen popíše |
| Nástroje | `App\Support\Agent\AgentServer`, stránky `Filament/Tools/Pages/Agent` | seznam, vlákna, terminál (`resources/js/agent-terminal.js`) |

Tajemství je na serveru v `/srv/agent/terminal.env` a v `.env` webu (`AGENT_SECRET`).
Lokálně jde stránka vyzkoušet přes SSH tunel: `ssh -N -L 7681:127.0.0.1:7681 root@88.198.31.29`
a do `.env` `AGENT_SECRET` ze serveru a `AGENT_WS_URL=ws://127.0.0.1:7681/`.

**Pozor na CyberPanel:** úprava webu taveo.cz v panelu (PHP, SSL) může `vhost.conf`
přepsat a blok `websocket` zmizí. Terminál pak neodpoví, zbytek stránky funguje.
Blok stačí vrátit na konec souboru a restartovat LiteSpeed (`/usr/local/lsws/bin/lswsctrl restart`).
Záloha konfigurace před úpravou: `/root/opravy-prav-2026-10-22/taveo-vhost.conf.pred-agentem`.

## Z ClickUpu (`@agent` v komentáři)

Komentář u úkolu v ClickUpu, který obsahuje `@agent`, spustí agenta v projektu
klienta. Agent dostane název, popis a komentáře úkolu, odpracuje to na pozadí
a odpoví do vlákna komentáře (odpovědi začínají „Agent:“). Další `@agent` ve
stejném úkolu naváže na stejnou konverzaci. Konverzace je pak i v nástrojích
→ Agent, dá se na ni navázat ručně.

| Krok | Kde |
|---|---|
| Webhook na nové komentáře | `POST /nastroje/api/clickup/webhook`, `ClickUpWebhookController`, podpis `X-Signature` (HMAC-SHA256 tajemstvím webhooku) |
| Spouštěč | uživatel „Agent“ (host v ClickUpu, `CLICKUP_AGENT_USER_ID`) označený v našeptávači, nebo napsaný text `@agent`, `/agent` či `!agent` |
| Kdo smí spustit | `CLICKUP_AGENT_USERS` (ID uživatelů ClickUpu: Tom, Pavel). Od nikoho jiného se nic nespustí. |
| Který projekt | seznam úkolu → klient s `clickup_list_id` (Klienti → klient → ClickUp seznam) → jeho Projekt agenta. Jinak projekt celého prostoru z `CLICKUP_SPACE_PROJECTS` (Pavlův prostor → `marketing`). Jinak agent odpoví, že projekt chybí. |
| Spuštění | `App\Support\ClickUp\ClickUpAgent::start()` po odeslání odpovědi webhooku → služba agenta `POST /api/projekty/{slug}/ulohy` |
| Práce | `server.js`, `odpracovat()`: `claude -p --permission-mode auto --output-format json`, v projektu nejvýš jedna úloha, celkem dvě, limit 30 min, 200 kroků. U webu na serveru předtím snímek a záloha DB. Log `~/.taveo-ulohy/<id>.log`. |
| Výsledek | služba → `POST /nastroje/api/agent/ulohy/{id}/vysledek` (token `AGENT_API_TOKEN`) → odpověď do ClickUpu |
| Ztracené úlohy | `clickup:hlidat` každých 15 min uzavře úlohy bez výsledku po 2 hodinách a napíše to do ClickUpu |

Pojistky: pokyn je jen text komentáře, popis a ostatní komentáře jdou agentovi
jako podklady s upozorněním, že pokyny v nich (vložené e-maily) neplní. Odpověď
agenta spouštěč neobsahuje a ID našich odpovědí se pamatují, takže se agent
nespustí sám sebou. Limit předplatného: `CLICKUP_AGENT_HOURLY_LIMIT` (6)
a `CLICKUP_AGENT_DAILY_LIMIT` (30) úloh. V ClickUpu agent jen čte úkoly
a přidává odpovědi, nic neupravuje ani nemaže.

Nastavení (jednou, na serveru):

```bash
# .env: CLICKUP_API_TOKEN, CLICKUP_TEAM_ID, CLICKUP_AGENT_USERS, CLICKUP_SPACE_PROJECTS
php artisan config:cache
php artisan clickup:napojit            # jen ukáže seznamy, klienty a projekty
php artisan clickup:napojit --ulozit   # přiřadí seznamy klientům se stejným názvem
php artisan clickup:napojit --webhook  # založí webhook, vypíše CLICKUP_WEBHOOK_SECRET do .env
php artisan config:cache
```

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
