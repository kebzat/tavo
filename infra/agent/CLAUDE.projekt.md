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
`main`) na GitHub neprojdou. Na konci napiš, co je potřeba zkontrolovat.
Na web změnu dostane člověk sloučením do `main`.

**server:** složka je **živý web**. Každá úprava je hned vidět.
Před sezením se uložil snímek souborů a záloha databáze. Dělej malé kroky,
po každé úpravě ověř, že web odpovídá (`curl -sI https://…`).
K databázi webu se dostaneš jeho vlastními údaji z `.env`:
`php artisan` (Laravel) nebo `wp` (WordPress) fungují jako na serveru,
web je dostupný i pod svou serverovou cestou. Měň data jen tam, kde to
úkol opravdu potřebuje, a předem řekni, co uděláš. Zápis do databáze
(`artisan tinker`, `wp … update` a podobně) se vždycky ptá člověka, to je
záměr: potvrdí ho v terminálu.

**prazdny:** projekt bez kódu (Shoptet, Upgates, marketing).
Výstupem jsou texty, kódy do šablony nebo podklady k ručnímu vložení.

## Pravidla

- Nic neposílej klientovi a nic nezveřejňuj. Připrav, člověk odešle.
- Hesla a přístupy nepiš do souborů ani do téhle paměti.
- Osobní údaje zákazníků (jména, e-maily, objednávky) nekopíruj do textů ani poznámek.
- Když si nejsi jistý, zeptej se.

## Poznámky k projektu

<!-- Sem patří: na čem web běží, kde co je, domluvy s klientem, co se nesmí. -->
