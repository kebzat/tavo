# Co se edituje kde

Mapa pro správce webu: kde v administraci najdu který kus stránky.

> Než začnete psát nový text, mrkněte na [.claude/skills/tavo-copy/SKILL.md](../.claude/skills/tavo-copy/SKILL.md).
> Jsou tam pravidla hlasu, seznam obratů, kterým se vyhýbáme, a co kam patří kvůli SEO.
> Dvě věci hlavně: žádné pomlčky v běžném textu a žádná čísla, která nemáme ověřená od klienta.

## Nastavení → Homepage

Celý obsah úvodní stránky, rozdělený do záložek podle sekcí.

| Záložka | Ovládá na webu |
|---|---|
| **Úvod** | velký nadpis přes tři řádky (odkrývá se po řádcích), perex, obě tlačítka |
| **Problém** | černá sekce „Každý si odvede svůj kus" — nadpis, perex, očíslované body |
| **Dvě situace** | dvě velké karty („Potřebujeme nový web" / „Web máme, ale…") |
| **Služby a reference** | jen nadpisy sekcí — obsah přichází z Obsah → Služby a Obsah → Reference |
| **Proč my** | černá sekce se čtyřmi sloupci (Marketing → Web → Data → Rozvoj) |
| **Lidé a proces** | nadpisy sekcí „Dva lidé" a „Jak spolu pracujeme" |
| **Závěrečné CTA** | cihlová sekce s formulářem na konci stránky |

> Které reference se objeví na homepage, řídí přepínač **„Vypíchnout na homepage"**
> u konkrétní reference, ne nastavení homepage.

## Nastavení → Web

Název značky, text a odkazy v patičce, položky v horním menu, tlačítko „Poptat projekt", copyright.

## Nastavení → Kontakt

E-mail, telefon, fakturační údaje, sociální sítě a **příjemci notifikací o poptávce**.
Tyto údaje se propisují do navigace, patičky i všech CTA sekcí — nikde je needitujte zvlášť.

## Nastavení → SEO a měření

Výchozí titulek a popisek, obrázek pro sdílení na sociálních sítích, ID Google Tag Manageru
a přepínač indexace (vypnout na testovacím serveru).

Měřicí kód se načte **až po souhlasu s cookies**. Když je pole GTM prázdné, cookie lišta
nabídne jen nezbytné cookies.

## Obsah → Reference

Nejbohatší část administrace, rozdělená do záložek:

| Záložka | Obsah |
|---|---|
| **Základ** | název, URL, kategorie, pořadí, zveřejnění, „vypíchnout na homepage", texty do výpisu, štítky, obrázky |
| **Detail** | nadpis detailu, údaje o projektu (klient/obor/rozsah/doba), výchozí stav, role marketingu a vývoje |
| **Výsledky** | metriky (hodnota + popisek) a citace klienta |
| **SEO** | vlastní titulek a popisek; prázdné = použije se název a krátký popis |

Pořadí ve výpisu se mění tažením řádků v seznamu.

**Obrázky:**
- **Náhled ve výpisu** (doporučeně 4:3) — jeden obrázek, zobrazí se na homepage
  i ve výpisu referencí. Dokud ho nenahrajete, je tam šrafované pole s textem
  z pole „Popisek zástupného vizuálu".
- **Galerie na detailu** — libovolný počet obrázků. Pořadí měníte přetažením,
  obrázky se **neořezávají** (zobrazí se v poměru, v jakém je nahrajete), jeden
  obrázek dostane užší sloupec, dva a víc se skládají do dvou sloupců. Návštěvník
  si obrázek zvětší kliknutím. **Prázdná galerie = sekce na detailu se vůbec
  nezobrazí** — reference tedy klidně může být bez obrázků.

## Obsah → Služby

Seznam v sekci „Co děláme". Služba s přepínačem **„Má vlastní stránku"** dostane navíc
detail na `/sluzby/<url>` a v seznamu se u ní objeví odkaz „Zjistit více".
Záložky Detailní stránka a SEO se zobrazí jen u takové služby.

Čtyři služby mají vlastní stránku schválně: každá cílí na jiný dotaz ve vyhledávání
(tvorba webových stránek, tvorba e-shopu, správa reklamy, správa webu). Když budete
zakládat pátou, dejte jí vlastní dotaz, ne variaci na existující. Dvě stránky o tom samém
si berou pozice navzájem.

## Obsah → Kategorie referencí

Filtry nad výpisem `/reference`. Slug se používá v adrese: `/reference?kategorie=weby`.
Kategorie bez jediné zveřejněné reference se ve filtru vůbec nenabídne.

## Obsah → Postup spolupráce

Pět kroků v sekci „Jak spolu pracujeme". Přepínač „Zvýraznit" udělá horní linku kroku cihlovou
(v designu je to poslední krok).

## Obsah → Lidé

Pavel a Tom v sekci „Dva lidé". Společná fotka stačí u prvního z nich — sekce používá jednu
fotografii a jména vysází jako pilulky přes ni.

## Obsah → Statické stránky

Ochrana osobních údajů, cookies a další právní texty. URL vzniká ze slugu: `/cookies`.
Odkazy na ně jsou natvrdo v patičce.

## Poptávky

Vše, co přijde z formuláře. Údaje od zákazníka nejdou editovat (jsou to doklady),
měnit lze **stav** (Nová / Řešíme / Vyhráno / Ztraceno) a **interní poznámku**.

Notifikační e-mail chodí na adresy z Nastavení → Kontakt.
