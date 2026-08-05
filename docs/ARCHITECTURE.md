# Architektura

## Rychlá orientace

```
app/
├─ Console/Commands/           obrazky:zmensit — hromadné zmenšeniny obrázků
├─ Filament/
│  ├─ Pages/Settings/          ManageHome, ManageSite, ManageContact, ManageSeo
│  └─ Resources/               CaseStudies, Services, CaseStudyCategories,
│                              ProcessSteps, Founders, Pages, Leads
├─ Http/Controllers/           Home, CaseStudy, Service, Page, Lead, Sitemap
├─ Http/Requests/LeadRequest   validace poptávkového formuláře
├─ Mail/LeadReceived           notifikace o nové poptávce
├─ Models/                     CaseStudy, CaseStudyCategory, Service,
│                              ProcessStep, Founder, Page, Lead, User
├─ Providers/AppServiceProvider  sdílí $site a $contact, spouští ImageDerivatives
├─ Settings/                   SiteSettings, ContactSettings, HomeSettings, SeoSettings
└─ Support/
   ├─ ResponsiveImage          WebP zmenšeniny + srcset a rozměry
   ├─ ImageDerivatives         hledá obrázky v obsahu, poslouchá uložení
   ├─ PageMeta                 title, description, OG, robots pro <head>
   ├─ StructuredData           JSON-LD
   └─ ContentSettingsMigration základ migrací nastavení

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
| `<x-media>` | obrázek nebo šrafovaný zástupný vizuál, volitelně s parallaxem — viz `fit` níže |
| `<x-gallery>` | galerie obrázků na detailu reference s lightboxem (Alpine `tavoLightbox`) |
| `<x-cta-band>` | cihlový pruh s výzvou; s `:form="true"` obsahuje i formulář |
| `<x-lead-form>` | poptávkový formulář |
| `<x-cookie-bar>` | cookie lišta, spouští měření až po souhlasu |
| `<x-home.*>` | jednotlivé sekce homepage |
| `<x-errors.layout>` | společný layout chybových stránek |

### Obrázky — zmenšeniny a `<x-media>`

Do administrace se nahrávají originály, na web se posílají **WebP zmenšeniny**.
Řeší to `App\Support\ResponsiveImage`: z každého obrázku udělá varianty v šířkách
480 / 768 / 1024 / 1440 / 1920 px (nezvětšuje; když je originál znatelně širší než
nejbližší stupeň, přidá i jeho vlastní šířku) a vrátí pole `src`, `srcset`,
`width`, `height`, `alt`.

- Varianty vznikají **při uložení obsahu** — `App\Support\ImageDerivatives::listen()`
  poslouchá uložení `Media` i modelů se skládaným obsahem (`CaseStudy`, `Page`).
- Pro starší obsah a po nasazení na nový server je `php artisan obrazky:zmensit`.
- Kdyby varianta přesto chyběla, dopočítá se při vykreslení, aby na webu nikdy
  nechyběl obrázek.
- Leží v `storage/app/public/zmenseniny/` se stejnou strukturou jako originály.
- **Jen WebP, bez zálohy v původním formátu** — web stojí na `color-mix(in oklab)`
  a `aspect-ratio`, což umí právě ty prohlížeče, které umí i WebP.
- Obrázek pro sdílení (OG) naopak zůstává v **původním formátu** (`thumbPath()`) —
  čtečky odkazů na LinkedInu si s WebP neporadí.

Kdo obrázek dodává:

| Zdroj | Metoda |
|---|---|
| náhled reference | `CaseStudy::thumbImage()` |
| galerie reference | `CaseStudy::galleryImages()` |
| fotka zakladatelů | `Founder::photoImage()` |
| obrázky v blocích | klíč `*_image` z `HasContentBlocks` |

Komponenta `<x-media :image="…">` z toho vysází `<img>` včetně `width`/`height`
(bez nich stránka při načítání poskakuje) a `srcset`/`sizes`. Volající předává
`sizes` podle toho, jak široký slot obrázek v rozvržení zabírá — výchozí hodnota
odpovídá dvousloupcové mřížce. `:priority="true"` je pro obrázek na první
obrazovce (`loading="eager"` + `fetchpriority="high"`), všechno ostatní se načítá
až při scrollování.

| `fit` | Chování | Kde se používá |
|---|---|---|
| `cover` (výchozí) | obrázek se ořízne na poměr z `ratio` | náhledy ve výpisech a na homepage — mřížka musí být zarovnaná |
| `natural` | obrázek si drží vlastní poměr, `ratio` platí jen pro zástupný vizuál | obrázkový blok statické stránky |

Zaoblení drží rámeček (`overflow-hidden` + `rounded-*`), takže funguje v obou režimech.

### Galerie reference — `<x-gallery>`

Detail reference má v heru **slider** vedle nadpisu a textu (kolekce médií `gallery`
na `CaseStudy`), ne jeden pevný vizuál:

- **0 obrázků** → slider se nevykreslí a hero je jednosloupcový, jen text
  (`@php($hasGallery = …)` v `case-studies/show`).
- **1 obrázek** → slider bez teček a šipek.
- **2+ obrázků** → tečky pod slidem (aktivní se protáhne do cihlové čárky) + šipky
  na hoveru rámu. Obrázky se v rámu ořezávají na 4:3 kvůli konzistentní výšce;
  plný obrázek bez ořezu ukáže lightbox po kliknutí.

Obrázky dodává `CaseStudy::galleryImages()` přes `ResponsiveImage` (viz výš), takže
každý snímek nese rozměry i `srcset`. Slider i lightbox řídí Alpine komponenta
`tavoGallery` v `resources/js/app.js` — tečky/šipky mění `index`, klik na obrázek
otevře lightbox.

Přístupnost slideru:

- Skrytý snímek je `inert`, takže tabulátorem projde jen ten viditelný.
- Tečky nejsou `role="tab"` (to by chtělo navázaný `tabpanel`), ale skupina
  tlačítek s `aria-current`.
- Lightbox je `role="dialog"` s **pastí na fokus** (`trapFocus`) — po otevření
  jde fokus na křížek, Tab z dialogu neuteče a po Esc se vrátí tam, odkud se
  otevřel. Šipky doleva/doprava fungují jen v otevřeném lightboxu, aby
  klávesnice nepřepínala slider mimo obrazovku.

Slider je **klientský** (Alpine `x-for`), takže alt texty ani URL nejsou v serverovém
HTML — jsou v `x-data` payloadu. Feature testy proto ověřují přítomnost komponenty
a dat, ne vykreslený `<img>`.

## Konvence

- **Prázdné pole = sekce se nezobrazí.** Nikde nepoužíváme náhradní výplňové texty
  (`?: 'Nějaký text'`). Když správce nechá pole prázdné, blok se prostě vynechá.
- **Tailwind třídy se nikdy neskládají z fragmentů** (`'text-' . $size`). Scanner Tailwindu
  hledá celé názvy tříd v textu, dynamicky složená třída se do CSS nedostane.
  Pište celé literály, nebo použijte `style=""`.
- **Media přes Spatie MediaLibrary**, alt text v `custom_properties`, ne jako sloupec.
- **Seznamy mají vlastní model**, singletonový obsah stránky jde do settings třídy.
