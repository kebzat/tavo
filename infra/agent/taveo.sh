#!/bin/bash
# Příkaz `taveo` v kontejneru agenta: čte data klientů z nástrojů TAVEO
# a zapisuje koncepty (ranní přehled, návrhy úkolů, rozhodnutí, podklady
# k reportu, návrhy poznatků do mozku). Nic neodesílá klientovi a nic nemění v reklamách.
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
taveo report <id> fakta|fungovalo|nefungovalo|priciny|doporuceni <<'EOF' … EOF
                                        jedna část konceptu reportu v Markdownu (jen prázdná pole se doplní)
taveo mozek "co hledám" [slug]          hledání v mozku TAVEO (schválené poznatky, JSON)
taveo mozek-navrh firma|klient|postup "nadpis" [slug] < text.md
                                        návrh poznatku do mozku, schválí ho člověk v nástrojích
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
    report)
        pole=""
        [ "${3:-}" = fakta ] && pole=facts
        [ "${3:-}" = priciny ] && pole=causes
        [ "${3:-}" = doporuceni ] && pole=recommendations
        [ "${3:-}" = fungovalo ] && pole=worked
        [ "${3:-}" = nefungovalo ] && pole=not_worked
        [ -n "$pole" ] || { echo "Použití: taveo report <id> fakta|fungovalo|nefungovalo|priciny|doporuceni <<'EOF' … EOF" >&2; exit 1; }
        jq -Rs --arg pole "$pole" '{($pole): .}' | volat POST "/reporty/${2:?id reportu}" --data-binary @-
        ;;
    mozek)
        volat GET /mozek -G --data-urlencode "q=${2:?co hledáte}" ${3:+--data-urlencode "klient=$3"}
        ;;
    mozek-navrh)
        jq -Rs --arg v "${2:?firma, klient nebo postup}" --arg n "${3:?nadpis}" --arg k "${4:-}" \
            '{poznatky: [{vrstva: $v, nadpis: $n, text: .} + (if $k == "" then {} else {klient: $k} end)]}' \
            | volat POST /mozek/navrhy --data-binary @-
        ;;
    *) napoveda ;;
esac
