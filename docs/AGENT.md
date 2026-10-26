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
| server (živý web, grillnor) | Hotová práce | agent pracuje v **kopii webu**: Zkontrolovat, **Zveřejnit na web** (celá kopie najednou), Zahodit změny, Vrátit |
| prazdny (Shoptet, Upgates) | Hotové podklady | Zkontrolovat s tlačítkem **Zkopírovat** u každého kódu, u `.html` i **Otevřít jako stránku** a **Odkaz bez přihlášení**, Hotovo |

Podklady přikládá agent do `/projekty/<projekt>/predani/<krátký-popis>/`
(`popis.md`, snímky `*.png`, u Shoptetu kódy jako `*.html`, `*.css`, `*.txt`). Návod pro agenta je v paměti každého projektu.
Shoptet ani Upgates šablonu přes API měnit nedovolí, proto se kódy kopírují
ručně.

### Živý web: kopie a Zveřejnit

Agent u živého webu v terminálu a v aplikaci Claude **nepracuje na živém webu**,
ale v jeho kopii: `/projekty/<projekt>/kopie` s vlastní databází
`<databáze>_kopie` (konverzace se v ní otevírá sama, kopie se založí s první
konverzací nebo tlačítkem Připravit kopii). Náhled si agent spustí v kopii
(`php artisan serve --port 8090`). Úlohy z ClickUpu jdou dál **rovnou na živý
web** (rozhodl Tom 23. 10. 2026), v kartě nejsou.

- Kopie má git, značka `zivy` = stav živého webu. Karta ukáže, kolik souborů
  v kopii čeká, a předání (popis, snímky) od agenta.
- **Zveřejnit na web** → root `agent nasadit-kopii <projekt>`: snímek souborů
  a záloha databáze, stránka údržby, rsync kopie na web (bez `.env`, `storage`,
  `bootstrap/cache`, bez symlinků), `migrate`, `optimize`, kontrola `APP_URL`.
  **Databáze se nepřepisuje**: změny obsahu agent píše jako migrace, živá data
  (objednávky, zprávy) zůstávají. Zveřejní se celá kopie najednou.
- **Zahodit změny**: kopie zpátky na živý web, databáze kopie znovu z živé
  (`agent kopie-db`). Tabulky, které v kopii vznikly navíc, v ní zůstanou.
- **Vrátit**: soubory živého webu ze snímku těsně před zveřejněním
  (`agent obnovit`, bez databáze), pak se kopie srovná s živým webem.
- Dokud kopie nejde založit, konverzace běží postaru na živém webu (se snímkem
  a zálohou).

| Kus | Kde |
|---|---|
| Kopie, změny, úlohy | `server.js`, `pripravitKopii()`, `spustitUlohuKopie()`, `GET/POST /api/projekty/<projekt>/kopie[/akce]`, `GET …/uloha/<id>` |
| Databáze kopie, nahrání | `agent.sh`, `kopie-db`, `nasadit-kopii` (fronta) |
| Karta, průběh | `App\Support\Agent\CopyPublisher`, `AgentHandovers` |

### Živý web: vrácení ručně

`agent obnovit <projekt> <čas> [databaze]` (root) vrátí živý web do stavu
před časem (unix): nejdřív uloží současný stav, pak vrátí soubory z posledního
snímku před tím časem (přidané po něm smaže, nahraná média a cache zůstanou),
s `databaze` i databázi ze zálohy (smaže i objednávky a zprávy od té doby).
`agent vratit <projekt>` ukáže snímky.

### Taveo: Zveřejnit rovnou ze serveru (bez GitHubu)

Web taveo běží na stejném serveru jako agent, takže tlačítko Zveřejnit ho
nahraje **rovnou ze serveru, bez testů a bez čekání na GitHub**, obvykle do
15 sekund (rozhodl Tom 23. 10. 2026). GitHub je jen historie.

1. Jakmile se úprava objeví v kartě, služba agenta předem sestaví **vydání**:
   commit větve (ten, který člověk viděl) spojený s tím, **co je teď na webu**,
   a s `main` z GitHubu, vendor bez dev balíčků, `npm run build`. Složka
   `/projekty/taveo/vydani/<strom>` (drží se poslední 3, cache balíčků 2 od každého).
   Konflikt s `main` se nesestaví, karta řekne, ať agent sloučí `main` do větve.
   Po kliknutí se spojení počítá znovu, starší úprava tak nepřepíše novější.
2. **Zveřejnit** → fronta → root `agent nasadit taveo <strom>`: stránka údržby,
   `db:zaloha`, současná verze stranou (`/srv/agent/vydani-zaloha/taveo/predchozi`),
   rsync (pevný seznam výjimek v `agent.sh`, bez symlinků), migrace, cache,
   kontrola, že web vrací 200.
