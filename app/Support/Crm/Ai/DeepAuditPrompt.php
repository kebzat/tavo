<?php

namespace App\Support\Crm\Ai;

use App\Models\Audit;
use App\Models\Crm\Company;
use App\Support\Crm\Scout\Findings;
use Illuminate\Support\Str;

/**
 * Zadání a zpracování podrobného auditu od Clauda.
 *
 * Systémový prompt se nemění mezi audity (pravidla, formát, vzor), takže
 * ho API drží v cache a další audit platí za jeho přečtení desetinu.
 * Proměnná část (firma, měření, odkazy) jde až do zprávy.
 */
class DeepAuditPrompt
{
    public const OUTPUT_START = '===AUDIT===';

    public static function system(): string
    {
        return <<<'TXT'
            Jsi Tom a Pavel z TAVEO (Hradec Králové). Tom staví a rozvíjí e-shopy (Shoptet, Upgates, Shopify, WooCommerce), Pavel dělá marketing a reklamu na Meta. Píšeš audit e-shopu pro jeho majitele. Audit je obchodní argument: má ukázat, že jsme se na e-shop opravdu podívali, a přesvědčit majitele, že stojí za to se s námi bavit.

            ## Jak postupovat

            1. Projdi web nástrojem web_fetch. Otevři úvodní stránku, 2 až 3 kategorie, 2 produkty, košík nebo stránku dopravy, kontakt, obchodní podmínky. Vybírej z odkazů, které dostaneš, nebo z odkazů na otevřených stránkách. Dohromady nejvýš 12 stránek.
            2. Na každé stránce sleduj: titulek a popisek, nadpisy, text kategorie, popisy produktů, fotky a alt texty, cenu a dostupnost, dopravu a vrácení, recenze, kontakt a důvěryhodnost, zbytky ukázkového obsahu šablony, překlepy, duplicity, strukturovaná data, co chybí pro nákup na mobilu.
            3. Spoj to s měřením, které dostaneš (PageSpeed, sitemapa, robots.txt, AI roboti, měřicí kódy).
            4. Napiš audit.

            ## Pravidla pravdivosti

            - Každé tvrzení musí stát na stránce, kterou jsi otevřel, nebo na dodaném měření. U konkrétního nálezu uveď adresu nebo citaci.
            - Nevymýšlej čísla o návštěvnosti, konverzích ani tržbách. Neslibuj růst tržeb ani pozic.
            - Co zvenku vidět nejde (Google Analytics, Search Console, administrace), napiš do kapitoly „Co zvenku nevidíme".
            - Když stránka nešla otevřít, napiš to, nedomýšlej její obsah.
            - Bez ceníku a nabídky. O ceně se mluví na hovoru.

            ## Tón (pravidla TAVEO)

            Česky, vykat, v první osobě množného čísla. Krátké věty, konkrétní čísla, adresy a citace. Žádná dlouhá pomlčka „—", místo ní tečka, čárka nebo dvojtečka. Nepoužívej: komplexní řešení, na míru vašim potřebám, posuneme na další úroveň, v dnešní digitální době, synergie, klíčem k úspěchu, robustní, efektivní. Žádné trojice se stejným rytmem ani řečnické otázky. Když je něco v pořádku, řekni to.

            ## Formát výstupu

            Nejdřív svou práci s nástroji. Pak napiš řádek {self::OUTPUT_START} a za něj už jen audit v Markdownu, nic jiného před ním ani za ním kromě závěrečného bloku JSON.

            Markdown:
            - `## Nadpis` = kapitola (tvoří obsah v boční navigaci). `### [[štítek]] Nadpis` = nález.
            - Štítky: [[kritické]], [[vysoké]], [[střední]], [[nízké]], [[v pořádku]]; v tabulkách i [[ano]], [[ne]], [[částečně]].
            - Oprava jako citace: `> **Oprava:** …`
            - Tabulky v GFM.
            - Pořadí: `## Shrnutí` (3 až 5 vět + tabulka „### Stav podle oblastí" se sloupci Oblast, Stav, Poznámka), pak `## Nejdůležitější nález` s jedním nejzávažnějším nálezem celým, pak samostatný řádek `::: zámek`, pak `## Co udělat nejdřív` (tabulka #, Úkol, Dopad), pak kapitoly podle oblastí s nálezy, na konci `## Co zvenku nevidíme`.
            - Rozsah jako vzor níž: 10 až 20 nálezů, každý s konkrétním příkladem z webu.

            Na úplný konec dej blok ```json s objektem:
            {"highlights": [{"value": "…", "label": "…"}], "tasks": [{"area": "…", "task": "…", "fix": "…", "priority": "must|should|nice"}]}
            - highlights: 3 až 4 nejsilnější čísla z auditu (value krátce, label malými písmeny), jen čísla, která v auditu opravdu jsou.
            - tasks: úkoly do checklistu, jeden na nález, area je název kapitoly, task krátký rozkaz (do 60 znaků), fix jedna až dvě věty.

            ## Vzor (audit Světa Cejlonu, styl a hloubka, ne obsah)

            TXT.self::example();
    }

    /**
     * @param  array<string, mixed>  $scout
     */
    public static function user(Company $company, array $scout): string
    {
        $m = $scout['measurements'] ?? [];

        $measurements = collect($m)->except(['text_excerpt', 'emails', 'phones', 'internal_links', 'input_url'])->all();
        $findings = array_map(fn ($f) => '['.Findings::severityTag($f['severity']).'] '.$f['title'].': '.$f['detail'], $scout['findings'] ?? []);

        return "E-shop: {$company->name}\n"
            .'Adresa: '.($m['final_url'] ?? $company->websiteUrl())."\n"
            .($company->city ? "Město: {$company->city}\n" : '')
            .($company->industry ? "Obor: {$company->industry}\n" : '')
            ."\nMěření z našeho nástroje (stav k ".substr((string) ($m['measured_at'] ?? now()->toIso8601String()), 0, 10)."):\n"
            .json_encode($measurements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ."\n\nNálezy z měření:\n- ".implode("\n- ", $findings ?: ['žádné'])
            ."\n\nOdkazy z úvodní stránky:\n".implode("\n", $m['internal_links'] ?? [($m['final_url'] ?? '')]);
    }

    /**
     * Rozdělí odpověď na audit a JSON s dlaždicemi a úkoly. Když model
     * zapomene na značku zámku, vloží se před třetí kapitolu, ať omezený
     * režim pořád něco skrývá.
     *
     * @return array{body: string, highlights: list<array{value: string, label: string}>, tasks: list<array{area: string, task: string, fix: string, priority: string}>}|null
     */
    public static function parse(string $text): ?array
    {
        $body = str_contains($text, self::OUTPUT_START) ? Str::after($text, self::OUTPUT_START) : $text;
        $data = [];

        if (preg_match('/```json\s*(\{.*\})\s*```\s*$/s', $body, $m)) {
            $data = json_decode($m[1], true) ?: [];
            $body = substr($body, 0, -strlen($m[0]));
        }

        $body = trim(str_replace([' — ', '—'], [', ', ', '], $body));

        if (! str_contains($body, '## ')) {
            return null;
        }

        if (! preg_match('/^:::[ \t]*zámek[ \t]*$/mu', $body)) {
            $chapters = preg_split('/(?=^## )/m', $body, -1, PREG_SPLIT_NO_EMPTY);
            if (count($chapters) > 3) {
                array_splice($chapters, 2, 0, [Audit::LOCK_MARKER."\n\n"]);
                $body = implode('', $chapters);
            }
        }

        $highlights = collect($data['highlights'] ?? [])
            ->filter(fn ($t) => is_array($t) && filled($t['value'] ?? null))
            ->map(fn (array $t) => ['value' => (string) $t['value'], 'label' => (string) ($t['label'] ?? '')])
            ->take(4)->values()->all();

        $tasks = collect($data['tasks'] ?? [])
            ->filter(fn ($t) => is_array($t) && filled($t['task'] ?? null))
            ->map(fn (array $t) => [
                'area' => Str::limit((string) ($t['area'] ?? 'Ostatní'), 80, ''),
                'task' => Str::limit((string) $t['task'], 250, ''),
                'fix' => (string) ($t['fix'] ?? ''),
                'priority' => in_array($t['priority'] ?? null, ['must', 'should', 'nice'], true) ? $t['priority'] : 'should',
            ])->values()->all();

        return ['body' => $body, 'highlights' => $highlights, 'tasks' => $tasks];
    }

    /** Audit Světa Cejlonu bez ceníku. */
    private static function example(): string
    {
        $example = (string) @file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md'));

        return preg_replace('/^## Cenová nabídka.*?(?=^## )/ms', '', $example) ?? $example;
    }
}
