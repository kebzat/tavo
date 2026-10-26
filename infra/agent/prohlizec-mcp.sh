#!/bin/bash
# Zaregistruje prohlížeč (Playwright MCP, server „prohlizec“) pro všechny konverzace uživatele
# agent, tedy pro všechny projekty v kontejneru. Volá ho entrypoint.sh při
# startu kontejneru. Registraci pokaždé obnoví, ať se změna voleb tady
# propíše po restartu.
#
# - headless a izolovaný profil: žádné okno, žádné uložené přihlášení mezi konverzacemi
# - bez sandboxu Chromia: v kontejneru bez oprávnění (cap_drop ALL,
#   no-new-privileges) nenaběhne. Izolaci dělá kontejner. Schválil Tom 23. 10. 2026.
# - snímky a záznamy do /tmp/agent-prohlizec (panel „Co agent dělá“ odtud bere poslední snímek)
set -euo pipefail

command -v claude >/dev/null 2>&1 || exit 0
claude mcp remove -s user prohlizec >/dev/null 2>&1 || true

claude mcp add -s user prohlizec -- playwright-mcp \
    --headless --isolated --no-sandbox \
    --executable-path "$(command -v agent-chromium)" \
    --output-dir /tmp/agent-prohlizec \
    --viewport-size 1280,860