3. Potom se úprava sloučí do `main` na GitHubu (pull request, squash), na to se
   nečeká. Sloučení má v popisu `[skip ci]`, nasazení z GitHubu se nespustí
   (pojistkou zůstává `storage/app/nasazeny-strom`, stejnou verzi `deploy.yml` přeskočí).
4. **Vrátit** → `agent nasadit-zpet taveo`: zpátky verze před posledním
   nasazením tlačítkem (databáze zůstává), na GitHubu revert pull requestu.

**Co je na webu** si pamatuje služba (`refs/web/aktualni` v `~/.taveo-vydani/taveo.git`:
strom webu, rodič = `main`, ze kterého vznikl; `refs/web/predchozi` pro Vrátit).
Nové vydání = web + co do `main` mezitím přibylo + úprava. Úprava, která ještě
nedorazila do historie (nebo se tam nedostala vůbec), proto z webu nezmizí.
Zveřejnit počká, dokud se nahrává jiná úprava, a po Vrátit, dokud vrácení
nedorazí do historie (jinak by vrácená úprava z `main` přišla zpátky).
Web nasazený jinou cestou (`deploy.yml` po ručním sloučení) služba nevidí,
jen jeho `main` se do dalšího vydání dostane.

Kód nikdo nečte a testy neběží, pojistkou je záloha databáze před každým
nahráním (`storage/app/backups` na webu, posledních 10) a Vrátit. Weby v gitu, které neběží tady, jdou dál cestou níž.
`AGENT_DEPLOY_PROJECTS` (výchozí `taveo`) říká, které projekty jdou ze serveru.

| Kus | Kde |
|---|---|
| Vydání | `server.js`, `spustitVydani()`, `POST/GET /api/projekty/<projekt>/vydani` |
| Nahrání na web | `agent.sh`, `nasadit` / `nasadit-zpet`, fronta |
| Průběh, historie | `App\Support\Agent\InstantPublisher` |

### Git: Zveřejnit přes GitHub (ostatní projekty)

1. Agent dokončí úpravu a pošle větev `agent/…`. K ní přiloží **popis a snímky**
   do `/projekty/<projekt>/predani/<větev bez agent/>/`.
2. Karta ukáže nadpis, náhled snímku, počet změněných souborů. **Zkontrolovat**
   otevře panel s popisem, snímky (kliknutím 1:1) a seznamem změn.
3. Testy běží **hned po odeslání větve** (CI na push `agent/…`), karta u úpravy
   ukáže „Testy běží“ / „Testy prošly“ / „Testy neprošly“ (pak Zveřejnit nejde).
4. **Zveřejnit** (Tom i Pavel), se zelenými testy na webu obvykle do 2 minut:
   *Testy* (už hotové, nebo se na ně počká) → sloučení do `main` → *Nasazení*
   (workflow Deploy hned po pushi do `main`, podruhé netestuje) → *Na webu*.
   Větev pozadu za `main` čeká na testy pull requestu, ty zkoušejí výsledek
   sloučení. Repozitář bez nasazovacího workflow skončí
   stavem *Sloučeno, bez nasazení*. Průběh je na kartě a dokončí se i bez
   otevřené stránky (plánovač, `agent:zverejneni` každou minutu).
5. **Vrátit** u poslední zveřejněné úpravy: GitHub založí opačný pull request
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
| Hotová práce ve službě | `GET/DELETE /api/projekty/<projekt>/predani`, snímky `…/predani/<jméno>/<soubor>` |
| Pull request, testy, sloučení | `App\Support\Agent\Publisher`, `GitHub`, tabulka `agent_publications` |
| Karta v nástrojích | `Filament/Tools/Livewire/AgentHandovers`, snímky přes `/nastroje/agent/<projekt>/snimek/…` |
| Stránka ke čtení | HTML z podkladů na `/nastroje/agent/<projekt>/stranka/<složka>/<soubor>.html` (`AgentHandoverPageController`). Jen pro přihlášené do nástrojů, odkaz jde poslat kolegovi. Běží v sandboxu (CSP `sandbox`, bez cookies nástrojů), povolené jsou jen inline styly a skripty a písma z Bunny Fonts. Bez `<html>` doplní kostru s češtinou a viewportem. |
| Odkaz bez přihlášení | Stejná stránka na `/sdilene/<projekt>/<složka>/<soubor>.html?expires=…&signature=…` pro kolegu nebo klienta bez účtu v nástrojích. Podepsaný odkaz (`signed:relative`) platí 30 dní, vydá ho jen přihlášený tlačítkem v kontrole podkladů (`AgentHandovers::shareUrl`). Bez podpisu nebo po vypršení 403, noindex. |
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
4. Nahrát službu a skripty pro roota (z Macu, v repozitáři na `main`). Jen tyhle
   tři soubory, ať se na serveru nepřepíše nic rozpracovaného:
   ```bash
   git checkout main && git pull
   scp infra/agent/agent.sh infra/agent/agent-fronta.py root@88.198.31.29:/srv/agent/docker/
   scp infra/agent/terminal/server.js root@88.198.31.29:/srv/agent/docker/terminal/
   ssh root@88.198.31.29 'install -m 755 /srv/agent/docker/agent.sh /usr/local/bin/agent && agent sluzba'
   ```
