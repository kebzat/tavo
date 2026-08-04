# Co se edituje kde

Mapa pro správce webu: kde v administraci najdu který kus stránky.

> Než začnete psát nový text, mrkněte na [.claude/skills/tavo-copy/SKILL.md](../.claude/skills/tavo-copy/SKILL.md).
> Jsou tam pravidla hlasu, seznam obratů, kterým se vyhýbáme, a co kam patří kvůli SEO.
> Dvě věci hlavně: žádné pomlčky v běžném textu a žádná čísla, která nemáme ověřená od klienta.

## Kdo je vlastníkem textu: administrace, nebo repozitář?

Obsah bydlí v databázi a mění se v administraci. Jenže texty se dají nasadit i z kódu
migrací v `database/settings/`, protože jinak by se nová verze webu neměla jak dostat
na produkci. Nasazení pak spustí `php artisan migrate` a migrace přepíše hodnotu
v databázi — včetně toho, co si tam mezitím napsal správce.

Aby se to nestalo, dědí settings migrace od
[`App\Support\ContentSettingsMigration`](../app/Support/ContentSettingsMigration.php)
a mají tři metody s různou mírou drzosti:

| Metoda | Co udělá | Kdy ji použít |
|---|---|---|
| `add()` | doplní jen klíče, které v databázi ještě nejsou | **výchozí volba.** Vždy, když do settings třídy přibylo nové pole |
| `replaceIfUntouched()` | přepíše hodnotu, jen když v ní pořád stojí to, co tam poslala minulá migrace | oprava vlastního překlepu na webu, který už žije |
| `replace()` | přepíše natvrdo | jen před spuštěním webu, dokud obsah nikdo needituje |

Pravidlo pro běžný provoz: **po spuštění se formulace ladí v administraci, ne migrací.**
Migrace se píše, když přibývá pole. Nové pole bez migrace je jediná varianta, která
produkci opravdu shodí — settings třída bude chtít hodnotu, kterou databáze nemá.

### A co celá nová stránka?

Stejný problém: `ContentSeeder` se pouští jen při prvním nasazení, takže stránka
přidaná do seederu se na běžící web nikdy nedostane. Nasazení spouští `migrate`,
proto se nová stránka přiveze **datovou migrací**. Vzor je
[`2026_08_04_150000_add_eshop_landing_page`](../database/migrations/2026_08_04_150000_add_eshop_landing_page.php):

- Na začátku zkontroluje, jestli slug už neexistuje, a když ano, **nedělá nic**.
  Chová se tedy jako `add()`: co správce mezitím upravil v administraci, zůstane.
- Obrázky se do gitu nedostanou přes `storage/` (ta je z nasazení vyloučená). Leží
  proto v `database/seeders/assets/` a migrace je odtud zkopíruje na disk `public`.
  Existující soubor nepřepisuje.
- `down()` stránku smaže, ale obrázky nechá být. Správce mohl mezitím nahrát vlastní.

## Nastavení → Homepage

Celý obsah úvodní stránky, rozdělený do záložek podle sekcí.

| Záložka | Ovládá na webu |
|---|---|
| **Úvod** | velký nadpis přes tři řádky (odkrývá se po řádcích), perex, obě tlačítka |
| **Problém** | černá sekce „Web je hotový a tím to skončí" — nadpis, perex, očíslované body |
| **Dvě situace** | dvě velké karty („Potřebujeme nový web" / „Web máme, ale…") |
| **Služby a reference** | jen nadpisy sekcí — obsah přichází z Obsah → Služby a Obsah → Reference |
| **Proč my** | černá sekce se čtyřmi sloupci (Marketing → Web → Data → Rozvoj) |
| **Lidé a proces** | nadpisy sekcí „Kdo jsme" a „Jak spolu pracujeme", plus blok o specialistech kolem nás |
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
- **Galerie na detailu** — libovolný počet obrázků, zobrazí se jako **slider vedle
  nadpisu** v úvodu detailu. Pořadí měníte přetažením. Při více obrázcích jsou pod
  slidem tečky (aktivní se protáhne do cihlové čárky) a šipky. Náhled ve slideru
  má jednotný poměr; návštěvník si obrázek **zvětší kliknutím** (v lightboxu je bez
  ořezu). **Prázdná galerie = úvod detailu je jen text, bez obrázku** — reference
  tedy klidně může být bez obrázků.

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

Obsah stránky se **skládá z bloků**. Tlačítkem „Přidat blok" se rozbalí mřížka náhledů,
kde je u každého typu vidět drátěnka jeho rozvržení; z ní poznáte i výchozí barvu sekce.
Pořadí měníte tažením za šipky vlevo nahoře, blok jde sbalit i smazat. Na právní text
stačí jeden blok „Text", zbytek jsou grafické sekce ze stejné sady, jakou používá web.

