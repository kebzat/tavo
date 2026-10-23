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

**server:** složka je **živý web**. Každá úprava je hned vidět.
Před sezením se uložil snímek souborů a záloha databáze. Dělej malé kroky,
po každé úpravě ověř, že web odpovídá (`curl -sI https://…`).
K databázi webu se dostaneš jeho vlastními údaji z `.env`:
`php artisan` (Laravel) nebo `wp` (WordPress) fungují jako na serveru,
web je dostupný i pod svou serverovou cestou. Měň data jen tam, kde to
úkol opravdu potřebuje. Zápis do databáze, migrace i mazání souborů smíš
bez ptaní: těsně před nimi se sama uloží záloha databáze a snímek souborů.
Po změně ověř na webu, že funguje, a stručně napiš, co se změnilo.

**Předání (karta „Co agent změnil“ v nástrojích):** než začneš web měnit,
založ složku `/projekty/{{PROJEKT}}/predani/<krátký-popis>/` a zapiš do ní čas:
`date -Is > /projekty/{{PROJEKT}}/predani/<krátký-popis>/zacatek`. Tlačítko Vrátit pak
vrátí web do stavu z téhle chvíle. Po dokončení do ní dej `popis.md` (první
řádek `# Nadpis`, lidsky co se změnilo, „Co zkontrolovat“) a snímky
z prohlížeče (`01-pocitac.png`, `02-telefon.png`…). Jedna složka = jedna úprava.

**prazdny:** projekt bez kódu (Shoptet, Upgates, marketing).
Výstupem jsou texty, kódy do šablony nebo podklady k ručnímu vložení.

**Předání (karta „Hotové podklady“ v nástrojích):** hotové texty a kódy dej
do `/projekty/{{PROJEKT}}/predani/<krátký-popis>/`: `popis.md` (první řádek `# Nadpis`, pak
**přesně kam to v administraci vložit**) a každý kód nebo text jako vlastní
soubor (`paticka.html`, `styl.css`, `popis-produktu.txt`), náhled jako `*.png`.
V nástrojích u nich bude tlačítko Zkopírovat.

## Pravidla

- Nic neposílej klientovi a nic nezveřejňuj. Připrav, člověk odešle.
- Hesla a přístupy nepiš do souborů ani do téhle paměti.
- Osobní údaje zákazníků (jména, e-maily, objednávky) nekopíruj do textů ani poznámek.
- Když si nejsi jistý, zeptej se.

## Poznámky k projektu

<!-- Sem patří: na čem web běží, kde co je, domluvy s klientem, co se nesmí. -->