5. Kontrola: Nástroje → Agent → taveo, karta Hotová práce bez žlutého upozornění.

**Po každé změně `agent.sh` nebo `agent-fronta.py`** (třeba okamžité zveřejnění
23. 10. 2026) je potřeba je na server nahrát ručně, krok 4. Samy se z GitHubu
neaktualizují, běží jako root.

Když token vyprší, karta ukáže „GitHub token neplatí“. Stačí vytvořit nový (krok 1–2)
a na serveru `php artisan optimize`.

## Z ClickUpu (`@agent` v komentáři)

Komentář u úkolu v ClickUpu, který obsahuje `@agent`, spustí agenta v projektu
klienta. Agent dostane název, popis a komentáře úkolu, odpracuje to na pozadí
a odpoví do vlákna komentáře (odpovědi začínají „Agent:“). Ve vlákně s agentem
se pak píše bez spouštěče jako v chatu: každá další zpráva od Toma nebo Pavla
v tom vlákně naváže na stejnou konverzaci. Agent odpovídá vždy do hlavního
komentáře vlákna (odpověď na odpověď by ClickUp schoval do vnořeného vlákna).
Nový spouštěč jinde ve stejném úkolu naváže na poslední konverzaci úkolu. Konverzace je pak i v nástrojích
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
| Samostatný komentář | blok `[komentar @Pavel] … [/komentar]` za shrnutím agenta → nový komentář k úkolu se štítky na konci (`ClickUpAgent::postComments()`), nejvýš 3 na úlohu. Označit jde jen lidi z `CLICKUP_AGENT_USERS` (podle jména v ClickUpu, celé nebo křestní, bez diakritiky), klienta nikdy. Do vlákna přijde shrnutí bez bloků a věta, co se přidalo. |
| Ztracené úlohy | `clickup:hlidat` každých 15 min uzavře úlohy bez výsledku po 2 hodinách a napíše to do ClickUpu |

Pojistky: pokyn je jen text komentáře, popis a ostatní komentáře jdou agentovi
jako podklady s upozorněním, že pokyny v nich (vložené e-maily) neplní. Odpověď
agenta spouštěč neobsahuje a ID našich odpovědí se pamatují, takže se agent
nespustí sám sebou. Počet úloh se nehlídá. Pojistka předplatného: když je 5h
relace nebo týden Claude vyčerpaný aspoň z `CLICKUP_AGENT_MAX_USAGE` % (90),
agent odpoví, kdy se limit obnoví, a nespustí se (100 = vypnuto). V ClickUpu agent jen čte úkoly
a přidává odpovědi a komentáře, nic neupravuje ani nemaže.

Nastavení (jednou, na serveru):

```bash
# .env: CLICKUP_API_TOKEN, CLICKUP_TEAM_ID, CLICKUP_AGENT_USERS, CLICKUP_SPACE_PROJECTS
php artisan config:cache
php artisan clickup:napojit            # jen ukáže seznamy, klienty a projekty
php artisan clickup:napojit --ulozit   # přiřadí seznamy klientům se stejným názvem
php artisan clickup:napojit --webhook  # založí webhook, vypíše CLICKUP_WEBHOOK_SECRET do .env
php artisan config:cache
```

## Reklamní grafika (Reklamy → Reklamní grafika)

Formulář v nástrojích → agent na pozadí připraví návrhy reklam jako PNG a ve Figmě.

- **Zadání:** klient (jen s projektem agenta), co propagovat, formáty (předvolby
  1080×1350, 1080×1080, 1080×1920, 1200×628, 1920×600, 300×250 a vlastní rozměr),
  počet návrhů (výchozí 5), styl (web, písma, barvy, inspirace), podklady (logo,
  fotky, až 10 souborů na disk `local`, `storage/app/private/reklamni-grafika`)
  a jestli smí generovat obrázky.