| Blok | Co vysází |
|---|---|
| **Text** | běžný editor v úzkém sloupci — nadpisy, odstavce, seznamy, odkazy |
| **Obrázek a text** | dvousloupec; přepínačem volíte stranu obrázku a světlou/tmavou barvu sekce |
| **Před a po** | dva snímky přes sebe s dělicí čárou, která sleduje myš; návštěvník si přejetím ukáže původní i nový stav |
| **Statistiky** | řada velkých čísel s popisky a poznámkou pod nimi |
| **Očíslované body** | nadpis vlevo, perex a číslovaný seznam vpravo (jako sekce „Web je hotový" na úvodu) |
| **Postup v krocích** | tři nebo čtyři sloupce s číslem, názvem a popisem, každý s linkou nahoře |
| **Karty** | mřížka karet s nadpisem a textem, na dva nebo tři sloupce |
| **Výčet v pilulkách** | nadpis s perexem vlevo, krátké výrazy jako pilulky vpravo (názvy platforem, technologií) |
| **Obrázek** | samostatný obrázek přes šířku textu, volitelně s popiskem |
| **Citace** | výrok s cihlovou linkou a jménem pod ním |
| **Výzva k akci** | cihlový pruh s tlačítky na e-mail a telefon (bere je z Nastavení → Kontakt) |

**Zástupný vizuál.** Blok „Obrázek a text" má pole **Popisek zástupného vizuálu**. Když ho
vyplníte a fotku ještě nemáte, drží místo šrafované pole s tímhle textem, takže sekce
vypadá hotově a fotka se doplní později. Prázdný popisek bez fotky = sekce bude jen text.

**Blok „Před a po"** potřebuje oba snímky, jinak se vůbec nezobrazí. Nahrajte je pokud
možno ve stejné šířce a vyfocené od stejného místa stránky; snímky se ořezávají odshora
do jednoho poměru, takže rozdílná výška nevadí, ale jiný výřez ano. Štítky nad rohy jdou
přejmenovat (výchozí je „Před" a „Po").

**Prázdný blok se na webu nezobrazí** — nenechá po sobě ani prázdné místo. Platí to i pro
jednotlivá pole: nevyplněný nadtitulek prostě nebude, žádný náhradní text se nedoplňuje.

**Střídejte světlou a tmavou barvu sekce.** Dva tmavé bloky za sebou splynou v jeden
dlouhý pruh a předěl mezi tématy se ztratí. Odsazení mezi sekcemi se v takovém případě
samo sloučí, aby uprostřed nezůstala díra, ale číst se to bude hůř.

### Hlavička stránky

Záložka **Hlavička** je nepovinná a prázdná nechá střídmý úvod, jaký mají právní texty.
Jakmile vyplníte **Nadtitulek**, hlavička se přepne do podoby dopadové stránky: nadpis
vyroste přes celou šířku a nad ním se objeví cihlový nadtitulek s čárkou.

- **Zvýrazněná část nadpisu** je slovo nebo úsek z názvu stránky, který se vysází cihlově
  kurzívou. Když se v názvu nenajde, nic se nerozbije, jen se nic nezvýrazní.
- **Tlačítka na e-mail a telefon** berou kontakt z Nastavení → Kontakt.

### `/e-shop` — dopadová stránka pro oslovování

Stránka, na kterou vede odkaz v e-mailech a zprávách majitelům e-shopů. Není v menu ani
v patičce, chodí se na ni jen z odkazu. Devět bloků, které se v barvě pravidelně střídají,
konec je výzva poslat odkaz na e-shop.

Dvě věci v ní schválně chybí:

- **Ceny.** Blok „Co to stojí" zatím jen popisuje, co vyjde dráž a co levněji. Až budete
  mít potvrzené částky, doplňte orientační „od" ceny, nejlépe jako blok Statistiky nad něj.
- **Čísla z odvedené práce.** Blok Statistiky uvádí jen to, co slibujeme (odpověď do dvou
  dnů, analýza zdarma), ne výsledky projektů. Ty se doplní, až je budeme mít od klientů
  potvrzené, spolu s blokem Citace.

Sekce „Přetáhněte čáru a uvidíte rozdíl" porovnává úvodní stránku e-shopu 2e Kompresory
před předěláním a po něm. **Než stránku pustíte ven, ověřte, že máte od 2e souhlas
s uvedením jako reference** včetně snímků obrazovky.

**Obrázky v blocích** se nahrávají přímo do bloku a alt text je vedle nich ve stejném
bloku. Nejde tedy o media knihovnu jako u referencí — blok si obrázek nese sám.

## Poptávky

Vše, co přijde z formuláře. Údaje od zákazníka nejdou editovat (jsou to doklady),
měnit lze **stav** (Nová / Řešíme / Vyhráno / Ztraceno) a **interní poznámku**.

Notifikační e-mail chodí na adresy z Nastavení → Kontakt.
