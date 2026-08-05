# Design systém

Všechny hodnoty jsou vytažené 1:1 z původního designu (`design-source/*.dc.html`)
a žijí v `@theme` bloku v [`resources/css/app.css`](../resources/css/app.css).
Když měníš hodnotu tam, uprav i tenhle dokument.

## Barvy

| Token | Hex | Kde |
|---|---|---|
| `cream` | `#f4ede1` | základní pozadí webu |
| `ink` | `#131110` | text, tmavé sekce, tlačítka |
| `ink-soft` | `#1d1a18` | karty na tmavém pozadí |
| `ink-lift` | `#2a2622` | gradienty tmavých vizuálů |
| `brick` | `#db4b24` | akcent, CTA pruhy, zvýrazněná kurzíva |
| `brick-dark` | `#c23e1a` | hover primárního tlačítka |
| `body` | `#3a362e` | běžný text na krémovém |
| `muted` | `#6b6459` | popisky, sekundární text |
| `sand-100`…`sand-600` | `#e4dccb`…`#bfb69f` | gradienty zástupných vizuálů |

Použití v Blade: `bg-cream`, `text-brick`, `border-ink/14`, `text-cream/65` atd.

### Kontrast

Paleta je součástí vizuálního stylu značky a **kvůli kontrastu se nemění**.
Cihlová na krémové (3,58:1) a krémová na cihlové (3,58:1) nesplňují WCAG AA
pro drobný text; je to vědomé rozhodnutí, ne opomenutí. Automatický audit
proto na barvy hlásit chyby bude — u ostatních pravidel (fokus, ARIA, struktura,
formuláře) ale zůstává nulová tolerance, viz [ARCHITECTURE.md](ARCHITECTURE.md).

## Typografie

Písmo **Montserrat** (300–800 + kurzívy), self-hostované přes Bunny Fonts —
stahuje se při buildu do `public/build/assets`, web nikdy nevolá Google.

Konfigurace je v [`vite.config.js`](../vite.config.js), do stránky ho pouští
direktiva `@fonts` v `components/layout/app.blade.php`. **Bez ní se písmo vůbec
nenačte** a web se vysází systémovým fontem — na vývojářském Macu si toho nikdo
nevšimne, protože Montserrat bývá nainstalovaný lokálně.

Tři věci, na kterých to stojí:

- **`subsets: ['latin', 'latin-ext']`** — č, ď, ě, ň, ř, š, ť, ů a ž leží
  v latin-ext. Bez něj by se půlka české diakritiky vysázela náhradním písmem.
- **`preload`** jen pro 400 a 800 (běžný text a nadpisy na první obrazovce).
  Předsunout všechny řezy by znamenalo ~500 kB blokujících požadavků.
- **Jen WOFF2.** Bunny vrací ke každému řezu i WOFF; obě pravidla mají stejnou
  rodinu i unicode-range, takže podle kaskády vyhraje to poslední (WOFF) a
  prohlížeč stáhne obojí. Plugin `woff2Only()` ve `vite.config.js` proto WOFF
  po sestavení zahodí.

Velikosti jsou fluidní `clamp()` tokeny, ne pevné hodnoty:

| Token | Hodnota | Použití |
|---|---|---|
| `text-hero` | `clamp(42px, 7.6vw, 124px)` | H1 na homepage |
| `text-page-title` | `clamp(40px, 7vw, 110px)` | H1 na podstránkách |
| `text-case-title` | `clamp(38px, 6.2vw, 96px)` | H1 detailu reference |
| `text-cta` / `text-cta-sm` | `clamp(34px, 6.4vw, 96px)` / `clamp(30px, 5.4vw, 80px)` | nadpis v cihlovém pruhu |
| `text-h2` / `text-h2-lg` / `text-h2-sm` | `clamp(28px,4vw,58px)` / `(30px,4.4vw,64px)` / `(26px,3.4vw,46px)` | nadpisy sekcí |
| `text-h3` / `text-h3-sm` | `clamp(24px,2.8vw,42px)` / `(20px,2.2vw,30px)` | nadpisy karet |
| `text-svc` | `clamp(26px, 3.6vw, 52px)` | řádky v seznamu služeb |
| `text-card` | `clamp(24px, 2.6vw, 38px)` | nadpisy velkých karet |
| `text-quote` | `clamp(22px, 3.2vw, 42px)` | citace klienta |
| `text-metric` / `text-metric-lg` / `text-metric-sm` | `(30px,3.4vw,48px)` / `(44px,6vw,84px)` / `(22px,2.4vw,32px)` | čísla výsledků |
| `text-lead` / `text-perex` / `text-body-lg` | `(16px,1.7vw,21px)` / `(15px,1.6vw,19px)` / `(16px,1.5vw,20px)` | odstavce |

