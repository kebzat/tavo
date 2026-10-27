#!/bin/bash
# Příkaz `taveo` v kontejneru agenta: čte data klientů z nástrojů TAVEO
# a zapisuje koncepty (ranní přehled, návrhy úkolů, rozhodnutí, podklady
# k reportu, návrhy poznatků do mozku), mění stav schválených úkolů, čte archiv klienta a používá trezor
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
                                        s "schvaleno": true rovnou do plánu klienta (jen když to člověk řekl),
                                        měsíc u úkolu "v_planu_na": "RRRR-MM" (bez něj tento měsíc)
taveo ukoly-seznam <slug>               úkoly s id: návrhy, otevřené a hotové za tento měsíc
taveo ukol-upravit <id> [--nazev "…"] [--popis "…"] [--mesic RRRR-MM] [--termin RRRR-MM-DD] [--schvalit]
                                        přejmenuje úkol, změní popis pro klienta, přesune ho na jiný měsíc,
                                        změní termín, schválí návrh (na pokyn člověka)
taveo ukol-stav <id> hotovo|rozpracovano|ceka|v-planu ["poznámka"] ["na koho se čeká"]
                                        změní stav úkolu; hotový klient uvidí v „Co jsme udělali“ (poznámka jen interně)
taveo rozhodnuti <slug> "rozhodnutí" "zdroj" [platí do RRRR-MM-DD]
taveo report <id> fakta|fungovalo|nefungovalo|priciny|doporuceni <<'EOF' … EOF
                                        jedna část konceptu reportu v Markdownu (jen prázdná pole se doplní)
taveo mozek "co hledám" [slug] [--skill S]
                                        hledání v mozku TAVEO (schválené poznatky, JSON); se skillem i u jiných klientů
taveo mozek-seznam                      nadpisy všeho v mozku (kontrola duplicit)
taveo mozek-navrh firma|klient|postup "nadpis" [slug] [--skill S] < text.md
                                        návrh poznatku do mozku, schválí ho člověk v nástrojích
taveo mozek-uvod                        mozek klienta pro začátek konverzace (háček SessionStart, projekt podle složky)
taveo archiv <slug|firma> [druh]        archiv klienta (firma = archiv TAVEO): data, strategie, výsledky, marže
taveo archiv-stahnout <id> <složka>     stáhne dokument z archivu (text a soubory) do místní složky
taveo archiv-nahrat <slug> "název" [--druh D] [--k-datu RRRR-MM-DD] [--popis "co v něm je"] [--poznatky] [--skill S] <soubor>… | -
                                        uloží soubory (nebo text ze vstupu: -) do archivu klienta; --poznatky = přečte ho i knihovník
                                        druhy: strategie, data, vysledky, obchod, schuzky, jine
taveo skilly                            skilly TAVEO (specialisté): kdy který použít, jeho projekt
taveo skill <skill> [slug]              pokyny, know-how, zkušenosti z klientů a archiv skillu (se slugem i ke klientovi)
taveo skill-archiv <skill> [druh]       archiv skillu (case studies, výsledky, vzory)
taveo skill-nahrat <skill> "název" [--druh D] [--popis "…"] [--poznatky] <soubor>… | -
                                        uloží podklady do archivu skillu; --poznatky = knihovník z nich navrhne know-how
taveo skilly-sync                       SKILL.md skillů do ~/.claude/skills (dělá i mozek-uvod)
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
taveo zaslat <id> GET|POST <cesta> ['{"json"}']
                                        API Zaslat.cz s klíčem z trezoru, jen čtení (zásilky, sledování)
taveo prihlasit <id>                    přihlásí se do administrace a vrátí cookies pro prohlížeč
TXT
}

