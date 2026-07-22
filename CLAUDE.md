# Konvence projektu TAVO

Web TAVO: Laravel 13 + Filament v4 (CMS) + Tailwind v4 + Alpine. Jen čeština.
Podrobnosti v [docs/](docs/) — hlavně [ARCHITECTURE.md](docs/ARCHITECTURE.md)
a [DESIGN-SYSTEM.md](docs/DESIGN-SYSTEM.md).

## Spouštění příkazů

V shellu je `php` alias na PHP 8.2, projekt potřebuje 8.4. Používej wrappery:

```bash
./bin/art migrate          # artisan
./bin/composer require …   # composer
./bin/php vendor/bin/pint  # pint
```

## Frontend

- **Tailwind třídy nikdy neskládej z fragmentů.** `'text-' . $x` scanner nenajde a třída
  se do CSS nedostane. Piš celé literály, případně `style=""`.
- **Nové barvy/velikosti patří do `@theme`** v `resources/css/app.css`, ne jako jednorázová
  arbitrary hodnota v šabloně.
- **Vlastní breakpointy** `menu:` (861 px) a `loop:` (1101 px) odpovídají původnímu designu.
- **Animace** řídí `resources/js/motion.js` přes `data-reveal`, `data-line`, `data-parallax`.
  Obsah je v CSS viditelný by default; třídu `.pre` přidává až JS.

## Blade

- `$site` (SiteSettings) a `$contact` (ContactSettings) jsou sdílené do **všech** šablon
  přes `AppServiceProvider`. Nikdy nevolej `app(SiteSettings::class)` v šabloně.
- **Žádné `@php` bloky s logikou.** Odvozeniny patří do controlleru nebo na model.
- **Prázdné pole = sekce se nezobrazí.** Nikdy nedoplňuj výplňové texty jako fallback
  (`?: 'Nějaký nadpis'`). Správce má právo nechat pole prázdné.

## Obsah

- **Seznam položek → vlastní model + Filament resource.** Singletonový obsah stránky
  → settings třída v `app/Settings/` + settings stránka v `app/Filament/Pages/Settings/`.
- **Nové pole v settings třídě vyžaduje migraci** v `database/settings/`, jinak
  aplikace spadne na chybějící hodnotě.
- **U `array` polí v settings třídách nepiš docblock** `@var array<…>` — spatie ho parsuje
  a na složitých tvarech spadne.
- **Obrázky přes Spatie MediaLibrary**, alt text do `custom_properties`, ne jako sloupec.

## Databáze

**Nikdy nespouštěj `migrate:fresh`, `migrate:reset` ani `db:wipe`** bez výslovného
souhlasu v aktuální konverzaci — lokální databáze obsahuje ručně nahraná média a obsah.
Pro změnu schématu vždy **nová migrace** + prosté `php artisan migrate`.

## Kontrola práce

Po každé změně šablony nebo komponenty **projdi výsledek v prohlížeči** přes Playwright MCP:
`browser_navigate` → `browser_take_screenshot` → `browser_console_messages`.
Typová kontrola ani testy neodhalí rozbitý layout, chybějící Tailwind třídu
ani runtime chybu v Blade.

Před dokončením úkolu: `./bin/art test` a `./bin/php vendor/bin/pint` musí být zelené.

## Referenční design

`design-source/` obsahuje původní Claude design (`*.dc.html` + React runtime `support.js`).
**Needituj ho** — je to podklad pro porovnání. Otevřít jde přes `file://`, obrázky
jsou tam nasymlinkované z `public/images/`.
