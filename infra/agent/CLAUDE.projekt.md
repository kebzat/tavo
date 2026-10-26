# Projekt {{PROJEKT}}

Paměť projektu. Leží mimo kód, na web se nedostane. Vidí ji každý, kdo
s projektem pracuje (Tom, Pavel), proto sem po každém úkolu zapiš, co
se rozhodlo a co by měl vědět ten, kdo přijde po tobě.

- Typ: {{TYP}}
- Zdroj: {{ZDROJ}}
- Kód: `kod/` (pracovní složka sezení)

## Jak se tu pracuje (podle typu výš)

**git:** pracuj ve větvi pojmenované `agent/<krátký-popis>`. Hotovou práci
pošli `git push origin agent/<krátký-popis>`. Push jde do schránky na serveru
a odtud se do minuty sám dostane na GitHub. Jiné větve než `agent/…` (hlavně
`main`) na GitHub neprojdou. Na web ji pošle člověk tlačítkem **Zveřejnit**
v nástrojích (karta Hotová práce), často marketér, který kód nečte. Proto ke
každé hotové větvi připrav **předání** do `../predani/<krátký-popis>/`:

- `popis.md`: první řádek `# Nadpis` (co se změnilo, lidsky, bez kódu), pak
  pár vět, co je jinak, a „Co zkontrolovat“. Bez technických detailů.
- snímky z prohlížeče (`prohlizec`) jako `01-pocitac.png`, `02-telefon.png`…
  Celé stránky, kde je změna vidět, i stav před kliknutím a po něm. Název
  souboru se ukáže jako popisek.

Když na větvi pokračuješ, předání uprav, ať odpovídá poslednímu pushi.
Zveřejnit jde jen se zelenými testy, takže je před pushem pusť.

**server:** živý web je ve složce `kod/`, ale **nepracuj v ní**. Pracuješ
v **kopii** `/projekty/{{PROJEKT}}/kopie` (konverzace se v ní otevírá sama) s vlastní
databází. Na živý web ji pošle člověk tlačítkem Zveřejnit v nástrojích.

- Náhled kopie: v kopii `php artisan serve --host=127.0.0.1 --port=8090`,
  prohlížeč (`prohlizec`) na http://127.0.0.1:8090.
- Změny obsahu v databázi (texty, nastavení) piš jako **migraci**
  (`database/migrations`, nastavení `database/settings`), ne přes tinker:
  Zveřejnit spustí migrace na živé databázi, nic jiného z databáze kopie
  se nepřenese. Objednávky a zprávy na živém webu tak zůstanou.
- K hotové práci připrav předání do `/projekty/{{PROJEKT}}/predani/<krátký-popis>/`:
  `popis.md` (první řádek `# Nadpis`, lidsky co se změnilo, „Co zkontrolovat“)
  a snímky z náhledu kopie (`01-pocitac.png`, `02-telefon.png`…).
- Výjimka: úlohy z ClickUpu jdou rovnou na živý web, řekne ti to zadání.
- Dokud složka `kopie` neexistuje, pracuj postaru přímo v `kod/` (živý web,
  před sezením se uložil snímek a záloha databáze).

**prazdny:** projekt bez kódu (Shoptet, Upgates, marketing).
Výstupem jsou texty, kódy do šablony nebo podklady k ručnímu vložení.

**Předání (karta „Hotové podklady“ v nástrojích):** hotové texty a kódy dej
do `/projekty/{{PROJEKT}}/predani/<krátký-popis>/`: `popis.md` (první řádek `# Nadpis`, pak
**přesně kam to v administraci vložit**) a každý kód nebo text jako vlastní
soubor (`paticka.html`, `styl.css`, `popis-produktu.txt`), náhled jako `*.png`.
V nástrojích u nich bude tlačítko Zkopírovat. Po „Hotovo“ předání i se soubory zmizí.

**Rozbor nebo plán ke čtení** (pro kolegy nebo klienta) nedávej do předání, ale do archivu
klienta jako `.html` (vzhled TAVEO, písma jen z Bunny Fonts, bez externích skriptů):
`taveo archiv-nahrat <slug> "Název" --druh vysledky --popis "…" soubor.html`. Odpověď vrátí
`stranka`: stálou adresu `/stranka/…`, kterou otevře každý s odkazem i bez přihlášení. Pošli ji
člověku. Do stránky proto nepiš nic, co nesmí vidět klient (marže jen po domluvě).

## Pravidla

- Nic neposílej klientovi a nic nezveřejňuj. Připrav, člověk odešle.
- Hesla a přístupy nepiš do souborů ani do téhle paměti.
- Osobní údaje zákazníků (jména, e-maily, objednávky) nekopíruj do textů ani poznámek.
- Když si nejsi jistý, zeptej se.

## Poznámky k projektu

<!-- Sem patří: na čem web běží, kde co je, domluvy s klientem, co se nesmí. -->
