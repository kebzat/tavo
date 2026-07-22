# TAVO — web

Prezentační web TAVO postavený na Laravelu s Filamentem jako CMS.
Vizuálně vychází z původního Claude designu, který je pro referenci zachovaný v [`design-source/`](design-source/).

| | |
|---|---|
| **Stack** | Laravel 13 · PHP 8.4 · MySQL 8 · Tailwind CSS v4 · Alpine.js · Filament v4 |
| **Administrace** | `/admin` |
| **Jazyk** | pouze čeština |

## Rychlý start

```bash
# PHP 8.4 (na macOS není nalinkované, viz docs/LOCAL-SETUP.md)
export PATH="/opt/homebrew/opt/php/bin:$PATH"

composer install
npm install

cp .env.example .env
php artisan key:generate

mysql -uroot -e "CREATE DATABASE tavo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
php artisan storage:link

npm run build          # nebo `npm run dev` pro vývoj s hot reloadem
php artisan serve
```

Web běží na <http://127.0.0.1:8000>, administrace na <http://127.0.0.1:8000/admin>.

Přihlašovací údaje po seedu jsou v [docs/LOCAL-SETUP.md](docs/LOCAL-SETUP.md) — **heslo změňte hned po prvním přihlášení.**

## Užitečné příkazy

```bash
php artisan test                # testy
vendor/bin/pint                 # formátování kódu
vendor/bin/pint --test          # jen kontrola, stejně jako v CI
php artisan db:seed             # doplní chybějící obsah (idempotentní)
php artisan optimize:clear      # vyčistí všechny cache
```

Pomocné wrappery `./bin/php`, `./bin/art` a `./bin/composer` volají správnou verzi PHP i bez upraveného `PATH`.

## Dokumentace

| Soubor | O čem je |
|---|---|
| [docs/LOCAL-SETUP.md](docs/LOCAL-SETUP.md) | zprovoznění na vlastním stroji, přihlašovací údaje, řešení potíží |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | jak je projekt poskládaný — routy, controllery, šablony |
| [docs/CONTENT-MODEL.md](docs/CONTENT-MODEL.md) | mapa „pole v administraci → místo na webu" |
| [docs/DESIGN-SYSTEM.md](docs/DESIGN-SYSTEM.md) | barvy, typografie, komponenty, animace |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | příprava serveru, CI/CD, rollback |
| [docs/DECISIONS.md](docs/DECISIONS.md) | rozhodnutí učiněná při stavbě a proč |
| [CLAUDE.md](CLAUDE.md) | konvence projektu pro AI asistenty |
