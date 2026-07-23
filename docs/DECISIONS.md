# Rozhodnutí a jejich důvody

Zápisník z první stavby webu (22.–23. 7. 2026). Slouží k tomu, aby se za půl roku
nikdo neptal „proč je to takhle".

## Stack

**Laravel 13, ne 12.** `composer create-project laravel/laravel` nainstaloval 13.21.1.
Ověřeno, že Filament v4.12 na něm běží bez problémů, takže nemělo smysl uměle degradovat.

**PHP 8.4 z `/opt/homebrew/opt/php/bin`.** V shellu je `php` alias na 8.2, což Laravel 13
nespustí. Alias se v zsh rozvine dřív než změněný `PATH`, proto jsou v repu wrappery
`bin/php`, `bin/art`, `bin/composer`. Do `~/.zshrc` jsem nesahal.

**Vlastní sitemap místo `spatie/laravel-sitemap`.** Web má čtyři typy adres a plná
kontrola nad `lastmod`/`priority` vyšla na 60 řádků. Balíček jsem odinstaloval.

## Obsah a CMS

**Settings + modely místo blokového page-builderu.** Zadání znělo „vše editovatelné včetně
sekcí homepage". Generický drag&drop builder by rozbil přesnou designovou skladbu a byl by
řádově víc práce. Místo toho: každá sekce homepage = záložka ve Filament settings stránce
(s repeatery pro seznamy), každá opakující se entita = model s resourcem. Editovatelné je
úplně všechno, layout zůstává nerozbitný.

**Docblocky u polí typu `array` v settings třídách musely pryč.** `spatie/laravel-settings`
parsuje docblocky a na `@var array<int, array{label: string}>` spadne s hláškou
`Unexpected token "", expected '>'`. Prostý `array` bez docblocku funguje.

**Reference: vizuál je vždy vlevo.** V původním designu má druhá case study v markupu
text první, ale s `order:1` na obrázku — na desktopu tedy oba bloky vypadají stejně.
Zachoval jsem, co se reálně vykreslovalo, ne co markup napovídá.

## Co z designu vypadlo

**GSAP a Three.js.** Design je načítal z CDN, ale funkce `init3D()` (dva propletené torusy)
se nikdy nevolala a canvas `#tavo3d` v markupu vůbec nebyl. Ušetřeno ~200 kB JS.
Kdyby se 3D prvek měl doplnit, původní kód je v `design-source/TAVO Homepage.dc.html`.

**Google Fonts CDN → Bunny Fonts self-hosted.** Kvůli GDPR (přenos IP do USA) a rychlosti.
Fonty se stahují při buildu, běhový provoz nevolá žádnou třetí stranu.

**Responzivita z JS do CSS.** Design přepínal `grid-template-columns` JavaScriptem
na 860 px a 1100 px. Přepsáno na Tailwind breakpointy `menu:` a `loop:` — funguje
i bez JS a nebliká při načtení.

## Co jsem přidal nad rámec designu

- **Poptávkový formulář** (design měl jen `mailto:`) — do DB, s notifikací, honeypotem
  a limitem 5 odeslání za minutu.
- **Cookie lišta** — měřicí kód se načte až po souhlasu; bez vyplněného GTM ID se
  nabízí jen nezbytné cookies.
- **Chybové stránky** 404/419/500/503 v designu TAVO.
- **Právní stránky** Ochrana osobních údajů a Cookies — texty jsou rozumný základ,
  ale **měl by je před spuštěním projít někdo, kdo za ně ponese odpovědnost**.
- **Odkazy na právní stránky v patičce.**

## Chyby nalezené při auditu v prohlížeči (a opravené)

Tohle by typová kontrola ani testy neodhalily — vyplavalo to až při proklikání
administrace v Playwrightu.

**`[object Object]` v jednoduchých seznamech.** Filamentí „simple" repeater pracuje
s plochým polem řetězců (`["Štítek"]`), ale seed ukládal pole objektů (`[{"text": "Štítek"}]`).
V administraci se tak místo textu zobrazovalo `[object Object]` a nešlo to uložit.
Narovnáno migrací `2026_07_23_010000_flatten_simple_list_fields` (má i `down()`),
šablony teď vypisují prvek přímo. Týkalo se štítků, odrážek a bodů rolí.

