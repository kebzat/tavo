#!/bin/bash
# Příkaz `taveo` v kontejneru agenta: čte data klientů z nástrojů TAVEO
# a zapisuje koncepty (ranní přehled, návrhy úkolů, rozhodnutí, podklady
# k reportu, návrhy poznatků do mozku), čte archiv klienta a používá trezor
# přístupů (hesla nevidí, práci s nimi dělá web). Nic neodesílá klientovi a nic nemění v reklamách.
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

# Bez JSON hlavičky: soubory, ZIP a nahrávání (multipart).
surove() {
    local metoda=$1 cesta=$2
    shift 2
    curl -sS --fail-with-body --max-time 300 -X "$metoda" -H "Authorization: Bearer $TOKEN" "$API$cesta" "$@"
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
taveo mozek-seznam                      nadpisy všeho v mozku (kontrola duplicit)
taveo mozek-navrh firma|klient|postup "nadpis" [slug] < text.md
                                        návrh poznatku do mozku, schválí ho člověk v nástrojích
taveo mozek-uvod                        mozek klienta pro začátek konverzace (háček SessionStart, projekt podle složky)
taveo archiv <slug> [druh]              archiv klienta: data, strategie, výsledky, marže (seznam s popisem)
taveo archiv-stahnout <id> <složka>     stáhne dokument z archivu (text a soubory) do místní složky
taveo archiv-nahrat <slug> "název" [--druh D] [--k-datu RRRR-MM-DD] [--popis "co v něm je"] [--poznatky] <soubor>… | -
                                        uloží soubory (nebo text ze vstupu: -) do archivu klienta; --poznatky = přečte ho i knihovník
                                        druhy: strategie, data, vysledky, obchod, schuzky, jine
taveo trezor [slug]                     přístupy, které agent smí použít (hesla nedostane nikdy)
taveo ftp <id> [cesta]                  výpis složky na FTP (cesta od složky webu)
taveo ftp-soubor <id> <cesta>           obsah jednoho souboru z FTP
taveo ftp-stahnout <id> <cesta> <složka>
                                        složka z FTP do místní složky (bez nahraných obrázků a cache)
taveo ftp-nahrat <id> "popis" <složka> <soubor>…
                                        nahraje soubory (cesty od složky webu); podle přístupu hned, nebo po schválení člověkem
taveo nahrani <id> <číslo>              stav nahrání (čeká, nahráno, vráceno)
taveo wp <id> GET|POST… <cesta> ['{"json"}']
                                        REST API WordPressu s aplikačním heslem z trezoru
taveo prihlasit <id>                    přihlásí se do administrace a vrátí cookies pro prohlížeč
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
    mozek-seznam)
        volat GET /mozek/seznam
        ;;
    mozek-navrh)
        jq -Rs --arg v "${2:?firma, klient nebo postup}" --arg n "${3:?nadpis}" --arg k "${4:-}" \
            '{poznatky: [{vrstva: $v, nadpis: $n, text: .} + (if $k == "" then {} else {klient: $k} end)]}' \
            | volat POST /mozek/navrhy --data-binary @-
        ;;
    mozek-uvod)
        # Háček SessionStart: projekt podle složky konverzace (/projekty/<projekt>/…).
        # Úlohy z nástrojů (ClickUp, grafika, mozek) mají mozek v zadání (TAVEO_ULOHA).
        # Nikdy nesmí zdržet ani shodit začátek konverzace: chyba = nic.
        [ -z "${TAVEO_ULOHA:-}" ] || exit 0
        projekt=$(printf '%s\n' "${CLAUDE_PROJECT_DIR:-$PWD}" | sed -n 's#^/projekty/\([^/]*\).*#\1#p')
        [ -n "$projekt" ] || exit 0
        curl -sf --max-time 8 -H "Authorization: Bearer $TOKEN" "$API/mozek/uvod" -G --data-urlencode "projekt=$projekt" || true
        exit 0
        ;;
    archiv)
        volat GET "/archiv/${2:?slug klienta}" ${3:+-G --data-urlencode "druh=$3"}
        ;;
    archiv-stahnout)
        cil=${3:?složka, kam stáhnout}
        mkdir -p "$cil"
        dokument=$(surove GET "/mozek/zdroje/${2:?id dokumentu z taveo archiv}/popis") || { echo "$dokument" >&2; exit 1; }
        adresa=$(jq -r '.text // empty' <<<"$dokument")
        [ -z "$adresa" ] || curl -sS --fail-with-body --max-time 300 -H "Authorization: Bearer $TOKEN" -o "$cil/text.md" "$adresa"
        jq -r '.soubory // [] | .[] | [.jmeno, .adresa] | @tsv' <<<"$dokument" | while IFS=$'\t' read -r jmeno adresa; do
            jmeno=$(basename -- "$jmeno")
            curl -sS --fail-with-body --max-time 300 -H "Authorization: Bearer $TOKEN" -o "$cil/${jmeno:-soubor}" "$adresa"
        done
        echo "Staženo do $cil: $(ls -1 "$cil" | tr '\n' ' ')"
        ;;
    archiv-nahrat)
        slug=${2:?slug klienta}
        nazev=${3:?název dokumentu}
        shift 3
        pole=(-F "nazev=$nazev")
        while [ $# -gt 0 ]; do
            case "$1" in
                --druh) pole+=(-F "druh=${2:?druh}"); shift 2 ;;
                --k-datu) pole+=(-F "k_datu=${2:?RRRR-MM-DD}"); shift 2 ;;
                --popis) pole+=(-F "popis=${2:?popis}"); shift 2 ;;
                --poznamka) pole+=(-F "poznamka=${2:?poznámka}"); shift 2 ;;
                --poznatky) pole+=(-F "uceni=1"); shift ;;
                -) pole+=(-F "text=<-"); shift ;;
                *) [ -f "$1" ] || { echo "Soubor $1 neexistuje." >&2; exit 1; }
                   pole+=(-F "soubory[]=@$1"); shift ;;
            esac
        done
        surove POST "/archiv/$slug" "${pole[@]}"
        echo
        ;;
    trezor)
        volat GET /trezor ${2:+-G --data-urlencode "klient=$2"}
        ;;
    ftp)
        volat GET "/trezor/${2:?id přístupu z taveo trezor}/ftp" -G --data-urlencode "cesta=${3:-}"
        ;;
    ftp-soubor)
        surove GET "/trezor/${2:?id přístupu}/ftp/soubor" -G --data-urlencode "cesta=${3:?cesta k souboru}"
        ;;
    ftp-stahnout)
        cil=${4:?složka, kam stáhnout}
        mkdir -p "$cil"
        zip=$(mktemp --suffix=.zip)
        surove GET "/trezor/${2:?id přístupu}/ftp/zip" -G --data-urlencode "cesta=${3:-}" --max-time 3600 -o "$zip"
        unzip -oq "$zip" -d "$cil" && rm -f "$zip"
        echo "Staženo do $cil$( [ -f "$cil/_vynechano.txt" ] && echo ", co se vynechalo: $cil/_vynechano.txt")"
        ;;
    ftp-nahrat)
        id=${2:?id přístupu}
        popis=${3:?popis, co a proč se mění}
        slozka=${4:?místní složka, která odpovídá složce webu}
        shift 4
        [ $# -gt 0 ] || { echo "Uveď soubory (cesty od složky webu)." >&2; exit 1; }
        pole=(-F "popis=$popis")
        for soubor in "$@"; do
            pole+=(-F "soubory[]=@$slozka/$soubor" -F "cesty[]=$soubor")
        done
        surove POST "/trezor/$id/ftp/nahrat" "${pole[@]}"
        echo
        ;;
    nahrani)
        volat GET "/trezor/${2:?id přístupu}/nahrani/${3:?číslo nahrání}"
        ;;
    wp)
        jq -n --arg m "${3:?metoda, třeba GET}" --arg c "${4:?cesta, třeba /wp/v2/plugins}" --argjson d "${5:-null}" \
            '{metoda: $m, cesta: $c} + (if $d == null then {} else {data: $d} end)' \
            | volat POST "/trezor/${2:?id přístupu}/wp" --data-binary @-
        ;;
    prihlasit)
        volat POST "/trezor/${2:?id přístupu}/prihlasit"
        ;;
    *) napoveda ;;
esac
