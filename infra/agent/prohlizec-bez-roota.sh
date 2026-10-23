#!/bin/bash
# Prohlížeč pro agenty v běžícím kontejneru bez přestavění obrazu a bez roota.
# Stáhne chybějící knihovny Chromia jako balíčky Ubuntu a rozbalí je do
# ~/.local/share/browser-deps. Domovská složka je svazek na serveru, takže to
# přežije restart. Po přestavění obrazu už potřeba není (prohlížeč je v obrazu).
#
#   bash infra/agent/prohlizec-bez-roota.sh
set -euo pipefail

D="$HOME/.local/share/browser-deps"
APT=(-o "Dir::State::Lists=$D/apt/lists" -o "Dir::Cache=$D/apt/cache" -o Debug::NoLocking=1)
mkdir -p "$D/apt/lists/partial" "$D/apt/cache/archives/partial" "$D/debs" "$D/root" "$HOME/.local/bin"

apt-get "${APT[@]}" update -qq

PKGS="libasound2t64 libatk-bridge2.0-0t64 libatk1.0-0t64 libatspi2.0-0t64 libcairo2 libcups2t64
libdbus-1-3 libdrm2 libgbm1 libglib2.0-0t64 libnspr4 libnss3 libpango-1.0-0 libx11-6 libxcb1
libxcomposite1 libxdamage1 libxext6 libxfixes3 libxkbcommon0 libxrandr2
fonts-liberation fonts-noto-color-emoji fonts-dejavu-core fontconfig"

NEED=()
for p in $(apt-cache "${APT[@]}" depends --recurse --no-recommends --no-suggests --no-conflicts \
        --no-breaks --no-replaces --no-enhances $PKGS 2>/dev/null | grep -v '^ \|<' | sort -u); do
    dpkg -s "$p" >/dev/null 2>&1 || NEED+=("$p")
done
(cd "$D/debs" && apt-get "${APT[@]}" download "${NEED[@]}" -qq)
for f in "$D"/debs/*.deb; do dpkg-deb -x "$f" "$D/root"; done

cat > "$D/fonts.conf" <<EOF
<?xml version="1.0"?>
<!DOCTYPE fontconfig SYSTEM "fonts.dtd">
<fontconfig>
  <dir>/usr/share/fonts</dir>
  <dir>$D/root/usr/share/fonts</dir>
  <cachedir>$HOME/.cache/fontconfig</cachedir>
  <include ignore_missing="yes">$D/root/etc/fonts/conf.d</include>
</fontconfig>
EOF

npx -y playwright@1.64.1 install chromium
npm install -g --prefix "$HOME/.local" @playwright/mcp@0.0.83 --no-audit --no-fund
install -m 755 "$(dirname "$0")/agent-chromium.sh" "$HOME/.local/bin/agent-chromium"
bash "$(dirname "$0")/prohlizec-mcp.sh"

"$HOME/.local/bin/agent-chromium" --headless --no-sandbox --dump-dom 'data:text/html,<p>prohlizec-ok</p>' 2>/dev/null | grep -q prohlizec-ok \
    && echo "Prohlížeč funguje." || { echo "Prohlížeč nenaběhl." >&2; exit 1; }
