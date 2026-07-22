# Architektura

## Rychlá orientace

```
app/
├─ Filament/
│  ├─ Pages/Settings/          ManageHome, ManageSite, ManageContact, ManageSeo
│  └─ Resources/               CaseStudies, Services, CaseStudyCategories,
│                              ProcessSteps, Founders, Pages, Leads
├─ Http/Controllers/           Home, CaseStudy, Service, Page, Lead, Sitemap
├─ Http/Requests/LeadRequest   validace poptávkového formuláře
├─ Mail/LeadReceived           notifikace o nové poptávce
├─ Models/                     CaseStudy, CaseStudyCategory, Service,
│                              ProcessStep, Founder, Page, Lead, User
├─ Providers/AppServiceProvider  sdílí $site a $contact do všech šablon
└─ Settings/                   SiteSettings, ContactSettings, HomeSettings, SeoSettings

database/
├─ migrations/                 schéma tabulek
├─ settings/                   výchozí hodnoty nastavení (spatie/laravel-settings)
└─ seeders/ContentSeeder       startovní obsah přepsaný z designu

resources/
├─ css/app.css                 design tokeny (@theme) + utility + animace
├─ js/{app.js,motion.js}       Alpine store + scroll animace
└─ views/
   ├─ components/              znovupoužitelné kousky (viz níže)
   ├─ home.blade.php           skládá homepage z <x-home.*> sekcí
   ├─ case-studies/            výpis a detail referencí
   ├─ services/show            detail služby
   ├─ pages/show               statické stránky
   ├─ errors/                  404, 419, 500, 503
   └─ sitemap.blade.php        XML mapa webu

design-source/                 původní Claude design (needitovat, jen referenci)
```

## Routy

| Metoda | URL | Controller | Pohled |
|---|---|---|---|
| GET | `/` | `HomeController` | `home.blade.php` |
| GET | `/reference` | `CaseStudyController@index` | `case-studies/index` |
| GET | `/reference/{slug}` | `CaseStudyController@show` | `case-studies/show` |
| GET | `/sluzby/{slug}` | `ServiceController@show` | `services/show` |
| GET | `/sitemap.xml` | `SitemapController@sitemap` | `sitemap` |
| GET | `/robots.txt` | `SitemapController@robots` | — |
| POST | `/poptavka` | `LeadController` | přesměruje na `/#kontakt` |
| GET | `/{slug}` | `PageController@show` | `pages/show` |

> Poslední routa chytá volný slug pro statické stránky — **musí zůstat na konci** souboru
> `routes/web.php`, jinak přebije všechno ostatní.

Formulář má `throttle:5,1` — pět odeslání za minutu z jedné IP.

## Jak se obsah dostane na stránku

1. `AppServiceProvider` přes `View::composer('*')` sdílí do **všech** šablon
   `$site` (`SiteSettings`) a `$contact` (`ContactSettings`).
   → V šablonách proto nikdy nevoláme `app(SiteSettings::class)` ručně.
2. Controller si dotáhne, co potřebuje konkrétní stránka (`HomeSettings`, modely)
   a předá to pohledu.
3. Šablona jen vypisuje a iteruje. **Žádné `@php` bloky s logikou** — když je potřeba
   něco odvodit, patří to do controlleru nebo do metody na modelu.

## Blade komponenty

| Komponenta | K čemu |
|---|---|
| `<x-layout.app>` | HTML kostra, `<head>`, navigace, patička, cookie lišta |
| `<x-layout.nav>` | fixní navigace + mobilní menu (Alpine store `nav`) |
| `<x-layout.footer>` | patička ze `SiteSettings` |
| `<x-seo.meta>` | title, description, OG, JSON-LD |
| `<x-btn>` | tlačítko — varianty `primary`, `dark`, `ghost`, `ghost-dark` |
| `<x-eyebrow>` | malý verzálkový popisek nad nadpisem |
| `<x-tag>` | pilulkový štítek |
| `<x-media>` | obrázek nebo šrafovaný zástupný vizuál, volitelně s parallaxem |
| `<x-cta-band>` | cihlový pruh s výzvou; s `:form="true"` obsahuje i formulář |
| `<x-lead-form>` | poptávkový formulář |
| `<x-cookie-bar>` | cookie lišta, spouští měření až po souhlasu |
| `<x-home.*>` | jednotlivé sekce homepage |
| `<x-errors.layout>` | společný layout chybových stránek |

## Konvence

- **Prázdné pole = sekce se nezobrazí.** Nikde nepoužíváme náhradní výplňové texty
  (`?: 'Nějaký text'`). Když správce nechá pole prázdné, blok se prostě vynechá.
- **Tailwind třídy se nikdy neskládají z fragmentů** (`'text-' . $size`). Scanner Tailwindu
  hledá celé názvy tříd v textu, dynamicky složená třída se do CSS nedostane.
  Pište celé literály, nebo použijte `style=""`.
- **Media přes Spatie MediaLibrary**, alt text v `custom_properties`, ne jako sloupec.
- **Seznamy mají vlastní model**, singletonový obsah stránky jde do settings třídy.
