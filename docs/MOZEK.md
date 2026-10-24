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
| **Podklady** (`/nastroje/mozek/podklady`) | Vložený text nebo soubory (poznámky, e-maily, přepisy schůzek, PDF, Word). Knihovník je na pozadí přečte a pošle návrhy poznatků. Víc podkladů najednou bere jedny po druhých (fronta projektu). |

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

## Rozhraní pro agenta

Token agenta (`AGENT_API_TOKEN`, v kontejneru `~/.config/taveo/token`), prefix `/nastroje/api/agent`:

| | |
|---|---|
| `GET /mozek?q=slova&klient=slug&navrhy=1` | hledání, nejlepší první (kmen slova: „reportu“ najde „Reporty“), `navrhy=1` přidá neschválené |
| `GET /mozek/seznam` | všechno platné a navržené jen s nadpisem (kontrola duplicit) |
| `GET /mozek/zdroje/{id}`, `…/soubory/{i}` | text a soubory podkladů |
| `POST /mozek/navrhy` | `{"zdroj": id?, "poznatky": [{vrstva, klient?, nadpis, text, stitky?, puvod?, nahrazuje?}]}` |

Příkaz `taveo mozek "co hledám" [slug]` a `taveo mozek-navrh firma|klient|postup "nadpis" [slug] < text.md`
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
