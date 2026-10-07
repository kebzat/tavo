# Architektura

## Rychlá orientace

```
app/
├─ Console/Commands/           obrazky:zmensit — hromadné zmenšeniny obrázků
├─ Filament/
│  ├─ Pages/Settings/          ManageHome, ManageSite, ManageContact, ManageSeo
│  └─ Resources/               CaseStudies, Services, CaseStudyCategories,
│                              ProcessSteps, Founders, Pages, Leads
├─ Http/Controllers/           Home, CaseStudy, Service, EshopOffer, Page, Lead,
│                              Sitemap
├─ Http/Requests/LeadRequest   validace poptávkového formuláře
├─ Mail/LeadReceived           notifikace o nové poptávce
├─ Models/                     CaseStudy, CaseStudyCategory, Service, EshopOffer,
│                              WebText, ProcessStep, Founder, Page, Lead, User
├─ Providers/AppServiceProvider  sdílí $site a $contact, spouští ImageDerivatives
├─ Settings/                   SiteSettings, ContactSettings, HomeSettings, SeoSettings
└─ Support/
   ├─ ResponsiveImage          WebP zmenšeniny + srcset a rozměry
   ├─ ImageDerivatives         hledá obrázky v obsahu, poslouchá uložení
   ├─ PageMeta                 title, description, OG, robots pro <head>
   ├─ StructuredData           JSON-LD
   ├─ WebTexts                 statické texty editovatelné v administraci
   ├─ helpers.php              globální text() nad WebTexts
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
   ├─ eshop/show               nabídka pro e-shopy (společná pro čtyři routy)
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
| GET | `/checklist/{key}` | `ChecklistController@show` | `checklist/show` |
| POST | `/checklist/{key}/polozka/{item}` | `ChecklistToggleController` | JSON nebo návrat zpět |
| GET | `/checklist/{key}/{slug}` | `ChecklistController@category` | `checklist/category` |
| GET | `/audit/{key}` | `AuditController` | `audit/show` |
| GET | `/potencialni-spoluprace/{slug}` | `ProposalController` | `proposal/show` |
| GET | `/report/{key}` | `AdReportController` | `ad-report/show` |
| GET | `/{slug}` | `PageController@show` | `pages/show`, nebo `eshop/show` pro nabídku pro e-shopy |

> Poslední routa chytá volný slug pro statické stránky — **musí zůstat na konci** souboru
> `routes/web.php`, jinak přebije všechno ostatní.

> Nabídky pro e-shopy (`/mereni-pro-eshopy`, `/rozvoj-eshopu`…) sdílí catch-all routu
> se statickými stránkami. `PageController` nejdřív hledá zveřejněnou `EshopOffer`
> a předá ji `EshopOfferController`, teprve pak `Page`. Aby se slugy nepotkaly, hlídá
> formuláře obou resourců pravidlo `App\Rules\FreeTopLevelSlug`.

Formulář má `throttle:5,1` — pět odeslání za minutu z jedné IP.

## Dva Filament panely

| Panel | URL | Kdo se dostane dovnitř | Kde žijí třídy |
|---|---|---|---|
| `admin` (výchozí) | `/admin` | správce i redaktor | `app/Filament/Resources`, `app/Filament/Pages` |
| `tools` | `/nastroje` | jen správce | `app/Filament/Tools/Resources`, `app/Filament/Tools/Pages` |

Panel `tools` drží interní nástroje, které se správou obsahu webu nesouvisí. Zatím
v něm žijí **technické checklisty klientských webů**: jedna univerzální šablona
(`is_template`), z ní se klonuje checklist pro každou zakázku a ten se dá zpřístupnit
klientovi odkazem `/checklist/{slug}`.

Dvě věci, na kterých to stojí:

- **Adresáře se nesmí překrývat.** `AdminPanelProvider` prohledává `app/Filament/Resources`.
  Kdyby nástroje ležely uvnitř, objevily by se v obou panelech.
- **Přístup hlídá `User::canAccessPanel()`**, ne `OnlyForAdmins`. Trait skrývá jednotlivé
  resources, tady je potřeba zavřít celý panel.

### Struktura checklistu

`Checklist → ChecklistCategory → ChecklistSection → ChecklistItem`

Kategorie je karta na rozcestníku, sekce podnadpis uvnitř ní. Bez kategorií by měl
rozcestník patnáct odkazů, bez sekcí by kategorie o sedmatřiceti položkách byla
nečitelná.

Položka nese kromě sekce i **přímý odkaz na checklist**. Je to vědomá redundance:
Eloquent protáhne vztah jen přes jednu mezitabulku, takže bez toho sloupce by
se progres i souhrnná tabulka v administraci skládaly ručním joinem. Doplňuje
se sám v `ChecklistItem::booted()`.

### Sdílená stránka

Vlastní layout `<x-layout.document>` bez navigace webu, bez patičky, bez cookie lišty
a bez měřicího kódu, protože stránka nic neměří. Vzhled jde ze stejných tokenů
v `resources/css/app.css` jako zbytek webu.

**Odškrtávat může každý, kdo zná odkaz.** Checklist je pracovní podklad, ne účetnictví,
a přihlašování by ho pro klienta zabilo. Políčko sedí v opravdovém `<form>`, takže
při vypnutém JS se odešle klasicky; Alpine odeslání jen odchytí a pošle na pozadí,
aby se u sto položek nečekalo na překreslení stránky.

Interní poznámky se do pohledu vůbec nenačítají, `ChecklistController` je vynechává
už ve výběru sloupců.

### Adresy sdílených dokumentů

Audity, checklisty i nabídky spolupráce mají čitelný slug podle klienta
(`/audit/svet-cejlonu`). Vzniká sám při uložení přes `App\Support\UniqueSlug`
a u druhého dokumentu téhož klienta dostane pořadové číslo. V administraci
se dá přepsat v sekci Sdílení.

Audity a checklisty dřív měly v adrese náhodný token. Token zůstal v databázi:
`{key}` v routě přijme slug i token a starý odkaz přesměruje (301) na slug,
takže nic rozeslaného nepřestalo fungovat. Slug je uhodnutelný, stránky proto
dál chrání `noindex` a `robots.txt`, ne tajná adresa.

### Potenciální spolupráce

Dopadová stránka pro firmu, kterou chceme získat (Checklisty → Potenciální
spolupráce). Sekce jdou v pořadí co jsme objevili → kdysi, dnes a s námi (tři screenshoty
webu vedle sebe) → co doporučujeme → akční kroky → jak přemýšlíme, mezi ně se vkládají ukázky (tmavé pruhy s návrhem webu,
fotkami nebo videem). Každá sekce je JSON pole z repeateru na modelu `Proposal`,
prázdná se nezobrazí. Nadpisy sekcí jsou statické texty `spoluprace.*`.

Před závěrečnou výzvou může stát společná fotka s nadpisem, stejná pro všechny
koncepty (`ProposalSettings`, Checklisty → Koncepty: společná fotka). Bez fotky se
sekce nezobrazí.

Nová stránka vzniká nesdílená, přihlášený správce ji vidí přes Náhled. Otevření
klientem se počítá a u propojeného klienta zapíše do CRM stejně jako u auditu
(`App\Models\Concerns\TracksClientViews`).

### Reklamy klientů

Skupina **Reklamy** v panelu nástrojů: denní čísla z reklamních účtů klientů
(Meta), upozornění s doporučením, co upravit, a týdenní či měsíční reporty
sdílené odkazem `/report/{slug}`. Klient je stejný model `Client` jako
u checklistů a auditů. Podrobnosti, přístupy a plánované běhy v [ADS.md](ADS.md).

### Přehled spolupráce

Klient na paušál dostane odkaz `/klient/{nazev-klienta}-{6 znaků}`: paušál po oblastech, hodiny
sečtené po úkolech, co čeká na něj, plán po měsících a sdílené dokumenty.
Úkoly a cíle měsíců se plní u klienta v nástrojích. Podrobnosti
v [CLIENT-DASHBOARD.md](CLIENT-DASHBOARD.md).

### Audity

Vedle checklistu může mít klient **audit**: dlouhý dokument s nálezy, který dostane
odkazem `/audit/{slug}` a může se k němu vracet. Spravuje se v panelu nástrojů
(Checklisty → Audity). Sdílené audity a checklisty téhož klienta na sebe odkazují
tlačítkem v tmavé hlavičce.

Text je v **Markdownu**, převádí ho `App\Support\AuditMarkdown`. Nad běžným Markdownem
(včetně tabulek) umí štítek stavu `[[kritické]]`, jehož barvu určuje slovo, a z nadpisů
`##` skládá obsah v bočním sloupci. Syrové HTML se escapuje. Styly jsou v `app.css`
pod `.prose-audit` a `.audit-tag`.

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
| `<x-cookie-bar>` | cookie lišta a okno s nastavením kategorií, stav v Alpine store `consent` (`resources/js/consent.js`) |
| `<x-tracking>` | v `<head>`: výchozí Consent Mode a ID měřicích kódů pro JS |
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

## Kontrola před odesláním

Audity, nabídky, checklisty a reporty reklam mají u výpisu sloupec
„{jméno} – zkontrolováno“ pro každého uživatele se zapnutým `is_reviewer`
(administrace → Uživatelé). Svou kontrolu si každý přepne kliknutím do svého
sloupce nebo tlačítkem na detailu. Stav je v tabulce `reviews`
(`App\Models\Concerns\HasReviews`, `App\Filament\Tools\Actions\Reviews`).

Změna obsahu po kontrole kontrolu nesmaže, jen ji zastará (oranžová ikona).
Kontrola toho, kdo změnu udělal, platí dál. Zobrazení klientem, zapnutí sdílení
ani odeslání kontrolu neshodí. Změny v napojených tabulkách (položky checklistu)
se nehlídají.