case "${1:-}" in
    klienti) volat GET /klienti ;;
    klient) volat GET "/klienti/${2:?slug klienta}" ;;
    prehled) jq -Rs '{body: .}' | volat POST "/klienti/${2:?slug klienta}/prehled" --data-binary @- ;;
    ukoly) volat POST "/klienti/${2:?slug klienta}/ukoly" --data-binary @- ;;
    ukoly-seznam) volat GET "/klienti/${2:?slug klienta}/ukoly" ;;
    ukol-stav)
        case "${3:-}" in
            hotovo) stav=done ;;
            rozpracovano) stav=in_progress ;;
            ceka) stav=waiting ;;
            v-planu) stav=planned ;;
            *) echo "Použití: taveo ukol-stav <id> hotovo|rozpracovano|ceka|v-planu [\"poznámka\"] [\"na koho se čeká\"]" >&2; exit 1 ;;
        esac
        jq -n --arg s "$stav" --arg p "${4:-}" --arg c "${5:-}" \
            '{stav: $s} + (if $p == "" then {} else {poznamka: $p} end) + (if $c == "" then {} else {ceka_na: $c} end)' \
            | volat POST "/ukoly/${2:?id úkolu z taveo ukoly-seznam}/stav" --data-binary @-
        ;;
    ukol-upravit)
        id=${2:?id úkolu z taveo ukoly-seznam}
        shift 2
        zmeny='{}'
        while [ $# -gt 0 ]; do
            case "$1" in
                --nazev) zmeny=$(jq -c --arg v "${2:?název}" '. + {nazev: $v}' <<<"$zmeny"); shift 2 ;;
                --popis) zmeny=$(jq -c --arg v "${2?popis}" '. + {popis: $v}' <<<"$zmeny"); shift 2 ;;
                --mesic) zmeny=$(jq -c --arg m "${2:?měsíc RRRR-MM}" '. + {v_planu_na: $m}' <<<"$zmeny"); shift 2 ;;
                --termin) zmeny=$(jq -c --arg t "${2:?termín RRRR-MM-DD}" '. + {termin: $t}' <<<"$zmeny"); shift 2 ;;
                --schvalit) zmeny=$(jq -c '. + {schvalit: true}' <<<"$zmeny"); shift ;;
                *) echo "Použití: taveo ukol-upravit <id> [--nazev \"…\"] [--popis \"…\"] [--mesic RRRR-MM] [--termin RRRR-MM-DD] [--schvalit]" >&2; exit 1 ;;
            esac
        done
        volat POST "/ukoly/$id" --data-binary "$zmeny"
        ;;
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
        dotaz=(-G --data-urlencode "q=${2:?co hledáte}")
        shift 2
        while [ $# -gt 0 ]; do
            case "$1" in
                --skill) dotaz+=(--data-urlencode "skill=${2:?skill}"); shift 2 ;;
                *) dotaz+=(--data-urlencode "klient=$1"); shift ;;
            esac
        done
        volat GET /mozek "${dotaz[@]}"
        ;;
    mozek-seznam)
        volat GET /mozek/seznam
        ;;
    mozek-navrh)
        vrstva=${2:?firma, klient nebo postup}
        nadpis=${3:?nadpis}
        shift 3
        klient="" skill=""
        while [ $# -gt 0 ]; do
            case "$1" in
                --skill) skill=${2:?skill}; shift 2 ;;
                *) klient=$1; shift ;;
            esac
        done
        jq -Rs --arg v "$vrstva" --arg n "$nadpis" --arg k "$klient" --arg s "$skill" \
            '{poznatky: [{vrstva: $v, nadpis: $n, text: .} + (if $k == "" then {} else {klient: $k} end) + (if $s == "" then {} else {skill: $s} end)]}' \
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
        # Skilly přidané v nástrojích budou jako /slug v příští konverzaci. Na pozadí, nezdrží.
        ( "$0" skilly-sync >/dev/null 2>&1 & )
        exit 0
        ;;
    skilly)
        volat GET /skilly | jq '[.[] | del(.soubor)]'
        ;;
    skill)
        surove GET "/skilly/${2:?skill, třeba meta-ads (seznam: taveo skilly)}" ${3:+-G --data-urlencode "klient=$3"}
        echo
        ;;
    skill-archiv)
        volat GET "/skilly/${2:?skill}/archiv" ${3:+-G --data-urlencode "druh=$3"}
        ;;
    skilly-sync)
        # SKILL.md každého skillu z nástrojů do ~/.claude/skills/<slug>/. Ručně psané (bez značky) nepřepíše.
        seznam=$(curl -sf --max-time 8 -H "Authorization: Bearer $TOKEN" "$API/skilly") || exit 0
        slozka="${CLAUDE_CONFIG_DIR:-$HOME/.claude}/skills"
        jq -c '.[] | select(.soubor)' <<<"$seznam" | while read -r polozka; do
            slug=$(jq -r .slug <<<"$polozka")
            [[ "$slug" =~ ^[a-z0-9][a-z0-9-]*$ ]] || continue
            soubor="$slozka/$slug/SKILL.md"
            if [ -f "$soubor" ] && ! grep -qF '<!-- taveo skilly-sync -->' "$soubor"; then continue; fi
            mkdir -p "$slozka/$slug"
            jq -r .soubor <<<"$polozka" >"$soubor.novy" && mv "$soubor.novy" "$soubor"
        done
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
    archiv-nahrat|skill-nahrat)
        [ "$1" = skill-nahrat ] && cesta="/skilly/${2:?skill}/archiv" || cesta="/archiv/${2:?slug klienta}"
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
                --skill) pole+=(-F "skill=${2:?skill}"); shift 2 ;;
                -) pole+=(-F "text=<-"); shift ;;
                *) [ -f "$1" ] || { echo "Soubor $1 neexistuje." >&2; exit 1; }
                   pole+=(-F "soubory[]=@$1"); shift ;;
            esac
        done
        surove POST "$cesta" "${pole[@]}"
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
    zaslat)
        jq -n --arg m "${3:?metoda, třeba GET}" --arg c "${4:?cesta, třeba /shipments/list?tracking}" --argjson d "${5:-null}" \
            '{metoda: $m, cesta: $c} + (if $d == null then {} else {data: $d} end)' \
            | volat POST "/trezor/${2:?id přístupu}/zaslat" --data-binary @-
        ;;
    prihlasit)
        volat POST "/trezor/${2:?id přístupu}/prihlasit"
        ;;
    *) napoveda ;;
esac
