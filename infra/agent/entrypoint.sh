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

# Služba pro nástroje (vlákna, terminál). Sezení jde otevřít i příkazem `agent`.
exec node /opt/terminal/server.js
