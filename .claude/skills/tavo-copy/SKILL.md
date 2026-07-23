---
name: tavo-copy
description: Psaní a revize českých textů pro web TAVO — tón hlasu, zákaz AI manýr, pravidla pro SEO. Použij vždy, když se píše nebo upravuje jakýkoliv text, který uvidí návštěvník webu (settings, seeder, Blade, meta popisky, e-maily).
---

# Texty pro TAVO

TAVO jsou dva lidé z Hradce Králové. Pavel Včeliš dělá marketing a výkonnostní
reklamu (8+ let, hlavně Meta, e-shopy, lead generation), Tom staví weby a e-shopy
(Laravel, WordPress/WooCommerce, Shoptet, Shopify, Upgates, vlastní CMS).
Píšeme jejich jménem, v první osobě množného čísla, česky, tykání ne.

## Kdo to čte

Majitel malé nebo střední firmy ve východních Čechách. Nemá čas, nezná pojmy
jako „konverzní poměr" a už jednou zaplatil za web, ze kterého nic nemá.
Chce vědět dvě věci: umíte to, a co to pro mě znamená.

## Tón

Mluvíme jako člověk, který tu práci opravdu dělá. Krátké věty. Konkrétní čísla,
jména platforem, města. Když něco neumíme nebo nevíme, řekneme to.
Trocha suchého humoru je v pořádku, nadsázka a chvástání ne.

## Zakázané obraty (tohle prozradí AI na první pohled)

**Interpunkce**

- **Pomlčka `—` v běžném textu je zakázaná.** Nahraď tečkou, čárkou, dvojtečkou
  nebo závorkou. Výjimka: odrážka jako grafický prvek v šabloně a rozsah („8–10 týdnů").
- Žádné `…` uprostřed věty místo rozmyšlené formulace.
- Uvozovky česky: `„ "`. V citacích klienta ano, jako ironické zdůraznění ne.

**Větné konstrukce**

- „Ne X, ale Y." / „Nejen X, ale i Y." / „X, ne Y." Jednou za stránku maximálně,
  ideálně vůbec.
- Trojice se stejným rytmem: „Rychlé, měřitelné, připravené na růst."
  Tři odrážky ve stejné délce a stejné stavbě věty jsou taky trojice.
- Antiteze v nadpisu: „Každý si odvede svůj kus. Za výsledek neručí nikdo."
- Řečnická otázka následovaná odpovědí.
- Věta začínající „Právě proto", „A přesně tam", „A to je přesně ten důvod".
- Pointa na konci odstavce, která jen zopakuje, co bylo řečeno výš.

**Slova a fráze**

Komplexní řešení, na míru vašim potřebám, posuneme na další úroveň,
v dnešní digitální době, synergie, holistický přístup, klíčem k úspěchu,
nastavíme procesy, s marketingem v hlavě, jeden funkční celek, DNA značky,
digitální svět, „nejsme jen další agentura", proaktivní, robustní, efektivní.

**Struktura**

- Odrážky ve stejné délce a se stejnou gramatickou stavbou. Jedna má být
  krátká, jedna dlouhá, jedna může být větný fragment.
- Každá sekce nadpis + perex + tři body. Střídej.
- Dokonalá paralelnost mezi sekcemi (Marketing přivede / Web přesvědčí /
  Data ukážou / Rozvoj zlepší). Když už schéma použiješ, rozbij ho aspoň
  v jednom místě.

## Co dělat místo toho

- **Konkrétní místo obecného.** Ne „výrazně jsme zrychlili web", ale
  „načtení z pěti sekund na jednu a půl".
- **Jména.** Hradec Králové, Chrudim, Shoptet, Meta, WooCommerce, Pavel, Tom.
- **Čísla, která existují.** Když číslo nemáme, nevymýšlíme ho a raději ho
  vynecháme. Vymyšlená metrika na webu je lež, kterou klient přečte dřív než Google.
- **Střídej délku vět.** Po dvou dlouhých souvětích přijde věta o třech slovech.
- **Piš, co se stane.** „Ozveme se do dvou pracovních dnů" je lepší než
  „ozveme se co nejdříve".
- **Přiznej omezení.** „Když zjistíme, že vám web nepomůže, řekneme to."
  Tohle AI nepíše a lidem to funguje.

## SEO pravidla

Cílíme na **Hradec Králové a Královéhradecký kraj**, sekundárně celou ČR.

Hlavní dotazy, které mají mít vlastní stránku a musí padnout v H1 nebo první
odstavec, přirozeně a jednou, ne třikrát:

| Stránka | Hlavní dotaz |
|---|---|
| `/sluzby/tvorba-webovych-stranek` | tvorba webových stránek Hradec Králové |
| `/sluzby/tvorba-eshopu` | tvorba e-shopu, Shoptet / WooCommerce / Shopify / Upgates |
| `/sluzby/reklama-a-marketing` | správa reklamy, PPC, Facebook a Instagram reklama |
| `/sluzby/sprava-a-rozvoj-webu` | správa webu, údržba webu |

Pravidla:

- Jeden `<h1>` na stránku, obsahuje hlavní dotaz v přirozeném tvaru.
- Title do 60 znaků včetně `| TAVO`, description 140–160 znaků a musí obsahovat
  důvod ke kliknutí, ne výčet klíčových slov.
- Lokalitu zmiňuj tam, kde dává smysl (kontakt, o nás, úvod služby), ne v každém
  odstavci. „Tvorba webových stránek Hradec Králové Pardubice Chrudim" je spam.
- Vnitřní prolinkování: každá stránka služby odkazuje aspoň na jednu referenci
  a na jednu další službu.
- Text na stránce služby aspoň 600 slov, ale jen pokud má co říct. Vata škodí víc
  než krátká stránka.
- Strukturovaná data: `ProfessionalService` s adresou v HK na všech stránkách,
  `Service` na detailu služby, `BreadcrumbList` na podstránkách.

## Kontrola před odevzdáním

1. `grep -c '—'` v nových textech vrátí nulu.
2. Přečti text nahlas. Kde se zadrhneš, tam je to psané pro algoritmus.
3. Najdi v textu tři konkrétní fakta (číslo, jméno, místo). Když tam nejsou,
   text je vata.
4. Zakryj logo. Dal by se ten text nalepit na web konkurence beze změny?
   Pak je moc obecný.