Nadpisy jsou vždy `font-extrabold` (800) s negativním `tracking` (`-.02em` / `-.03em`).

## Tvary a pohyb

| Token | Hodnota |
|---|---|
| `rounded-pill` | `100px` — tlačítka, štítky |
| `rounded-card` | `22px` — velké karty, CTA boxy |
| `rounded-media` | `20px` — obrázky v referencích |
| `rounded-thumb` | `18px` — náhledy ve výpisu |
| `ease-tavo` | `cubic-bezier(.2,.7,.2,1)` — hover a posun |
| `ease-tavo-out` | `cubic-bezier(.16,1,.3,1)` — odkrývání řádků nadpisu |

## Breakpointy

Původní design přepínal layout JavaScriptem na 860 px a 1100 px. Tady jsou z toho
běžné Tailwind breakpointy:

| Prefix | Šířka |
|---|---|
| `menu:` | od 861 px — desktopové menu, vícesloupcové mřížky |
| `loop:` | od 1101 px — čtyřsloupcová mřížka sekce „Proč my" |

Standardní `sm: md: lg:` zůstávají k dispozici.

## Vlastní utility

`container-tavo` (max 1500 px, vycentrováno), `section-x` (`padding-inline: 6vw`),
`section-y` / `section-y-sm` (svislé odsazení sekcí), `hatch-light` / `hatch-dark`
(šrafovaný vzor zástupných vizuálů).

Obsah z rich-text editoru sází třída `prose-tavo`. Uvnitř tmavé sekce se k ní přidává
`prose-tavo-dark`, která přebarví odstavce a odrážky na krémovou — bez ní by zůstaly
v tmavé barvě textu a nebyly vidět.

## Odsazení bloků statických stránek

Každý blok si nese svislé odsazení sám (`section-y-sm`) a v atributu `data-block-bg`
říká, jaké má pozadí (`cream` / `ink` / `brick`). Když jdou za sebou dvě sekce se stejným
pozadím, CSS druhé z nich sebere horní odsazení, aby v místě bez viditelného předělu
nevznikla díra. Světlá sekce hned pod hlavičkou stránky dostane jen malý odstup,
protože navazuje na perex.

Pravidlo drží [`resources/css/app.css`](../resources/css/app.css) přes sousedící
selektory, ne Blade. Nový blok proto musí `data-block-bg` nastavit, jinak se do
slučování nezapojí.

**Při skládání stránky střídejte světlou a tmavou sekci.** Dva tmavé pruhy za sebou
splynou v jeden dlouhý blok, ve kterém se ztratí předěl mezi tématy.

## Animace

Řídí je [`resources/js/motion.js`](../resources/js/motion.js) — port původního JS,
bez GSAP a bez Three.js (původní 3D funkce `init3D()` se v designu nikdy nevolala).

| Atribut | Chování |
|---|---|
| `data-reveal` | prvek se odkryje, jakmile doscrolluje do 92 % výšky okna |
| `data-line` | řádek nadpisu vyjede zdola, se staggerem 110 ms |
| `data-parallax` + `data-parallax-wrap` | jemný posun obrázku při scrollu |
| `data-nav` | navigace dostane krémové pozadí po 40 px scrollu |
| `data-svc` + `data-svc-plus` | řádek služby se při hoveru odsadí, plus se otočí o 45° |

**Důležité:** obsah je v CSS viditelný ve výchozím stavu. Třídu `.pre`, která ho skryje,
přidává až JavaScript. Když skript selže nebo je vypnutý, web zůstane čitelný.

Vše respektuje `prefers-reduced-motion: reduce` — animace se pak vypnou úplně.

## Pravidla, která nesmíš porušit

1. **Nikdy neskládej Tailwind třídu z fragmentů** — `'text-' . $velikost` se do CSS nedostane,
   protože scanner hledá celé názvy tříd v souborech. Piš celé literály.
2. **Nové barvy a velikosti patří do `@theme`**, ne jako jednorázová arbitrary hodnota.
3. **Prázdný obsah = žádná sekce.** Nepoužívej výplňové texty ani obrázky jako fallback.
4. **Po každé změně šablony se podívej do prohlížeče** — layoutovou chybu testy nechytnou.