- **Běh:** fronta jako u úlohy z ClickUpu. `AgentJob` se `source = grafika`, služba
  agenta ji odpracuje v projektu klienta (30 min, pojistka předplatného
  `CLICKUP_AGENT_MAX_USAGE`). Na rozdíl od ClickUpu ne přes `claude -p`, ale jako
  obyčejná konverzace v okně tmuxu (`terminal: true` v úloze, okno má `@uloha`),
  aby šla sledovat. Konec pozná háček Stop z `--settings` (zapíše `~/.taveo-ulohy/<id>.stop`,
  odtud `last_assistant_message` jako výsledek). Když agent čeká na příkaz na pozadí,
  ještě není hotovo, ledaže odpověď už obsahuje `[/grafika]` (`konec` v úloze).
  Celé zadání je jen v souboru `~/.taveo-ulohy/<id>.zadani`, Claude dostane v příkazové
  řádce jen úvod s cestou: dřív `pgrep -f "http.server <port>" | xargs kill` (úklid
  serveru náhledu) trefil i Clauda, protože měl celé zadání v argv, a úloha spadla těsně
  před koncem.
- **Po úloze okno zůstává** jako běžná konverzace (`@uloha` se smaže, uspí se po 12 h).
  Dokud žije, služba hlídá háček dál: odpověď s `[/grafika]` (člověk chtěl změnu) pošle
  na web znovu a `GraphicsAgent::finish()` obnoví galerii, i u kola, které skončilo chybou.
  Odpověď bez bloku galerii nechá. Token zadání platí ještě den po doběhnutí (obrázky
  a podklady v pokračování). Probuzená konverzace (po uspání) háček nemá, galerii pak
  obnoví až Upravit návrhy.
- **Terminál v detailu:** blok „Co agent dělá“ = terminál téhle konverzace. Výchozí jen
  ke sledování (vstupenka s `r: 1`, služba pustí jen kolečko myši), **Zasáhnout** ji vymění
  za vstupenku s psaním. Divák má vlastní sezení tmuxu jen s oknem úlohy (`link-window`),
  takže po konci úlohy nevidí jiné konverzace projektu. Po doběhnutí je pod návrhy
  „Pokračovat s agentem“ (rovnou na psaní), uspanou konverzaci jde tamtéž probudit.
  Probouzí se ve své složce (u živého webu `kod/`, ne v kopii), jinak ji `--resume` nenajde. Když do úlohy někdo napíše,
  služba ji ukončí až po odpovědi s `[/grafika]` (odpověď na jeho zprávu ještě není
  výsledek) a limit se od posledního zásahu prodlouží o 15 min. Úloha je vidět i v seznamu
  konverzací projektu jako „Úloha běží“, sama se tam ale neotevře. Výsledek přijde na `/nastroje/api/agent/ulohy/{id}/vysledek`,
  `AgentApiController` ho podle `source` pošle do `GraphicsAgent::finish()`, ne do ClickUpu.
  Ztracené úlohy po 2 h uzavře `clickup:hlidat` (u grafiky bez ClickUpu).
- **Výstup:** agent ukládá do `/projekty/<projekt>/predani/grafika-<id>/` (PNG, `popis.md`,
  zdroje v `zdroj/`). Na konci vrátí blok `[grafika] {"figma": …, "varianty": […]} [/grafika]`,
  web si z něj uloží jména souborů a odkaz na Figmu. Obrázky ukazuje přes stejnou routu
  jako snímky Hotové práce (`agent.snimek`). U projektu bez kódu je složka vidět i v kartě
  Hotové podklady. Když ji tam někdo zahodí, zmizí obrázky i z galerie (Figma zůstane).
- **Figma:** MCP `figma` je v kontejneru připojený pro všechny projekty (`claude mcp add
  --scope user figma https://mcp.figma.com/mcp`), účet Taveo, plán Starter. Zápis jde přes
  `generate_figma_design` (code to canvas) do nového souboru v konceptech účtu. Zachytávací
  skript se v prohlížeči nesmí čekat přes `await` (nevrátí se), pokyn je v zadání úlohy.
  Při vypršení přihlášení k Figmě: v terminálu `/mcp` → figma → Authenticate.
- **Úpravy:** tlačítko Upravit návrhy = nové kolo (`parent_id` na první zadání), agent
  naváže na stejnou konverzaci (`--resume`) a vyjde ze složky předchozího kola. Nové
  návrhy přidá do stejného souboru ve Figmě.
