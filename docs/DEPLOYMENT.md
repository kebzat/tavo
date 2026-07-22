# Nasazení

Model: **push do `main` → CI → automatický deploy přes SSH na VPS.**

## 1. GitHub repozitář

```bash
# jednorázově
gh repo create tavo-web --private --source=. --remote=origin --push
# nebo repo založit ručně na github.com a pak:
git remote add origin git@github.com:<uzivatel>/tavo-web.git
git push -u origin main
```

## 2. Příprava serveru

Předpoklad: Ubuntu 24.04, doména míří na server.

```bash
# Balíčky
sudo apt update
sudo apt install -y nginx mysql-server git unzip \
  php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Databáze
sudo mysql -e "CREATE DATABASE tavo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'tavo'@'localhost' IDENTIFIED BY 'SILNE_HESLO';"
sudo mysql -e "GRANT ALL ON tavo.* TO 'tavo'@'localhost'; FLUSH PRIVILEGES;"
```

První nasazení ručně:

```bash
sudo mkdir -p /var/www/tavo && sudo chown -R $USER:www-data /var/www/tavo
git clone git@github.com:<uzivatel>/tavo-web.git /var/www/tavo
cd /var/www/tavo

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
# v .env nastavit: APP_ENV=production, APP_DEBUG=false, APP_URL=https://tavo.cz,
#                  DB_USERNAME=tavo, DB_PASSWORD=…, MAIL_* (SMTP)

php artisan migrate --force --seed
php artisan storage:link
php artisan optimize

sudo chown -R www-data:www-data storage bootstrap/cache
```

### nginx

```nginx
server {
    listen 80;
    server_name tavo.cz www.tavo.cz;
    root /var/www/tavo/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    # Statické assety s dlouhou cache — Vite je verzuje hashem v názvu
    location ~* \.(css|js|woff2?|svg|png|jpe?g|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/tavo /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d tavo.cz -d www.tavo.cz
```

### Cron (plánované úlohy Laravelu)

```
* * * * * cd /var/www/tavo && php artisan schedule:run >> /dev/null 2>&1
```

## 3. Klíč pro deploy

Na serveru:

```bash
ssh-keygen -t ed25519 -C "github-deploy" -f ~/.ssh/github_deploy -N ""
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys
cat ~/.ssh/github_deploy       # tento privátní klíč vložíte do GitHub Secrets
```

Ve `Settings → Secrets and variables → Actions` nastavte:

| Secret | Hodnota |
|---|---|
| `SSH_HOST` | IP nebo doména serveru |
| `SSH_USER` | uživatel, pod kterým se nasazuje |
| `SSH_KEY` | obsah privátního klíče `~/.ssh/github_deploy` |
| `SSH_PORT` | volitelné, výchozí 22 |
| `DEPLOY_PATH` | `/var/www/tavo` |
| `PRODUCTION_URL` | `https://tavo.cz` |

Server musí mít přístup ke GitHubu (deploy key repozitáře nebo veřejný repozitář).

## 4. Jak deploy probíhá

`.github/workflows/ci.yml` na každý push a PR spustí Pint a testy.
`.github/workflows/deploy.yml` se pustí **až po zeleném CI na `main`** a přes SSH provede:

```
php artisan down → git reset --hard origin/main → composer install --no-dev
→ npm ci && npm run build → migrate --force → optimize → queue:restart → php artisan up
```

Nakonec ověří, že produkce vrací HTTP 200 — pokud ne, běh skončí červeně.

Deploy jde spustit i ručně: **Actions → Deploy → Run workflow**.

## 5. Rollback

```bash
cd /var/www/tavo
git log --oneline -10
git reset --hard <předchozí-commit>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize
```

Pokud problém způsobila migrace, vraťte ji cíleně:

```bash
php artisan migrate:rollback --path=database/migrations/<konkretni_soubor>.php
```

> **Nikdy nepouštějte `migrate:fresh` ani `migrate:reset` na produkci** — smaže to všechna
> data včetně nahraných obrázků.

## 6. Zálohy

Doporučené minimum — denní cron:

```bash
0 3 * * * mysqldump -u tavo -p'HESLO' tavo | gzip > /var/backups/tavo-$(date +\%F).sql.gz
0 4 * * * tar czf /var/backups/tavo-media-$(date +\%F).tar.gz /var/www/tavo/storage/app/public
```

## 7. Kontrolní seznam před prvním spuštěním

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] změněné heslo administrátora (výchozí je v `docs/LOCAL-SETUP.md`)
- [ ] nastavené SMTP a otestovaná poptávka
- [ ] Nastavení → Kontakt: reálný e-mail a telefon
- [ ] Nastavení → SEO: zapnutá indexace, doplněný obrázek pro sdílení
- [ ] HTTPS certifikát a přesměrování z HTTP
- [ ] běžící cron a zálohy
