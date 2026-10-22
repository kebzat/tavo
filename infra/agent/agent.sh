#!/bin/bash
# Příkaz `agent` na serveru: přidává projekty a otevírá vlákna s Claudem.
# Běží jako root na serveru, Claude sám běží v kontejneru taveo-agent.
# Podrobnosti v docs/AGENT.md.
set -euo pipefail
export TZ=Europe/Prague

ROOT=/srv/agent
PROJEKTY=$ROOT/projekty
SNIMKY=$ROOT/snimky
ZALOHY=$ROOT/zalohy
SEZNAM=$ROOT/projekty.tsv
KONTEJNER=taveo-agent
AGENT_UID=2100

chyba() { echo "Chyba: $*" >&2; exit 1; }
v_kontejneru() { docker exec -u agent "$KONTEJNER" "$@"; }

zaznam() { awk -F'\t' -v s="$1" '$1 == s' "$SEZNAM" 2>/dev/null; }
typ_projektu() { zaznam "$1" | cut -f2; }

overit_nazev() {
    [[ "$1" =~ ^[a-z0-9][a-z0-9-]{1,40}$ ]] || chyba "název projektu jen malá písmena, číslice a pomlčky (např. grillnor)"
}

napoveda() {
    cat <<'TXT'
Použití:
  agent                         seznam projektů
  agent <projekt>               otevře vlákna projektu (Ctrl+b c nové, Ctrl+b d odejít)
  agent pridat <projekt> git <adresa repozitáře>
  agent pridat <projekt> server <složka webu na tomto serveru> [databáze]
  agent pridat <projekt> prazdny      (Shoptet, Upgates, marketing: bez kódu)
  agent zaloha <projekt>        snímek souborů a záloha databáze hned teď
  agent vratit <projekt>        ukáže snímky a jak se vrátit
  agent odebrat <projekt>       agent ztratí přístup, snímky a zálohy zůstanou
  agent prihlasit               přihlášení Claude Code (jednou)
TXT
}

seznam() {
    if [ ! -s "$SEZNAM" ]; then echo "Zatím žádné projekty."; echo; napoveda; return; fi
    printf "%-22s %-9s %s\n" PROJEKT TYP ZDROJ
    while IFS=$'\t' read -r slug typ zdroj db; do
        stav=""; v_kontejneru tmux has-session -t "$slug" 2>/dev/null && stav=" (běží)"
        printf "%-22s %-9s %s%s\n" "$slug" "$typ" "$zdroj" "$stav"
    done < "$SEZNAM"
}

pamet() {
    # Paměť projektu leží o složku výš než kód. Claude ji načte (CLAUDE.md
    # z nadřazených složek), na web se ale nikdy nedostane.
    local slug=$1 typ=$2 zdroj=$3
    local soubor=$PROJEKTY/$slug/CLAUDE.md
    [ -f "$soubor" ] && return
    sed -e "s|{{PROJEKT}}|$slug|g" -e "s|{{TYP}}|$typ|g" -e "s|{{ZDROJ}}|$zdroj|g" \
        "$ROOT/docker/CLAUDE.projekt.md" > "$soubor"
    chown agent:agent "$soubor"
}

