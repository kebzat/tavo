# Nasazení

Dvě cesty, podle toho, co na serveru máte:

- **A) Push do `main` → CI → automatický deploy přes SSH** — dál v tomto dokumentu
  (kapitoly 1–7). Vyžaduje na serveru jen SSH, `rsync` a PHP 8.4;
  git, composer ani node tam potřeba nejsou.
- **B) Průvodce v prohlížeči** — bez jediného příkazu v terminálu. Popsáno hned níž.

Obě cesty vedou ke stejnému výsledku; B se hodí na sdílené hostingy a panely
(CyberPanel, Plesk), kde je práce v shellu nepohodlná.

## 0. Cesta B — nasazení bez terminálu

Předpoklad: **`vendor/` a `public/build/` sestavíte lokálně** a nahrajete s sebou.
Na serveru pak není potřeba composer ani npm.

1. **V panelu hostingu** založte web a databázi. Document root nasměrujte na
   podsložku **`public/`** projektu (ne na jeho kořen — jinak by byl `.env`
   stažitelný z internetu).
2. **Nahrajte projekt** (FTP/FileZilla). Vynechte `node_modules/` a `.env`.
3. **Otevřete `https://vase-domena/install`.** Průvodce zkontroluje verzi PHP
   a rozšíření, vyžádá si údaje k databázi, zapíše `.env`, vygeneruje `APP_KEY`,
   spustí migrace, volitelně naplní výchozí obsah a propojí složku se soubory.
4. Hotovo — průvodce se **sám zamkne** a dál vrací 404.

Průvodce se nedá spustit na webu, který už běží (má `APP_KEY` a proběhlé migrace),
takže nehrozí, že by někdo cizí přepsal konfiguraci. Zámek je v
`storage/app/installed.lock`.

### Údržba z administrace

Běžné provozní úkony jsou v administraci pod **Nastavení → Údržba**, opět bez terminálu:

| Tlačítko | Kdy použít |
|---|---|
| Spustit migrace | po nasazení verze, která přidává pole nebo tabulky |
| Obnovit cache | když se změny obsahu nebo nastavení neprojevují |
| Propojit soubory | když se místo nahraných obrázků zobrazuje prázdné místo |

Stránka zároveň ukazuje verzi PHP, prostředí, stav databáze a poslední migraci.

## 1. GitHub repozitář

```bash
# jednorázově
gh repo create taveo-web --private --source=. --remote=origin --push
# nebo repo založit ručně na github.com a pak:
git remote add origin git@github.com:<uzivatel>/taveo-web.git
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
sudo mysql -e "CREATE DATABASE taveo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'taveo'@'localhost' IDENTIFIED BY 'SILNE_HESLO';"
sudo mysql -e "GRANT ALL ON taveo.* TO 'taveo'@'localhost'; FLUSH PRIVILEGES;"
```

Před prvním nasazením stačí připravit složku a `.env` — kód a `vendor/` doveze
sám workflow:

```bash
sudo mkdir -p /var/www/taveo && sudo chown -R $USER:www-data /var/www/taveo
cd /var/www/taveo

# .env si server drží sám, deploy ho nikdy nepřepisuje
curl -o .env https://raw.githubusercontent.com/<uzivatel>/taveo-web/main/.env.example
# nastavit: APP_ENV=production, APP_DEBUG=false, APP_URL=https://taveo.cz,
#           APP_KEY (doplní se níž), DB_USERNAME=taveo, DB_PASSWORD=…, MAIL_* (SMTP)
```

Pak spusť **Actions → Deploy → Run workflow**. Po prvním doběhnutí ještě jednou:

```bash
cd /var/www/taveo
php artisan key:generate     # jen poprvé, APP_KEY zůstává v .env natrvalo
php artisan db:seed --force  # jen poprvé, výchozí obsah a administrátor
sudo chown -R www-data:www-data /var/www/taveo
```

### nginx

```nginx
server {
    listen 80;
    server_name taveo.cz www.taveo.cz;
    root /var/www/taveo/public;

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
sudo ln -s /etc/nginx/sites-available/taveo /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d taveo.cz -d www.taveo.cz
```

### Cron (plánované úlohy Laravelu)

