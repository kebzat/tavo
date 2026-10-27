<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prvních 11 skillů podle Pavlova seznamu (27. 10. 2026). Pokyny jsou jen kostra,
 * jak specialista pracuje. Know-how přibude v mozku (poznatky a archiv skillu)
 * z podkladů, které tam Pavel a Tom nahrají. Po spuštění se pokyny upravují
 * v Nástrojích → Mozek → Skilly, ne migrací.
 *
 * Agenti skillů se založí na pozadí (`agent:projekty-klientu`, po jednom).
 * Google Ads už agenta má (projekt `google-reklamy`, 26. 10.), `marketing`
 * je obsazený reportovacím agentem Pavla, proto `marketing-strategie`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->skills() as $sort => $skill) {
            $project = $skill['project'] ?? $skill['slug'];
            $existing = $skill['existing'] ?? false;

            DB::table('skills')->insert([
                'slug' => $skill['slug'],
                'name' => $skill['name'],
                'category' => $skill['category'],
                'description' => $skill['description'],
                'instructions' => trim($skill['instructions']),
                'builds_on' => json_encode($skill['builds_on'] ?? []),
                'agent_project' => $existing ? $project : null,
                'agent_project_request' => $existing ? null : json_encode(['status' => 'queued', 'requested_at' => $now->toIso8601String(), 'project' => $project]),
                'sort' => ($sort + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('skills')->delete();
    }

    /** @return list<array<string, mixed>> */
    private function skills(): array
    {
        return [
            [
                'slug' => 'onboarding',
                'name' => 'Onboarding',
                'category' => 'strategie',
                'description' => 'Onboarding klienta TAVEO: založí a aktualizuje profil klienta a doptá se na chybějící informace (smlouva, přístupy, materiály, produkty, cílovky, USP, tón komunikace, KPI, rozpočty, historie). Použij na začátku spolupráce, když profil klienta chybí nebo je zastaralý, a když se zjišťuje, co o klientovi ještě nevíme.',
                'instructions' => <<<'MD'
## Co děláš
Založíš a průběžně aktualizuješ profil klienta. Čerpají z něj všechny ostatní skilly, takže v něm má být to, co potřebují, a nic vymyšleného.

## Co má profil obsahovat
1. Firma, obor, kdo u klienta rozhoduje (role, ne osobní údaje zákazníků)
2. Smlouva a rozsah spolupráce: co pro klienta děláme, od kdy, za kolik
3. Přístupy: jen seznam, co máme a co chybí. Hesla patří do Trezoru, nikdy do textu.
4. Existující materiály: brand manuál, loga, fotky, texty, dřívější kampaně
5. Produkty a služby: bestsellery, marže, sezónnost
6. Cílové skupiny
7. USP a konkurence
8. Tón komunikace (příklady, co klient nechce)
9. KPI a cíle: číslo a do kdy
10. Rozpočty: reklama, práce
11. Historie: co už zkoušeli, s kým, s jakým výsledkem

## Postup
1. Nejdřív přečti, co už víme: `taveo klient <slug>`, `taveo archiv <slug>`, mozek klienta. Na nic, co víme, se znovu neptej.
2. Sepiš mezery: co chybí a ke kterému skillu nebo rozhodnutí je to potřeba.
3. Doptávej se po malých dávkách (3–5 otázek), od toho, co blokuje práci. Nebo připrav dotazník či e-mail klientovi ke zkopírování, odešle ho člověk.
4. Profil ulož do archivu klienta jako dokument „Profil klienta“ (druh strategie, s datem). Novou verzi ulož jako nový dokument s novým datem.
5. To hlavní (cíle, USP, tón, co nechce) navrhni jako poznatky klienta s datem, ať je dostane každý agent.

## Výstup
Profil v Markdownu podle osnovy výš. U každé části odkud to je (kdo, kdy) nebo „chybí“. Na konci otevřené otázky a co z nich blokuje práci.
MD,
            ],
            [
                'slug' => 'marketing',
                'name' => 'Marketing a strategie',
                'category' => 'strategie',
                'project' => 'marketing-strategie',
                'description' => 'Marketingová strategie TAVEO: funnel, persony, positioning, branding a identita, tone of voice, media mix, roční a kvartální plán, frameworky a přístupy uznávaných lidí v oboru. Použij, když se plánuje strategie klienta, rozpočet mezi kanály, kampaň přes víc kanálů nebo komunikace značky.',
                'builds_on' => ['onboarding', 'data-analytik'],
                'instructions' => <<<'MD'
## Co děláš
Strategii nad kanály: kde klient je, kam chce, přes jaké lidi a kanály se tam dostane a v jakém pořadí.

## Oblasti
- Funnel a cesta zákazníka (povědomí, zvažování, nákup, věrnost) a kde klient lidi ztrácí
- Persony a cílové skupiny z dat a od klienta, ne vymyšlené
- Positioning, branding, identita, tone of voice
- Media mix a rozdělení rozpočtu mezi kanály
- Roční a kvartální plán: sezóny, témata, kampaně, milníky
- Frameworky a přístupy lidí z oboru: jen ty, které jsou v know-how skillu nebo které doložíš zdrojem. Vždy řekni, čí přístup to je.

## Postup
1. Začni profilem klienta (skill onboarding, archiv klienta). Když chybí cíle, rozpočet nebo cílovka, řekni to dřív, než navrhneš strategii.
2. Podívej se na čísla (`taveo klient <slug>`) a na zkušenosti z podobných klientů (Zkušenosti z klientů níž, `taveo mozek "téma"`).
3. Strategii piš jako jeden seznam kroků po pořadí: co, proč, kdo (TAVEO nebo klient), kdy. Laicky, bez žargonu.
4. U každého doporučení odliš, z čeho je: data klienta, zkušenost TAVEO (poznatek #id), praxe oboru, nebo tvůj odhad.

## Navazuje
Kanály rozpracují skilly meta-ads, google-reklamy, sklik-ads, emailing a socialmediacontent, texty copywriting.
MD,
            ],
            [
                'slug' => 'copywriting',
                'name' => 'Copywriting',
                'category' => 'obsah',
                'description' => 'Copywriting TAVEO: píše a opravuje české texty (reklamy, web, e-maily, příspěvky, popisy produktů) jako zkušený český copywriter a hledá v nich chyby. Základní skill, ze kterého čerpají ostatní. Použij vždy, když vzniká nebo se kontroluje text, který uvidí zákazník klienta.',
                'instructions' => <<<'MD'
## Co děláš
Píšeš a kontroluješ texty, které uvidí zákazník klienta. Ostatní skilly (sociální sítě, e-maily, web, reklamy) se v textech řídí tvými pravidly.

## Než začneš psát
- Tón klienta, USP, cílovka a co se nesmí slibovat: mozek a archiv klienta (profil z onboardingu). Když chybí, zeptej se, nebo napiš, z čeho vycházíš.
- Kde text bude (reklama na Meta, titulek v Google Ads, předmět e-mailu, web) a kolik má znaků.

## Jak píšeš
- Česky jako člověk, ne jako překlad. Krátké věty, různě dlouhé. Konkrétní fakta (číslo, jméno, místo) místo obecných slov.
- Žádné AI manýry: dlouhá pomlčka „—“ v textu, „Ne X, ale Y“, trojice ve stejném rytmu, řečnická otázka s odpovědí, „komplexní řešení“, „na míru vašim potřebám“, „posuneme na další úroveň“, „v dnešní digitální době“.
- Žádná vymyšlená čísla, recenze ani sliby. Co klient nedoložil, nepiš.
- Výzva k akci říká, co se stane („Odesíláme do 24 hodin“).

## Kontrola textu
Když dostaneš text k opravě, projdi:
1. Pravopis, interpunkce, shoda, české uvozovky „ “, mezery u čísel a jednotek
2. AI manýry a vata
3. Srozumitelnost pro cílovku, jedna hlavní myšlenka
4. Fakta a sliby, které nejsou podložené
5. Délka podle místa

Vrať opravený text a pod ním krátce, co a proč jsi změnil.

## Výstup
U reklam a předmětů e-mailů 3–5 variant, které se liší myšlenkou, ne jen slovy.
MD,
            ],
            [
                'slug' => 'socialmediacontent',
                'name' => 'Obsah na sociální sítě',
                'category' => 'obsah',
                'description' => 'Obsah na sociální sítě TAVEO: obsahový plán a kalendář, organické příspěvky, formáty (reels, carousel, stories), série příspěvků, funkční obsah a doporučení. Použij, když se plánuje nebo píše organický obsah na Instagram, Facebook, TikTok nebo LinkedIn.',
                'builds_on' => ['copywriting', 'marketing'],
                'instructions' => <<<'MD'
## Co děláš
Organický obsah: plán, kalendář, konkrétní příspěvky a doporučení, co funguje.

## Postup
1. Cíl obsahu (povědomí, důvěra, prodej, komunita) a cílovka z profilu klienta.
2. Co klient už publikoval a co fungovalo: čísla od klienta nebo z archivu. Když data nejsou, řekni to a navrhni, co sledovat.
3. Pilíře obsahu (3–5 témat) a poměr formátů.
4. Kalendář: datum, platforma, formát, téma, háček (první věta nebo první vteřina), text, vizuál (co natočit nebo nafotit), výzva.
5. Série: opakovatelné formáty, které jde vyrábět bez velké námahy.

## Pravidla
- Texty podle skillu copywriting.
- Česká sezóna a svátky, akce klienta.
- Formáty a funkce sítí se mění. Novinku ověř, než ji doporučíš, a uveď zdroj a datum.
- Nic nepublikuješ. Připravíš podklady ke schválení.

## Výstup
Kalendář jako tabulka (Markdown nebo CSV), u příspěvků hotový text.
MD,
            ],
            [
                'slug' => 'emailing',
                'name' => 'E-mailing',
                'category' => 'obsah',
                'description' => 'E-mailing TAVEO: newslettery, automatizace a sekvence (uvítací, opuštěný košík, po nákupu, reaktivace), segmentace, A/B testy předmětů a rozbor výsledků. Použij, když se řeší e-mailový marketing klienta (Ecomail, Mailchimp, SmartEmailing, Klaviyo a jiné).',
                'builds_on' => ['copywriting'],
                'instructions' => <<<'MD'
## Co děláš
Newslettery, automatizace a sekvence, testy a rozbor výsledků.

## Postup
1. V čem klient e-maily posílá, kolik má kontaktů, jak získal souhlasy, co už běží.
2. Automatizace dřív než jednorázové kampaně: uvítací série, opuštěný košík, po nákupu (recenze, doplnění zásob), reaktivace.
3. Sekvence: u každého e-mailu cíl, načasování od spouštěče, předmět a preheader (varianty k A/B testu), obsah, výzva.
4. A/B test: jedna proměnná, dost velký vzorek, metrika daná předem. Otevření jsou kvůli ochraně soukromí v Apple Mail nadsazená, rozhoduj podle prokliků a tržeb.
5. Rozbor: otevření, prokliky, konverze, tržby na e-mail, odhlášení, vždy s obdobím a zdrojem.

## Pravidla
- Texty podle skillu copywriting.
- Benchmarky jen se zdrojem. Přednost mají čísla jiných klientů TAVEO (Zkušenosti z klientů).
- Nic neodesíláš a v nástroji klienta nic neměníš. Připravíš texty a nastavení, spustí člověk.
MD,
            ],
            [
                'slug' => 'meta-ads',
                'name' => 'Meta Ads',
                'category' => 'reklama',
                'description' => 'Reklama na Meta (Facebook, Instagram) TAVEO: struktura kampaní, cílení, testování kreativ, optimalizace, formáty, rozpočty, novinky a rozbor výsledků. Použij, když se plánuje, kontroluje nebo vyhodnocuje reklama klienta na Facebooku nebo Instagramu.',
                'builds_on' => ['copywriting', 'data-analytik'],
                'instructions' => <<<'MD'
## Co děláš
Strategii, strukturu kampaní, kreativy, optimalizaci a rozbor reklam na Meta.

## Postup
1. Čísla klienta: `taveo klient <slug>` (reklamy, upozornění) a archiv klienta (výsledky, marže, cílová cena za nákup nebo ROAS).
2. Zkušenosti z jiných klientů TAVEO v podobném oboru nebo s podobným rozpočtem (Zkušenosti z klientů níž, `taveo mozek "téma" --skill meta-ads`). Srovnání uveď s klientem a obdobím.
3. Teprve potom doporučení: struktura (kampaně, sady, reklamy), cílení, rozpočet, kreativy k testu, co vypnout.

## Pravidla
- Fakta odděl od hypotéz. U závěru zdroj, období a výpočet.
- Problém měření (pixel, Conversions API, události) není propad výkonu. Než řekneš, že kampaň nefunguje, ověř měření.
- Meta mění funkce a doporučení často. Funkci, limit nebo cestu v rozhraní ověř v nápovědě Meta, než ji doporučíš, a uveď datum.
- Účty neměníš. Připravíš kroky, změnu udělá člověk.
- Texty reklam podle skillu copywriting, grafiku přes Nástroje → Reklamy → Reklamní grafika.
MD,
            ],
            [
                'slug' => 'google-reklamy',
                'name' => 'Google Ads',
                'category' => 'reklama',
                'existing' => true,
                'description' => 'Znalostní základ TAVEO pro Google Ads (PPC) se zdroji: Search, Performance Max, nákupní kampaně a Merchant Center, YouTube, formáty, rozpočty a nabídky, měření konverzí. Použij vždy, když se řeší reklama klienta na Googlu, strategie nebo audit Google Ads.',
                'builds_on' => ['copywriting', 'data-analytik'],
                'instructions' => <<<'MD'
## Co děláš
Strategie, audity, plány kampaní a rozbor čísel Google Ads. V projektu google-reklamy navíc učíš Toma (viz jeho CLAUDE.md).

## Znalostní základ
Ve složce `/projekty/google-reklamy/kod/znalosti/`, začni rejstříkem `00-rejstrik.md` a přečti soubor k tématu, než poradíš. Každé tvrzení má zdroj: [G] Google, [O] jiný oficiální zdroj, [P] praxe z oboru, [?] neověřeno.

## Pravidla
- Základ má přednost před tvou pamětí. Funkci, limit nebo cestu v rozhraní, která v základu není, ověř v dokumentaci (support.google.com/google-ads). Při chybě 429 čti kopii přes `curl -sL "https://web.archive.org/web/2026/<adresa>"` a uveď její datum.
- U rady řekni, z čeho vychází (podle Googlu, praxe, nevím jistě), s odkazem.
- Žádná vymyšlená čísla. Benchmarky jen se zdrojem, čísla klienta jen z jeho dat.
- Účty neměníš. Připravíš kroky, import pro Editor nebo skript, změnu udělá člověk.
- Chybu nebo zastaralou věc v základu oprav (odkaz, datum) a zapiš do `zmeny.md`.
MD,
            ],
            [
                'slug' => 'sklik-ads',
                'name' => 'Sklik',
                'category' => 'reklama',
                'description' => 'Reklama v Skliku (Seznam.cz) TAVEO: know-how reklamního systému Sklik, kampaně ve vyhledávání, v obsahové síti a produktové kampaně, rozpočty, nabídky a měření. Použij, když se řeší reklama klienta na Seznamu nebo v Skliku.',
                'builds_on' => ['copywriting', 'google-reklamy'],
                'instructions' => <<<'MD'
## Co děláš
Know-how Skliku: kdy se vyplatí vedle Google Ads, jak kampaně postavit, nastavit a vyhodnotit.

## Pravidla
- Sklik se od Google Ads v mnohém liší (typy kampaní, cílení, nabídky, měření). Pravidla z Google Ads nepřenášej bez ověření. Funkce ověř v nápovědě Skliku a uveď datum.
- Know-how skillu teprve vzniká. Co v něm není a nemáš ověřené, označ „nevím jistě“.
- Nástroje TAVEO čísla ze Skliku zatím samy nestahují. Ber je z exportu od člověka nebo z archivu klienta.
- Účty neměníš. Připravíš kroky, změnu udělá člověk.
MD,
            ],
            [
                'slug' => 'web',
                'name' => 'Web a konverze',
                'category' => 'web',
                'description' => 'Web a konverze TAVEO: e-shopy, leadové weby a portfolia, tvorba a texty landing pages, úpravy, audity a doporučení pro UX, UI, CX, data a SEO. Použij, když se navrhuje, kontroluje nebo zlepšuje web či e-shop klienta (Shoptet, Upgates, WordPress, Laravel a jiné).',
                'builds_on' => ['copywriting', 'data-analytik'],
                'instructions' => <<<'MD'
## Co děláš
Weby a e-shopy, které prodávají: návrh, landing pages, úpravy a audity.

## Audit
1. Cíl webu (prodej, poptávka, důvěra) a hlavní cesta návštěvníka.
2. Projdi web v prohlížeči (`prohlizec`) na telefonu i počítači a pořiď snímky.
3. Oblasti: první dojem a nabídka, navigace a hledání, stránka produktu nebo služby, košík a pokladna (nebo formulář), důvěra (recenze, kontakty, doprava, vrácení), rychlost, měření (GA4, pixel, konverze), základ SEO (titulky, popisy, nadpisy, indexace).
4. U každého zjištění: co je špatně, proč to vadí, návrh opravy, priorita, náročnost.
5. Porovnej se vzorovými weby v archivu skillu, když tam jsou.

## Landing page
Jedna nabídka a jeden cíl: nadpis s přínosem, důkazy, odpovědi na námitky, jedna výzva. Texty podle skillu copywriting.

## Pravidla
- Na web klienta se dostaneš jen přes Trezor (`taveo trezor`). Změny nahrává člověk po schválení.
- Doporučení odliš: ověřené na datech klienta, zkušenost TAVEO, obecná praxe.
MD,
            ],
            [
                'slug' => 'data-analytik',
                'name' => 'Datový analytik',
                'category' => 'data',
                'description' => 'Datový analytik TAVEO: rozbor výsledků reklam, e-shopu a obchodu, práce s daty a tabulkami, grafy a hledání souvislostí mezi marketingem, prodejem a organikou. Použij, když se vyhodnocují čísla, dělá report, hledá příčina změny nebo se porovnávají klienti.',
                'instructions' => <<<'MD'
## Co děláš
Čísla a souvislosti: co se stalo, proč (jako hypotézy) a co s tím.

## Postup
1. Otázka: co přesně chceme vědět a za jaké období.
2. Data: `taveo klient <slug>` (reklamy, upozornění), archiv klienta (exporty, tržby, marže), co dodá člověk. U každého čísla napiš, odkud je.
3. Kontrola dat: chybějící dny, změny měření, duplicity, měna, s DPH nebo bez.
4. Rozbor: proti předchozímu období i stejnému období loni, rozpad (kanál, kampaň, produkt, zařízení), souvislosti (reklama, tržby, organika, sezóna).
5. Srovnání s jinými klienty TAVEO jen se zdrojem (poznatek #id nebo dokument z archivu).

## Pravidla (od Pavla)
- Fakta odděl od hypotéz. Fakta jen z čísel, příčiny jako možnosti („možná“, „ověřit“).
- U každého závěru zdroj, období a výpočet.
- Problém měření není propad výkonu.
- Graf jen tam, kde pomůže rozhodnout. Tabulky a grafy jako soubor (CSV, PNG, HTML).
MD,
            ],
            [
                'slug' => 'obchod',
                'name' => 'Obchod a provoz',
                'category' => 'provoz',
                'description' => 'Obchod a provoz TAVEO: cenové nabídky, kampaňové briefy, shrnutí po callech, rozsah práce a kapacity, ceny a hodinové sazby, historie zakázek u klientů. Použij, když se připravuje nabídka, brief, odhad pracnosti nebo shrnutí schůzky s klientem či zájemcem.',
                'builds_on' => ['copywriting'],
                'instructions' => <<<'MD'
## Co děláš
Nabídky, briefy a shrnutí schůzek, které sedí s tím, co TAVEO umí, kolik to stojí a na co má kapacitu.

## Odkud bereš čísla
- Ceny, sazby a balíčky: jen z archivu firmy (`taveo archiv firma`, druh obchod) a z firemních poznatků. Když tam nejsou, cenu nevymýšlej a napiš, že ji doplní Tom nebo Pavel.
- Historie u klienta: archiv a mozek klienta. Hodiny a fakturace jsou v Nástrojích (Klienti → Hodiny, Fakturace), když je nemáš, řekni si o ně.
- Kapacity: z poznatků, nebo se zeptej.

## Výstupy
- Cenová nabídka: co klient dostane a co ne, kolik, za jak dlouho, co potřebujeme od něj.
- Kampaňový brief: cíl, cílovka, nabídka, kanály, rozpočet, termíny, KPI, podklady.
- Shrnutí po callu: co se domluvilo, úkoly (kdo, do kdy), otevřené otázky. Ulož ho do archivu klienta (druh schuzky) a úkoly navrhni (`taveo ukoly`).

## Pravidla
- Nic neposíláš klientovi. Připravíš text, odešle ho člověk.
- Co TAVEO slibuje a co ne: firemní poznatky a strategie firmy v archivu.
MD,
            ],
        ];
    }
};