pridat() {
    local slug=${1:-} typ=${2:-} zdroj=${3:-} db=${4:-}
    overit_nazev "$slug"
    [ -z "$(zaznam "$slug")" ] || chyba "projekt $slug už existuje"
    install -d -o agent -g agent "$PROJEKTY/$slug"

    case "$typ" in
        git)
            [ -n "$zdroj" ] || chyba "chybí adresa repozitáře"
            # Dva klíče. Agent v kontejneru má jen čtení. Zápis má klíč na serveru,
            # kterým `agent preposlat` posílá na GitHub jen větve agent/…
            # Do main se tak agent nedostane, ani kdyby chtěl.
            local klic=/home/agent/.ssh/$slug
            v_kontejneru bash -c "install -d -m 700 ~/.ssh
                [ -f $klic ] || ssh-keygen -q -t ed25519 -N '' -f $klic -C taveo-agent-$slug-cteni
                grep -q 'Host github-$slug' ~/.ssh/config 2>/dev/null || printf 'Host github-%s\n  HostName github.com\n  User git\n  IdentityFile %s\n  IdentitiesOnly yes\n' $slug $klic >> ~/.ssh/config
                grep -q github.com ~/.ssh/known_hosts 2>/dev/null || ssh-keyscan -t ed25519 github.com >> ~/.ssh/known_hosts 2>/dev/null"
            local zapis=/root/.ssh/agent-$slug
            [ -f "$zapis" ] || ssh-keygen -q -t ed25519 -N '' -f "$zapis" -C "taveo-agent-$slug-zapis"
            local adresa=${zdroj/git@github.com:/github-$slug:}
            adresa=${adresa/https:\/\/github.com\//github-$slug:}
            if ! v_kontejneru git clone -q "$adresa" "/projekty/$slug/kod" 2>/dev/null; then
                echo "Repozitář zatím nejde stáhnout. Na GitHubu přidejte dva klíče:"
                echo "Repozitář → Settings → Deploy keys → Add deploy key"
                echo
                echo "1) čtení pro agenta, BEZ Allow write access:"
                v_kontejneru cat "$klic.pub"
                echo
                echo "2) odesílání větví agent/…, S Allow write access:"
                cat "$zapis.pub"
                echo
                echo "Pak spusťte znovu: agent pridat $slug git $zdroj"
                rmdir "$PROJEKTY/$slug" 2>/dev/null || true
                exit 1
            fi
            # Schránka na serveru. Agent do ní pushuje místo na GitHub.
            v_kontejneru git init -q --bare "/projekty/$slug/odeslat.git"
            v_kontejneru git -C "/projekty/$slug/kod" remote set-url --push origin "/projekty/$slug/odeslat.git"
            ;;
        server)
            [ -d "$zdroj" ] || chyba "složka $zdroj neexistuje"
            case "$zdroj" in /home/*/*) ;; *) chyba "jde připojit jen složku webu v /home/<web>/…" ;; esac
            local vlastnik; vlastnik=$(stat -c %U "$zdroj")
            # Přístup jen k téhle složce. Výchozí ACL zajistí, že soubory od agenta
            # může dál číst a měnit i uživatel webu, pod kterým běží PHP.
            setfacl -R -m "u:agent:rwX" "$zdroj"
            find "$zdroj" -type d -exec setfacl -m "d:u:agent:rwX,d:u:$vlastnik:rwX" {} +
            install -d -o agent -g agent "$PROJEKTY/$slug/kod"
            mount --bind "$zdroj" "$PROJEKTY/$slug/kod"
            grep -q " $PROJEKTY/$slug/kod " /etc/fstab || echo "$zdroj $PROJEKTY/$slug/kod none bind,nofail 0 0" >> /etc/fstab
            # Snímky v gitu mimo složku webu: .git ve webu by šel stáhnout z internetu.
            local g=$SNIMKY/$slug.git
            git -c safe.directory='*' --git-dir="$g" init -q -b main
            git --git-dir="$g" config core.bare false
            cat "$ROOT/docker/snimky-exclude" > "$g/info/exclude"
            snimek "$slug" "Výchozí stav při připojení"
            [ -z "$db" ] || zalohovat_db "$slug" "$db"
            ;;
        prazdny)
            install -d -o agent -g agent "$PROJEKTY/$slug/kod"
            v_kontejneru git -C "/projekty/$slug/kod" init -q
            ;;
        *) napoveda; exit 1 ;;
    esac

    printf "%s\t%s\t%s\t%s\n" "$slug" "$typ" "$zdroj" "$db" >> "$SEZNAM"
    pamet "$slug" "$typ" "$zdroj"
    echo "Projekt $slug je připravený. Otevřete ho: agent $slug"
}

snimek() {
    local slug=$1 zprava=$2 g=$SNIMKY/$1.git
    [ -d "$g" ] || return 0
    local git=(git -c safe.directory='*' -c user.name="Snímek" -c user.email="agent@taveo.cz" --git-dir="$g" --work-tree="$PROJEKTY/$slug/kod")
    "${git[@]}" add -A
    "${git[@]}" diff --cached --quiet 2>/dev/null || "${git[@]}" commit -q -m "$zprava ($(date '+%-d. %-m. %Y %H:%M'))"
    chown -R agent:agent "$g"
}

zalohovat_db() {
    local slug=$1 db=$2
    install -d -m 700 "$ZALOHY/$slug"
    local soubor=$ZALOHY/$slug/$(date +%F-%H%M%S).sql.gz
    # Údaje roota výslovně: systemd (fronta z nástrojů) nemá HOME=/root.
    if ! ( umask 077; set -o pipefail; mysqldump --defaults-extra-file=/root/.my.cnf --single-transaction --quick "$db" | gzip > "$soubor" ); then
        rm -f "$soubor"
        chyba "záloha databáze $db se nepovedla"
    fi
    ls -1t "$ZALOHY/$slug"/*.sql.gz | tail -n +21 | xargs -r rm --
    echo "Záloha databáze: $soubor"
}

zaloha() {
    local slug=$1; [ -n "$(zaznam "$slug")" ] || chyba "projekt $slug neexistuje"
    snimek "$slug" "Ruční snímek"
    local db; db=$(zaznam "$slug" | cut -f4)
    [ -z "$db" ] || zalohovat_db "$slug" "$db"
}

otevrit() {
    local slug=$1; [ -n "$(zaznam "$slug")" ] || chyba "projekt $slug neexistuje (agent pro seznam)"
    local typ; typ=$(typ_projektu "$slug")
    local env=()
    if [ "$typ" = server ]; then
        # Před každým otevřením snímek a záloha: cokoli agent udělá, jde vrátit.
        if ! v_kontejneru tmux has-session -t "$slug" 2>/dev/null; then
            snimek "$slug" "Před sezením"
            local db; db=$(zaznam "$slug" | cut -f4)
            [ -z "$db" ] || zalohovat_db "$slug" "$db"
        fi
        env=(-e "GIT_DIR=/snimky/$slug.git" -e "GIT_WORK_TREE=/projekty/$slug/kod")
    fi
    exec docker exec -it -u agent "$KONTEJNER" \
        tmux new-session -A -s "$slug" -c "/projekty/$slug/kod" "${env[@]}" "bash -lc 'claude; exec bash'"
}

preposlat() {
    # Spouští cron každou minutu. Ze schránky každého git projektu pošle na
    # GitHub jen větve agent/… Jiné větve (main) tímhle refspecem projít nemůžou.
    while IFS=$'\t' read -r slug typ zdroj db; do
        [ "$typ" = git ] || continue
        local schranka=$PROJEKTY/$slug/odeslat.git stav=$ROOT/.preposlano-$slug
        [ -d "$schranka" ] || continue
        local nyni; nyni=$(git -c safe.directory='*' --git-dir="$schranka" for-each-ref --format='%(objectname) %(refname)' 'refs/heads/agent/')
        [ -n "$nyni" ] || continue
        [ "$nyni" != "$(cat "$stav" 2>/dev/null)" ] || continue
        if GIT_SSH_COMMAND="ssh -i /root/.ssh/agent-$slug -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new" \
            git -c safe.directory='*' --git-dir="$schranka" push -q --force "$zdroj" 'refs/heads/agent/*:refs/heads/agent/*'; then
            echo "$nyni" > "$stav"
            echo "$(date '+%F %T') $slug: odesláno $(echo "$nyni" | wc -l) větví"
        else
            echo "$(date '+%F %T') $slug: odeslání selhalo" >&2
        fi
    done < "$SEZNAM"
}

vratit() {
    local slug=$1 g=$SNIMKY/$1.git
    [ -d "$g" ] || chyba "projekt $slug nemá snímky (git projekt se vrací přes git na GitHubu)"
    git -c safe.directory='*' --git-dir="$g" log --format='%h  %s' -15
    cat <<TXT

Co se od snímku změnilo:   git --git-dir=$g --work-tree=$PROJEKTY/$slug/kod diff <kód> --stat
Vrátit soubory na snímek:  git --git-dir=$g --work-tree=$PROJEKTY/$slug/kod checkout <kód> -- .
Zálohy databáze:           ls $ZALOHY/$slug
TXT
}

odebrat() {
    local slug=$1; [ -n "$(zaznam "$slug")" ] || chyba "projekt $slug neexistuje"
    local typ zdroj; typ=$(typ_projektu "$slug"); zdroj=$(zaznam "$slug" | cut -f3)
    v_kontejneru tmux kill-session -t "$slug" 2>/dev/null || true
    if [ "$typ" = server ]; then
        umount "$PROJEKTY/$slug/kod" || true
        sed -i "\| $PROJEKTY/$slug/kod |d" /etc/fstab
        setfacl -R -x u:agent "$zdroj" 2>/dev/null || true
        find "$zdroj" -type d -exec setfacl -x d:u:agent {} + 2>/dev/null || true
        rmdir "$PROJEKTY/$slug/kod" 2>/dev/null || true
    fi
    install -d -m 700 "$ROOT/archiv"
    mv "$PROJEKTY/$slug" "$ROOT/archiv/$slug-$(date +%F-%H%M%S)"
    # Přepsat obsah, ne soubor: kontejner má seznam připojený a mv by ho odpojilo.
    awk -F'\t' -v s="$slug" '$1 != s' "$SEZNAM" > "$SEZNAM.tmp" && cat "$SEZNAM.tmp" > "$SEZNAM" && rm "$SEZNAM.tmp"
    echo "Projekt $slug odebrán. Snímky: $SNIMKY/$slug.git, zálohy: $ZALOHY/$slug, paměť a kód: $ROOT/archiv/"
}

[ "$(id -u)" = 0 ] || chyba "spouštějte jako root"

case "${1:-}" in
    "") seznam ;;
    -h|--help|napoveda) napoveda ;;
    pridat) shift; pridat "$@" ;;
    zaloha) zaloha "${2:?název projektu}" ;;
    vratit) vratit "${2:?název projektu}" ;;
    odebrat) odebrat "${2:?název projektu}" ;;
    preposlat) preposlat ;;
    prihlasit) exec docker exec -it -u agent "$KONTEJNER" bash -lc claude ;;
    *) otevrit "$1" ;;
esac
