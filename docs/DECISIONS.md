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

## Drobnosti k dořešení

- `contact.phone` je zatím `+420 000 000 000` z designu — doplnit reálné číslo
  v Nastavení → Kontakt.
- Reference jsou anonymizované (klient „Rodinný e-shop *"), přesně jak to bylo v designu.
  Po souhlasu klientů doplnit skutečná jména a čísla.
- Fotky projektů zatím nejsou — místo nich se zobrazuje šrafovaný zástupný vizuál
  s popiskem. Nahrání fotky ho automaticky nahradí.
- Mobilní menu překrývala cookie lišta; vyřešeno sdíleným Alpine store `nav`,
  lišta se při otevřeném menu schová.
