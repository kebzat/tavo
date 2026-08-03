# Lokální zprovoznění

## Přihlášení do administrace

Administrace běží na <http://127.0.0.1:8000/admin>.

Účet správce zakládá seeder — **heslo v repozitáři nikde není**. Buď si ho určíte
předem v `.env`:

```env
ADMIN_EMAIL=vas@email.cz
ADMIN_PASSWORD=DlouheSilneHeslo123!
```

…nebo ho seeder vygeneruje náhodně a jednorázově vypíše do konzole:

```
Vytvořen správce: admin@taveo.cz
Heslo: xY7$kQ2mNp4wLz9r
Uložte si ho — znovu se nezobrazí.
```

> Seeder **nikdy nepřepíše heslo existujícího účtu**, takže opakované `db:seed`
> vám nevrátí heslo zpátky.

Další účty se zakládají přímo v administraci: **Nastavení → Uživatelé**
(vidí je jen role *Správce*). Případně z příkazové řádky:

```bash
php artisan make:filament-user
```

## Role

| Role | Co vidí |
|---|---|
| **Správce** | vše — obsah, nastavení webu, uživatele, údržbu |
| **Redaktor** | jen obsah — reference, služby, stránky, poptávky |

Vlastní roli si nikdo nemůže snížit a smazat sám sebe taky ne — jinak by se
u posledního správce už nikdo k nastavení nedostal.

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