**Prázdné pole shodilo ukládání nastavení.** Vyprázdněný input přijde z Filamentu jako
`null`, ale vlastnosti settings tříd byly typované `string` → `Cannot assign null to property`.
Všechna nepovinná pole jsou teď `?string`. Povinná zůstala: `brand_name`, `nav_cta_label`,
`nav_cta_url`, `copyright`, `email`, `phone`, `default_title`.
Hlídá to test `test_volitelna_nastaveni_snesou_prazdnou_hodnotu`.

**Prázdný `<h1>` na nové referenci.** Nově založená reference má vyplněný jen název,
ne „Nadpis detailu" — detail se tedy vykreslil s prázdným H1. Doplněn fallback na název
záznamu (u služeb stejně).

**Cookie lišta překrývala CTA v mobilním menu.** Vyřešeno sdíleným Alpine store `nav` —
lišta se při otevřeném menu schová. Logo se zároveň přepne na krémovou variantu,
jinak by na tmavém překryvu zmizelo.

## Nahrané obrázky mizely z webu (23. 7. 2026)

**Příznak:** obrázek nahraný v administraci se v Filamentu tvářil v pořádku, ale na webu
se nezobrazil (rozbitý `<img>`). Poprvé u reference ChrudimLab.

**Příčina:** `vendor/filament/support/config/filament.php` má
`'default_filesystem_disk' => env('FILESYSTEM_DISK', 'local')` a v `.env` je
`FILESYSTEM_DISK=local`. Filament tenhle disk předává MediaLibrary při nahrávání,
takže soubory skončily v `storage/app/private/`, kam se z webu nedá dostat.
Obrázky ze seederu problém neměly — ty používaly výchozí disk MediaLibrary (`public`).

**Oprava, tři vrstvy:**
1. `config/filament.php` publikován, `default_filesystem_disk` je natvrdo `public`
   (přes `FILAMENT_FILESYSTEM_DISK`, aby to nekolidovalo s výchozím diskem aplikace).
2. `useDisk('public')` na media kolekcích v `CaseStudy` a `Founder` — pojistka pro
   případ, že disk přidá někdo programově (seeder, import).
3. Migrace `2026_07_23_170000_move_media_to_public_disk` přesune soubory,
   které se stihly nahrát špatně, a přepíše jim `disk` v databázi.

**Hlídá to** `tests/Feature/MediaUploadTest.php` — ověřeno, že test opravdu selže,
když se kterákoliv z vrstev vrátí zpět.

**Pozor při nasazení:** `FILESYSTEM_DISK` v `.env` nechte být; disk pro Filament
řídí `FILAMENT_FILESYSTEM_DISK`, který musí zůstat `public`. A na serveru je nutný
`php artisan storage:link`, jinak nebude fungovat ani správný disk.

## Hlavní vizuál → galerie (23. 7. 2026)

Původně měl detail reference jeden pevný vizuál (kolekce `hero`). Klient chtěl místo
toho **galerii** s libovolným počtem obrázků — 0, 1 nebo víc.

- Kolekce `hero` (singleFile) nahrazena kolekcí `gallery` (multiple). Migrace
  `2026_07_23_190000_move_case_study_hero_to_gallery` přesune stávající vizuál
  do galerie jako první položku, takže se nic neztratí.
- Komponenta `<x-gallery>` řeší rozvržení podle počtu (1 = užší sloupec, 2+ = `columns-2`)
  a lightbox. Obrázky se neořezávají — každý si drží vlastní poměr.
- `CaseStudy::galleryImages()` čte rozměry ze souboru a cachuje je, aby šlo rezervovat
  `width`/`height` a stránka při načítání neposkakovala.
- Prázdná galerie → celá sekce se přeskočí. Pokryto `CaseStudyGalleryTest`.

Poznámka: `<x-media>` s `fit="natural"` v projektu zůstává (komponenta i test), i když
ho teď nikdo nevolá — je to připravená varianta pro obrázek, který se nemá ořezávat.

## Drobnosti k dořešení

- `contact.phone` je zatím `+420 000 000 000` z designu — doplnit reálné číslo
  v Nastavení → Kontakt.
- Reference jsou anonymizované (klient „Rodinný e-shop *"), přesně jak to bylo v designu.
  Po souhlasu klientů doplnit skutečná jména a čísla.
- Fotky projektů zatím nejsou — místo nich se zobrazuje šrafovaný zástupný vizuál
  s popiskem. Nahrání fotky ho automaticky nahradí.
- Mobilní menu překrývala cookie lišta; vyřešeno sdíleným Alpine store `nav`,
  lišta se při otevřeném menu schová.