```
* * * * * cd /var/www/taveo && php artisan schedule:run >> /dev/null 2>&1
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
| `DEPLOY_PATH` | kořen projektu na serveru, např. `/var/www/taveo` (bez lomítka na konci) |
| `PRODUCTION_URL` | `https://taveo.cz` |
| `PHP_BIN` | cesta k PHP 8.4 na serveru — na CyberPanelu `/usr/local/lsws/lsphp84/bin/php`, jinak `php` |
| `WEB_USER` | volitelné; uživatel, pod kterým běží web (nahrané soubory se mu vrátí do vlastnictví) |

Server ke GitHubu přístup mít nemusí — soubory tam posílá GitHub, ne naopak.

## 4. Jak deploy probíhá

`.github/workflows/ci.yml` na každý push a PR spustí Pint a testy.
`.github/workflows/deploy.yml` se pustí **až po zeleném CI na `main`**. Projekt se
sestaví na GitHubu a na server se pošle hotový:

```
composer install --no-dev + npm run build (na GitHubu)
→ php artisan down → rsync celého projektu na server
→ migrate --force → storage:link → optimize → queue:restart → php artisan up
```

Nakonec ověří, že produkce vrací HTTP 200 — pokud ne, běh skončí červeně.
Když nasazení spadne uprostřed, web se přesto nahodí zpátky z údržby.

Deploy jde spustit i ručně: **Actions → Deploy → Run workflow**.

### Co se na server neposílá

`rsync` běží s `--delete`, takže složka na serveru přesně odpovídá repozitáři —
soubory smazané v gitu zmizí i z produkce. Výjimky jsou v
[`.github/deploy-exclude.txt`](../.github/deploy-exclude.txt): `.env`, celá
složka `storage/` (nahraná média, logy), `public/storage`, `public/.well-known/`
a vývojové věci (`tests/`, `docs/`, `design-source/`, `node_modules/`).

**Když na serveru vznikne něco ručně mimo tyto cesty, přidej to do toho souboru** —
jinak to příští nasazení smaže.

### Jak dostat na běžící web novou stránku nebo obrázek

`db:seed` se pouští jen při prvním nasazení, takže obsah přidaný do seederu se na
produkci nedostane. Nová stránka se veze **datovou migrací**, protože `migrate --force`
běží při každém nasazení. Podrobnosti a vzor v
[CONTENT-MODEL.md](CONTENT-MODEL.md#a-co-celá-nová-stránka).

Obrázky, které má migrace nahrát, patří do `database/seeders/assets/`. Složka `storage/`
je z nasazení vyloučená, takže cokoliv v ní se na server přes git nedostane.

## 5. Rollback

Na serveru už git není, rollback se dělá přes repozitář — vrátí se `main`
a nasazení se pustí znovu:

```bash
git log --oneline -10
git revert --no-edit <commit-který-to-rozbil>
git push            # CI proběhne a deploy nasadí opravený stav
```

Když je potřeba spěchat, jde nasadit i libovolný starší stav: **Actions → Deploy
→ Run workflow** a v `Use workflow from` vybrat větev nebo tag s tím stavem.

Pokud problém způsobila migrace, vraťte ji cíleně:

```bash
php artisan migrate:rollback --path=database/migrations/<konkretni_soubor>.php
```

> **Nikdy nepouštějte `migrate:fresh` ani `migrate:reset` na produkci** — smaže to všechna
> data včetně nahraných obrázků.

## 6. Zálohy

Doporučené minimum — denní cron:

```bash
0 3 * * * mysqldump -u taveo -p'HESLO' taveo | gzip > /var/backups/taveo-$(date +\%F).sql.gz
0 4 * * * tar czf /var/backups/taveo-media-$(date +\%F).tar.gz /var/www/taveo/storage/app/public
```

## 7. Kontrolní seznam před prvním spuštěním

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] změněné heslo administrátora (výchozí je v `docs/LOCAL-SETUP.md`)
- [ ] nastavené SMTP a otestovaná poptávka
- [ ] Nastavení → Kontakt: reálný e-mail a telefon
- [ ] Nastavení → SEO: zapnutá indexace, doplněný obrázek pro sdílení
- [ ] HTTPS certifikát a přesměrování z HTTP
- [ ] běžící cron a zálohy
