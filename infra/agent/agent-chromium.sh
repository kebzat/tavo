#!/bin/sh
# Chromium pro agenty (Playwright MCP): kontrola vzhledu, responzivity a chyb
# v konzoli. V obrazu je prohlížeč v /opt/ms-playwright (viz Dockerfile).
#
# Záloha pro kontejner postavený před přidáním prohlížeče: knihovny a fonty
# rozbalené bez roota do ~/.local/share/browser-deps (infra/agent/prohlizec-bez-roota.sh)
# a prohlížeč z ~/.cache/ms-playwright.
if [ -x /opt/ms-playwright/chromium/chrome ]; then
    exec /opt/ms-playwright/chromium/chrome "$@"
fi

D="$HOME/.local/share/browser-deps"
export LD_LIBRARY_PATH="$D/root/usr/lib/x86_64-linux-gnu:$D/root/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
[ -f "$D/fonts.conf" ] && export FONTCONFIG_FILE="$D/fonts.conf"

CHROME=$(ls -d "$HOME"/.cache/ms-playwright/chromium-*/chrome-linux64/chrome 2>/dev/null | sort -V | tail -1)
if [ -z "$CHROME" ]; then
    echo "Prohlížeč není nainstalovaný. Viz docs/AGENT.md, oddíl Prohlížeč." >&2
    exit 1
fi
exec "$CHROME" "$@"
