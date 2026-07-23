# Lokální zprovoznění

## Přihlášení do administrace

| | |
|---|---|
| URL | <http://127.0.0.1:8000/admin> |
| E-mail | `admin@tavo.cz` |
| Heslo | `tavo-admin-2026` |

> **Heslo změňte hned po prvním přihlášení** — klikněte vpravo nahoře na avatar → Profil.
> Toto heslo je zapsané v `database/seeders/DatabaseSeeder.php` a je určené jen pro první spuštění.

Další účet vytvoříte příkazem:

```bash
php artisan make:filament-user
```

## PHP na macOS — důležité

Na tomto stroji je v shellu `php` **alias** na PHP 8.2, ale projekt vyžaduje **8.4**.
Alias se v zsh rozvine dřív, než se stihne uplatnit změněný `PATH`, takže samotné
`export PATH=…` v jednom příkazovém řádku nestačí.

Tři možnosti, jak to obejít:

1. **Wrappery v repu** (nejjednodušší, fungují vždy):
   ```bash
   ./bin/php -v
   ./bin/art migrate
   ./bin/composer install
   ```

2. **Trvale v `~/.zshrc`** — smazat řádek `alias php=...` a přidat:
   ```bash
   export PATH="/opt/homebrew/opt/php/bin:$PATH"
   ```

3. **Plná cesta**: `/opt/homebrew/opt/php/bin/php artisan …`

## Databáze

MySQL 8 běží přes Homebrew (`brew services list`). Databáze projektu se jmenuje `tavo`,
uživatel `root` bez hesla.

```bash
mysql -uroot -e "CREATE DATABASE tavo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
```

> `migrate:fresh` smaže **všechna data včetně nahraných obrázků v databázi médií**.
> Pro změnu schématu vždy vytvořte novou migraci a spusťte prosté `php artisan migrate`.

## E-maily

Lokálně je `MAIL_MAILER=log` — odeslané poptávky najdete v `storage/logs/laravel.log`.
Pro reálné odesílání nastavte v `.env` SMTP údaje.

## Vývoj frontendu

```bash
npm run dev      # Vite dev server s hot reloadem
npm run build    # produkční build (nutný, než pustíte `php artisan serve` bez Vite)
```

Fonty (Montserrat) se stahují při buildu a hostují se lokálně — web nikdy nevolá Google Fonts.

## Časté potíže

| Problém | Řešení |
|---|---|
| `Composer detected issues in your platform: … >= 8.4.1` | pouštíte to pod PHP 8.2, viz sekce výše |
| Stránka je bez stylů | chybí `npm run build`, nebo neběží `npm run dev` |
| Změna v administraci se neprojeví | `php artisan optimize:clear` |
| `Class … not found` po přidání souboru | `composer dump-autoload` |
| Obrázky se nezobrazují | chybí `php artisan storage:link` |
| Nahraný obrázek je v administraci vidět, ale na webu ne | soubor skončil na špatném disku — ověř `SELECT disk FROM media`, musí být `public`; viz [DECISIONS.md](DECISIONS.md) |

## Kontrola v prohlížeči

Projekt má nakonfigurovaný Playwright MCP (`.mcp.json`). Po jakékoliv změně šablon
je vhodné projít stránku v prohlížeči a zkontrolovat konzoli — layoutové chyby
typová kontrola ani testy neodhalí.
