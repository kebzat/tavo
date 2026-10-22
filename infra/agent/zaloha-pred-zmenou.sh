#!/bin/bash
# Háček Claude Code (PreToolUse, Bash) v kontejneru taveo-agent.
#
# Zápis do databáze, migrace a mazání souborů na živém webu jedou bez ptaní,
# aby agenta zvládl i marketér. Bezpečnost proto stojí na tom, že jde všechno
# vrátit: těsně před takovým příkazem se uloží snímek souborů a záloha
# databáze (fronta → server, `agent zaloha`). Když se záloha nepovede,
# příkaz se nespustí (exit 2, Claude dostane důvod).
#
# Jedna záloha na projekt za dvě minuty stačí: série příkazů jde vrátit
# do stavu před první z nich.

set -uo pipefail

vstup=$(cat)
prikaz=$(jq -r '.tool_input.command // ""' <<<"$vstup")
cwd=$(jq -r '.cwd // ""' <<<"$vstup")

# Jen příkazy, které mění data nebo mažou.
grep -Eq 'artisan +(tinker|db:seed|settings:|migrate)|(^|[;&|(] *)wp +.*(create|update|delete|set|add|import|search-replace|db +(import|query|reset))|(^|[;&|(] *)rm +' <<<"$prikaz" || exit 0

# Projekt podle složky nebo cesty v příkazu (i serverové /home/web/složka).
slug=""
while IFS=$'\t' read -r s typ zdroj db; do
    [ "$typ" = server ] || continue
    case "$cwd $prikaz" in
        *"/projekty/$s/"*|*"/projekty/$s "*|*"$zdroj"*) slug=$s ;;
    esac
done < /etc/taveo-agent/projekty.tsv
[ -n "$slug" ] || exit 0

znacka=/tmp/taveo-zaloha-$slug
if [ -f "$znacka" ] && [ $(( $(date +%s) - $(stat -c %Y "$znacka") )) -lt 120 ]; then
    exit 0
fi

id=$(cat /proc/sys/kernel/random/uuid)
pozadavek=/fronta/pozadavky/pozadavek-$id.json
vysledek=/fronta/vysledky/vysledek-$id.json
printf '{"akce":"zaloha","projekt":"%s"}' "$slug" > "$pozadavek.tmp" && mv "$pozadavek.tmp" "$pozadavek"

for _ in $(seq 1 240); do
    if [ -f "$vysledek" ]; then
        ok=$(jq -r '.ok' "$vysledek")
        vystup=$(jq -r '.vystup' "$vysledek")
        rm -f "$vysledek"
        if [ "$ok" = true ]; then
            touch "$znacka"
            exit 0
        fi
        echo "Záloha webu $slug před změnou se nepovedla, příkaz jsem nespustil: $vystup" >&2
        exit 2
    fi
    sleep 0.5
done

rm -f "$pozadavek"
echo "Záloha webu $slug před změnou neodpověděla (fronta na serveru neběží?), příkaz jsem nespustil." >&2
exit 2
