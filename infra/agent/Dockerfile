# Kontejner, ve kterém běží Claude Code nad projekty klientů.
#
# Vidí jen /projekty (pracovní složky) a /home/agent (přihlášení, klíče).
# Ostatní weby na serveru, poštu ani databáze nevidí. Pravidla v
# /etc/claude-code/managed-settings.json patří rootovi, agent je nepřepíše.

FROM ubuntu:24.04

ARG AGENT_UID=2100

ENV DEBIAN_FRONTEND=noninteractive \
    LANG=C.UTF-8 \
    TZ=Europe/Prague

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg software-properties-common tini \
        git tmux less nano rsync ripgrep jq unzip openssh-client locales tzdata ncurses-term \
        build-essential python3 \
    && add-apt-repository -y ppa:ondrej/php \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
        php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd \
        php8.4-intl php8.4-bcmath php8.4-mysql php8.4-sqlite3 \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

# Uživatel se stejným UID jako `agent` na serveru, ať sedí práva (ACL)
# u složek webů připojených zvenku. Ubuntu 24.04 má v obrazu uživatele
# `ubuntu` s UID 1000, ten nevadí.
RUN useradd --uid ${AGENT_UID} --create-home --home-dir /home/agent --shell /bin/bash agent

# Služba pro nástroje TAVEO: vlákna a terminál v prohlížeči (node-pty se překládá).
COPY terminal/package.json /opt/terminal/package.json
RUN cd /opt/terminal && npm install --omit=dev --no-audit --no-fund
COPY terminal/server.js /opt/terminal/server.js

COPY managed-settings.json /etc/claude-code/managed-settings.json
COPY tmux.conf /etc/tmux.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 644 /etc/claude-code/managed-settings.json /etc/tmux.conf \
    && chmod 755 /usr/local/bin/entrypoint.sh

USER agent
WORKDIR /projekty
ENV PATH=/home/agent/.local/bin:$PATH

ENTRYPOINT ["/usr/bin/tini", "--", "/usr/local/bin/entrypoint.sh"]