- **Obrázky (Nano Banana):** klíč z Google AI Studia se vkládá v Reklamy → Reklamní grafika
  → Nastavení (`AdGraphicsSettings`, šifrovaně). Agent ho nevidí: obrázek si vyžádá
  `POST /nastroje/api/grafika/{id}/obrazek` (popis, poměr stran, až 3 předlohy) s jednorázovým
  tokenem zadání v hlavičce `X-Grafika-Token`. Token platí, jen dokud úloha běží, a pustí
  jen k podkladům a obrázkům svého zadání. Strop obrázků na zadání je v nastavení (12),
  počítá se i nepovedený pokus. Model jde přepsat (výchozí `gemini-2.5-flash-image`).
  „Ověřit klíč“ se jen zeptá na model, nic neplatí, a **bezplatný tarif nepozná**: Google
  na něm u obrázkových modelů vrací 429 s `limit: 0`. To odhalí „Vyzkoušet obrázek“
  (jeden obrázek, asi 1 Kč) a ukáže česky, že je potřeba zapnout placení (Billing) u projektu
  klíče v Google AI Studiu. Chyba generování se ukládá k zadání (`image_error`) a je vidět
  v detailu i v nastavení, agent ji vidí v odpovědi (curl bez `-f`).

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

Web jinde (FTP, WordPress, Shoptet, Upgates na cizím hostingu): projekt `prazdny`
a přístupy do Nástrojů → **Trezor** ([TREZOR.md](TREZOR.md)). Agent hesla nedostane,
používá je přes `taveo ftp…`, `taveo wp`, `taveo prihlasit`.

## Prohlížeč (oči agenta)

Každá konverzace v kontejneru má headless Chromium přes MCP server
**`prohlizec`** (Playwright MCP). Agent si otevře stránku, udělá snímek, přečte
chyby z konzole a zkusí šířku telefonu, tabletu i počítače. Jak ho má používat,
říká společná paměť agentů `~/.claude/CLAUDE.md` (zdroj
[`infra/agent/CLAUDE.agent.md`](../infra/agent/CLAUDE.agent.md)).

| Kus | Kde |
|---|---|
| Chromium + knihovny + fonty | v obrazu, `/opt/ms-playwright` (Dockerfile) |
| Spouštění prohlížeče | `agent-chromium` ([`agent-chromium.sh`](../infra/agent/agent-chromium.sh)) |
| Registrace pro konverzace | `prohlizec-mcp` z `entrypoint.sh`, uživatelská úroveň v `~/.claude.json` |
| Snímky | `/tmp/agent-prohlizec` v kontejneru |
| Povolení bez ptaní | `mcp__prohlizec` v `managed-settings.json` |

Profil prohlížeče je izolovaný (nic si nepamatuje mezi konverzacemi). Verze
jsou připnuté (`playwright@1.64.1`, `@playwright/mcp@0.0.83`), zvedají se
v Dockerfile a v `prohlizec-bez-roota.sh` zároveň.

**Bez přestavění obrazu:** `bash infra/agent/prohlizec-bez-roota.sh` v kontejneru
stáhne knihovny jako balíčky Ubuntu do `~/.local/share/browser-deps` (bez roota)
a prohlížeč zaregistruje. Tak běží od 23. 10. 2026, než se obraz přestaví.
`.mcp.json` v repu (server `playwright` přes `npx`) je pro lokální vývoj na Macu,
v kontejneru je vypnutý.

## Panel „Co agent dělá“

Mod Claude Code ([`infra/agent/mody/co-agent-dela`](../infra/agent/mody/co-agent-dela/)),
načtený ve všech konverzacích v kontejneru:

- **Panel** vedle chatu: každý krok agenta česky (čte soubor, spouští příkaz,
  prohlížeč otevírá stránku…) s časem a stavem (▶ běží, ✓ hotovo, ✗ chyba),
  nahoře „Teď: …“. Pod tím poslední adresa z prohlížeče a poslední snímek.
  Obrázek vykreslí jen terminál, který to umí (kitty, Ghostty, iTerm2), jinde
  je tam cesta k souboru.
- **Pruh nad vstupním polem** se na dvě minuty objeví, jakmile agent sáhne po
  prohlížeči: „Agent kontroluje web v prohlížeči“ a tlačítko **Kouknout**.
- **`/co-agent-dela`** otevře panel kdykoli.

Načítá ho proměnná `CLAUDE_CODE_PLUGIN_DIRS`: v obrazu z Dockerfile
(`/opt/taveo-agent/mody/co-agent-dela`), do přestavění z `env`
v `~/.claude/settings.json` (kopie v `~/.claude/mody/`). Testy:
`claude plugin test infra/agent/mody/co-agent-dela`.

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
