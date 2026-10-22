#!/bin/bash
# Příkaz `taveo` v kontejneru agenta: čte data klientů z nástrojů TAVEO
# a zapisuje koncepty (ranní přehled, návrhy úkolů, rozhodnutí, podklady
# k reportu). Nic neodesílá klientovi a nic nemění v reklamách.
# Rozhraní: /nastroje/api/agent (App\Http\Controllers\Agent\AgentApiController).
set -euo pipefail

API=${TAVEO_API_URL:-https://taveo.cz/nastroje/api/agent}
# Token v souboru (jen pro uživatele agent), nebo v prostředí.
TOKEN=${AGENT_API_TOKEN:-$(cat "$HOME/.config/taveo/token" 2>/dev/null || true)}

[ -n "$TOKEN" ] || { echo "Chybí AGENT_API_TOKEN, rozhraní TAVEO není nastavené." >&2; exit 1; }

volat() {
    local metoda=$1 cesta=$2
    shift 2
    curl -sS --fail-with-body --max-time 60 -X "$metoda" \
        -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
        "$API$cesta" "$@"
    echo
}

napoveda() {
    cat <<'TXT'
taveo klienti                           seznam klientů (slug, cíl, upozornění)
taveo klient <slug>                     všechno o klientovi: karta, čísla, upozornění, úkoly
taveo prehled <slug> < prehled.md       uloží dnešní ranní přehled (Markdown ze vstupu)
taveo ukoly <slug> < ukoly.json         návrhy úkolů ke schválení: {"tasks":[{"title":…,"source":…}]}
taveo rozhodnuti <slug> "rozhodnutí" "zdroj" [platí do RRRR-MM-DD]
taveo report <id> < report.json         fakta / příčiny / doporučení ke konceptu reportu
TXT
}

case "${1:-}" in
    klienti) volat GET /klienti ;;
    klient) volat GET "/klienti/${2:?slug klienta}" ;;
    prehled) jq -Rs '{body: .}' | volat POST "/klienti/${2:?slug klienta}/prehled" --data-binary @- ;;
    ukoly) volat POST "/klienti/${2:?slug klienta}/ukoly" --data-binary @- ;;
    rozhodnuti)
        jq -n --arg d "${3:?rozhodnutí}" --arg s "${4:?zdroj}" --arg v "${5:-}" '{decision: $d, source: $s} + (if $v == "" then {} else {valid_until: $v} end)' \
            | volat POST "/klienti/${2:?slug klienta}/rozhodnuti" --data-binary @-
        ;;
    report) volat POST "/reporty/${2:?id reportu}" --data-binary @- ;;
    *) napoveda ;;
esac
