# Mozek TAVEO

Společná paměť týmu pro lidi i agenty. Nástroje → **Mozek**.

Agenti se sami neučí. Model se při práci nemění a každá konverzace začíná od
nuly. Paměť je proto v databázi jako krátké **poznatky**, ne v konverzaci.
Platí jen poznatky, které schválil člověk.

## Části

| Stránka | Co dělá |
|---|---|
| **Zeptat se** (`/nastroje/mozek`) | Otázka na mozek. Agent hledá ve schválených poznatcích a odpoví s čísly poznatků (#id). Na „zapamatuj si…“ navrhne nový poznatek. Otázky jdou do jedné dlouhé konverzace, „Začít nový rozhovor“ ji založí znovu. Poznatky zůstávají. |
| **Poznatky** (`/nastroje/mozek/poznatky`) | Návrhy ke schválení (hromadně i po jednom), platné, „vždy v hlavě“, archiv. Ručně napsaný poznatek platí hned. |
| **Archiv a podklady** (`/nastroje/mozek/podklady`) | Vložený text nebo soubory (poznámky, e-maily, přepisy schůzek, PDF, Word, Excel). Knihovník je na pozadí přečte, napíše popis obsahu a pošle návrhy poznatků. Víc podkladů najednou bere jedny po druhých (fronta projektu). Podklady s klientem jsou jeho archiv, viz níž. |
| **Klienti → klient → Archiv / Mozek klienta** | Archiv klienta (nahrát, stáhnout, smazat) a jeho poznatky (platné a ke schválení, schválit jde i tady). |

## Poznatek

- **Vrstva:** `firma` (platí pro všechny), `klient` (jeden klient), `postup` (návod na opakovanou práci).
- **Vždy v hlavě:** agent ho dostane v každém zadání (do 8 000 znaků, co se nevejde, vypadne). Nastavuje jen člověk.
- **Nové znění** (`replaces_id`): agent navrhne opravu platného poznatku. Schválením se starý archivuje.
- Stav: `proposed` → `approved` / `rejected`, platný jde do `archived` a zpátky.

## Jak se k tomu dostanou agenti

| Agent | Co dostane | Jak navrhne poznatek |
|---|---|---|
| ClickUp (`ClickUpAgent`) | v zadání oddíl „Mozek TAVEO“: poznatky vždy v hlavě, poznatky klienta úkolu (do 6 000 znaků), příkaz na hledání | blok `[poznatek klient] Nadpis↵text [/poznatek]` za shrnutím, nejvýš 3. Do ClickUpu nejde, ve vlákně je jen věta, že návrh vznikl. |
| Reklamní grafika (`GraphicsAgent`) | totéž | totéž |
| Mozek (otázky) | instrukce a hledání v zadání | totéž, i se slugem klienta: `[poznatek klient svet-cejlonu]` |
| Knihovník (podklady) | podklady ke stažení, seznam všeho v mozku, pravidla | `POST /mozek/navrhy` po dávkách (max 20, z jedněch podkladů max 60) |

Do zadání jde jen malý základ, zbytek si agent dohledá (celý mozek by se do
zadání nevešel a agent by v něm to důležité přehlédl). Návrh se stejným
nadpisem, jaký už v mozku je, se nezaloží.

## Archiv klienta a mozek projektu

Každý klient má **archiv**: dokumenty jako data, vize a strategie, výsledky, obchodní data a marže.
Technicky jsou to podklady mozku s klientem (`knowledge_sources.client_id`), navíc s druhem
(`category`, `KnowledgeSource::categories()`), datem, ke kterému platí (`document_date`), a popisem
obsahu (`summary`, píše ho knihovník přes `POST /mozek/navrhy` s `popis`). Soubory zůstávají uložené
(`storage/app/private/mozek`), smazáním dokumentu zmizí i soubory, poznatky z něj zůstanou.

- **Učit se, nebo jen uložit** (`learn`): vypnuté = stav „Uloženo“, knihovník neběží (měsíční exporty,
  tabulky). Agent v projektu dokument i tak najde a přečte celý. „Udělat z nich poznatky“ ho pustí dodatečně.
- **Mozek projektu**: každá nová konverzace v projektu agenta (terminál, aplikace Claude, i po `/clear`
  a zkrácení) dostane na začátek úvod z `GET /mozek/uvod?projekt=…` (`KnowledgeBase::opening()`):
  klienta podle projektu agenta na kartě klienta, poznatky „vždy v hlavě“, schválené poznatky klienta,
  seznam archivu s popisy a jak navrhnout nový poznatek. Dělá to háček SessionStart v `managed-settings.json`
  (`taveo mozek-uvod`, projekt podle složky `/projekty/<projekt>/…`). Úvod má pod 10 000 znaků
  (víc Claude Code z háčku nevezme), zbytek si agent dohledá.
- Úlohy z nástrojů (ClickUp, grafika, mozek) mají mozek v zadání (`briefing()`, nově i se seznamem archivu),
  služba jim nastaví `TAVEO_ULOHA=1` a háček je přeskočí.
- **Učení na projektu**: agent v konverzaci navrhuje poznatky klienta (`taveo mozek-navrh klient … <slug>`),
  objeví se v Mozku → Poznatky i na kartě klienta ke schválení. Platí až po schválení jako vše ostatní.
- **Agent ukládá do archivu sám**: dokument, který mu člověk dá v konverzaci (soubor, vložená tabulka, zápis
  ze schůzky), uloží `taveo archiv-nahrat` (`POST /archiv/{slug}`, multipart: `nazev`, `druh`, `k_datu`, `popis`,
  `poznamka`, `text` nebo `soubory[]`, `uceni`). Bez `uceni` jen uložení s popisem od agenta, s ním i knihovník.
  Autor je „agent“, stejné typy a velikosti souborů jako formulář. Pokyn dostává v úvodu konverzace.
  Ověřeno naostro (26. 10.): po vloženém zápisu ze schůzky ho agent sám uložil s druhem, datem a popisem.
- Háček nikdy nezdrží začátek konverzace: bez spojení nebo s chybou nevypíše nic (limit 8 s).
- Do přestavění obrazu (`managed-settings.json` nahrává Tom) běží stejný háček z `~/.claude/settings.json`
  agenta a příkazy z `~/.local/bin/taveo`. Po nahrání `managed-settings.json` háček z `~/.claude/settings.json`
  smazat, jinak dostane konverzace úvod dvakrát.

## Rozhraní pro agenta

Token agenta (`AGENT_API_TOKEN`, v kontejneru `~/.config/taveo/token`), prefix `/nastroje/api/agent`:

| | |
|---|---|
| `GET /mozek?q=slova&klient=slug&navrhy=1` | hledání, nejlepší první (kmen slova: „reportu“ najde „Reporty“), `navrhy=1` přidá neschválené |
| `GET /mozek/seznam` | všechno platné a navržené jen s nadpisem (kontrola duplicit) |
| `GET /mozek/zdroje/{id}`, `…/soubory/{i}` | text a soubory podkladů |
| `POST /mozek/navrhy` | `{"zdroj": id?, "popis": "co v podkladech je"?, "poznatky": [{vrstva, klient?, nadpis, text, stitky?, puvod?, nahrazuje?}]}` |
| `GET /mozek/uvod?projekt=slug` | úvod konverzace v projektu (text), pro háček SessionStart |
| `GET /archiv/{slug}?druh=obchod` | archiv klienta s popisy a adresami souborů, `GET /mozek/zdroje/{id}/popis` jeden dokument |
| `POST /archiv/{slug}` | agent uloží dokument do archivu (multipart, viz Archiv klienta) |

Příkaz `taveo mozek "co hledám" [slug]`, `taveo mozek-navrh firma|klient|postup "nadpis" [slug] < text.md`,
`taveo archiv <slug> [druh]`, `taveo archiv-stahnout <id> <složka>`, `taveo archiv-nahrat <slug> "název" [--druh …] soubor…` a `taveo mozek-uvod`
(`infra/agent/taveo.sh`, do kontejneru ho nahrává Tom). Zadání z nástrojů volají `curl` přímo, na nový `taveo` nečekají.

## Kde běží

Projekt agenta `mozek` (`AGENT_BRAIN_PROJECT`), typ bez kódu. Založí ho tlačítko
**Připravit mozek** na stránce Zeptat se (jednou). Podklady i otázky jsou úlohy
v terminálu (`AgentJob` se `source` = `mozek` / `mozek-dotaz`): jdou sledovat a Zasáhnout,
limit 30 min jako ostatní (`ULOHA_LIMIT_MIN`), ztracené uzavře `clickup:hlidat`.

Proč ne jeden agent, který „nikdy nespí“: dlouho běžící konverzace se plní a
Claude ji zkracuje, takže by zapomínal. Proto je mozek jedno trvalé místo
v databázi a agent se k němu připojí, kdykoli je potřeba. Rozhovor s mozkem
navazuje, dokud ho někdo nezačne znovu.

## Pojistky

- Nic, co agent navrhne, neplatí bez schválení člověkem. Agent nic neschválí ani nesmaže.
- Podklady jsou pro agenta data, ne pokyny (vložené e-maily mohou obsahovat cokoli).
- Knihovník vynechává osobní údaje zákazníků, hesla a přístupy.
- Stejná pojistka předplatného jako u ClickUpu (`CLICKUP_AGENT_MAX_USAGE`).
