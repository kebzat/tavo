# Přehled spolupráce pro klienta

Stránka `/klient/{nazev-klienta}-{6 náhodných znaků}` (například
`/klient/svet-cejlonu-k7f2q9`) pro klienty na měsíční paušál. Náhodná část chrání
stránku před uhodnutím, adresa se po přejmenování klienta nemění. Starý odkaz
se 40znakovým tokenem přesměruje (301) na novou adresu. Ukazuje, za co platí,
kolik hodin jsme odpracovali, co je hotové, co čeká na klienta a co je v plánu.
Odpovídá na tři otázky z BRAND-STRATEGY §5: co bylo dokončeno, co jsme zjistili,
co následuje.

## Kde se to plní

Nástroje → Checklisty → **Klienti** → klient:

| Co | Kde | Na přehledu |
|---|---|---|
| Paušál po oblastech | sekce Pravidelná spolupráce → Paušál | dlaždice s cenou a hodinami, součet v hlavičce |
| Začátek spolupráce | Spolupráce od | první měsíc v přepínači a v grafu hodin |
| Zapnutí odkazu | Přehled vidí klient | bez zapnutí 404, přihlášený vidí náhled vždy |
| Úkoly | záložka Úkoly | Co jsme udělali, Čeká na vás, Co následuje |
| Cíl a komentář měsíce | záložka Měsíce | hlavička, sekce Co jsme zjistili |
| Hodiny | Reklamy → Hodiny nebo Zapsat čas | sečtené po úkolech a oblastech |

Při zápisu času se vybírá úkol (jde ho rovnou založit tlačítkem +). Oblast se pak
bere z úkolu. Čas bez úkolu se v seznamu úkolů neukazuje, klient ho vidí jen
v čerpání paušálu nahoře (rozhodnutí z 8. 10. 2026).

## Co klient vidí a co ne

- Jen **fakturovatelný** čas. Nefakturovatelné zápisy (oprava naší chyby,
  interní porada) se nezobrazují ani nepočítají.
- Úkol: název, popis pro klienta, stav, oblast, hodiny v měsíci a kdo na něm
  pracoval. **Interní poznámka** úkolu ani **popis zápisu času** ven nejdou.
- „Čeká na vás“ a „Co následuje“ jen u aktuálního měsíce. Minulé měsíce ukazují
  jen tehdejší práci.
- V plánu nejsou úkoly, na kterých se tento měsíc už pracovalo, ty jsou v „Co jsme
  udělali“. Úkol s měsícem v minulosti, který není hotový, spadne do aktuálního
  měsíce.
- Dokumenty: veřejné audity, checklisty a reporty reklam téhož klienta.

## Paušál a fakturace

`App\Support\Ads\Billing` bere paušál z `client_retainers`, když je klient má,
jinak z nastavení reklam. Přehled i Fakturace proto ukazují stejná čísla.

**Změna částky do budoucna** (teď 30 000, od ledna 10 000) = další řádek téže
oblasti: starému Do, novému Od. **Předběžně** = zatím nedomluvené, počítá se jen
do CRM → Výhled, nefakturuje se a klient ho v přehledu nevidí. Když na předběžný
paušál dojde měsíc, Fakturace ho ukáže oranžově k potvrzení.
Výhled jde stejně jako Fakturace přepnout na vývoj (Tom) nebo marketing (Pavel),
přihlášenému se otevře jeho oblast podle `users.billing_area`.

**Klient vidí plán ceny** (`clients.dashboard_shows_pricing`, výchozí vypnuto)
přidá do přehledu sekci Cena spolupráce: paušál od tohoto měsíce po obdobích,
i s předběžnými částkami (`App\Support\RetainerSchedule`). Období se berou z Od
a Do u paušálu, takže i stálá cena „říjen 2026 – březen 2027“ se ukáže. Dva řádky téže oblasti platné ve stejném měsíci formulář
neuloží, sečetly by se.

**Hodin v paušálu prázdné** = hodiny jen ukazujeme, nad rámec se nic neúčtuje.
Jakmile se u některé oblasti vyplní, hodiny nad součet se počítají sazbou
z nastavení reklam.

Převod nevyčerpaných hodin do dalšího měsíce přehled neumí a neukazuje. Zakladatelé
ho zatím nemají domluvený (BRAND-STRATEGY §7.4).

## Implementace

| Soubor | K čemu |
|---|---|
| `App\Support\ClientDashboard` | skládá data za měsíc, šablona jen vypisuje |
| `App\Http\Controllers\ClientDashboardController` | adresa (`clients.dashboard_slug`, starý token přesměruje), náhled pro přihlášené, `?mesic=2026-10` |
| `resources/views/client-dashboard/show.blade.php` | stránka, layout sdílených dokumentů (noindex) |
| `App\Models\ClientRetainer`, `ClientTask`, `ClientMonth` | paušál, úkoly, měsíce |
| `App\Enums\WorkArea`, `TaskStatus` | oblasti (vývoj webu, marketing) a stavy úkolu |

Token vzniká až při prvním zobrazení odkazu (`Client::dashboardPreviewUrl()`),
ne při založení klienta. Starší datové migrace zakládají klienty dřív, než sloupec
existuje.

Texty na stránce jdou přepsat v Nastavení → Statické texty pod klíči `client_dashboard.*`.

## Ukázka

Klient **Ukázka: Bylinky z Podkrkonoší** je vymyšlený e-shop se zapnutým přehledem.
Jeho odkaz jde poslat komukoli, kdo chce vidět, jak přehled vypadá. Odkaz najdete
v nástrojích u klienta.

Data se vztahují k aktuálnímu měsíci: dva měsíce historie, rozpracovaná práce,
„čeká na vás“ a plán dopředu. Plánovač je 1. v měsíci v 5:00 obnoví
(`clients:dashboard-demo --refresh`), odkaz zůstává stejný.

```bash
./bin/art clients:dashboard-demo           # založí nebo hned obnoví
./bin/art clients:dashboard-demo --remove  # smaže, plánovač ji už nevrátí
```

Ukázka má paušál 15 000 Kč, takže se zobrazí i ve Fakturaci, stejně jako ukázkoví
klienti u reklam.
