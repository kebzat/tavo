#!/bin/bash
# Claude Code se instaluje do domovské složky (svazek na serveru), ne do obrazu.
# Tak se sám aktualizuje a přestavění obrazu nesmaže přihlášení.
set -e

if ! command -v claude >/dev/null 2>&1; then
    curl -fsSL https://claude.ai/install.sh | bash
fi

if [ ! -f "$HOME/.gitconfig" ]; then
    git config --global user.name "Claude (TAVEO agent)"
    git config --global user.email "agent@taveo.cz"
    git config --global init.defaultBranch main
fi

# Prohlížeč pro všechny konverzace (Playwright MCP) a společná paměť agentů
# s návodem, jak ho používat. Paměť se jen založí, ruční úpravy nepřepíše.
# Selhání nesmí shodit kontejner (viz `set -e`).
prohlizec-mcp || echo "Prohlížeč pro agenty se nepodařilo zaregistrovat."
if [ ! -f "$HOME/.claude/CLAUDE.md" ]; then
    mkdir -p "$HOME/.claude" && cp /etc/taveo-agent/CLAUDE.agent.md "$HOME/.claude/CLAUDE.md" || true
fi

# Služba pro nástroje (konverzace, terminál). Běží ve smyčce: novou verzi
# stačí nahrát do /opt/terminal a službu ukončit (`agent sluzba`). Konverzace
# v tmuxu běží mimo ni, takže aktualizace služby je neukončí.
# Pozor na `set -e` nahoře: ukončená služba vrací chybový kód a bez `|| …`
# by skončil celý kontejner i s konverzacemi.
while true; do
    node /opt/terminal/server.js || echo "Služba agenta skončila (kód $?), za 2 s ji spouštím znovu."
    sleep 2
done
