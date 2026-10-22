# Agent na serveru (Claude Code)

Claude Code běží na serveru TAVEO (88.198.31.29) v Docker kontejneru
`taveo-agent`. Každý projekt klienta má vlastní složku a vlastní vlákna,
která běží dál i po zavření terminálu. Soubory instalace jsou v
[`infra/agent/`](../infra/agent/), na web se nenasazují.

## Každodenní práce

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
| Spustí ničivý příkaz | `/etc/claude-code/managed-settings.json` v kontejneru: zákaz `migrate:fresh`, `db:wipe`, `rm -rf`, force push, `ssh`; na push, migrace a mazání se ptá. Agent soubor nepřepíše. Režim „bez ptaní“ je vypnutý. Pravidla jsou druhá vrstva, hlavní je kontejner. |
| Vytíží server | Strop 4 jádra, 8 GB RAM. |

Zálohy databází: `/srv/agent/zalohy/<projekt>/` (posledních 20, jen root).
Snímky: `/srv/agent/snimky/<projekt>.git`. Log přeposílání: `/var/log/taveo-agent.log`.

## Úprava instalace

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
