<?php

namespace App\Support\Crm;

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Audit;
use App\Models\Checklist;
use App\Models\Client;
use App\Models\Crm\Company;
use App\Support\Crm\Ai\ProspectAi;
use App\Support\Crm\Scout\Findings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Z proklepnuté firmy udělá koncept auditu a checklist úkolů.
 *
 * Audit vzniká neveřejný a v omezeném režimu. Než odkaz odejde, musí ho
 * přečíst Tom nebo Pavel a sdílení zapnout ručně. Text stojí jen na
 * nálezech z měření. Claude smí napsat úvodní odstavec, čísla ne.
 *
 * Struktura textu kopíruje audit Světa Cejlonu: shrnutí, stav podle
 * oblastí, pak kapitoly s nálezy. V omezeném režimu klient vidí shrnutí,
 * tabulku a nejzávažnější nález. Zbytek je pod značkou zámku.
 */
class AuditFromCompany
{
    public function __construct(private readonly ProspectAi $ai) {}

    public function create(Company $company): Audit
    {
        $scout = $company->scout_data ?? [];
        $findings = $scout['findings'] ?? [];
        $domain = $company->domain ?: $company->name;

        return DB::transaction(function () use ($company, $scout, $findings, $domain): Audit {
            $client = $this->clientFor($company);

            $audit = Audit::create([
                'client_id' => $client->getKey(),
                'title' => 'Audit webu '.$domain,
                'audited_at' => isset($scout['measurements']['measured_at'])
                    ? substr($scout['measurements']['measured_at'], 0, 10)
                    : now()->toDateString(),
                'intro' => 'Co jsme zjistili z veřejné části webu, bez přístupu do vašich dat.',
                'highlights' => $this->highlights($scout),
                'body' => $this->body($company, $scout),
                'is_public' => false,
                'is_teaser' => true,
            ]);

            if ($findings !== []) {
                $this->checklist($client, $findings, $domain);
            }

            return $audit;
        });
    }

    /**
     * Klient v nástroji checklistů. Prospekt ho nemá, tak ho založíme
     * a propojíme s firmou. Druhý audit téže firmy použije stejného.
     */
    private function clientFor(Company $company): Client
    {
        if ($company->client) {
            return $company->client;
        }

        $base = Str::slug($company->domain ?: $company->name) ?: 'klient';
        $slug = $base;

        for ($i = 2; Client::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return Client::create([
            'crm_company_id' => $company->getKey(),
            'name' => $company->name,
            'slug' => $slug,
            'website_url' => $company->websiteUrl(),
            'note' => 'Prospekt z CRM. Založeno s auditem.',
        ]);
    }

    /**
     * Dlaždice pod hlavičkou. Jen čísla, která opravdu máme.
     *
     * @param  array<string, mixed>  $scout
     * @return list<array{value: string, label: string}>
     */
    private function highlights(array $scout): array
    {
        $m = $scout['measurements'] ?? [];
        $findings = $scout['findings'] ?? [];
        $tiles = [];

        $serious = count(array_filter($findings, fn ($f) => in_array($f['severity'], ['critical', 'high'], true)));
        $things = self::plural(count($findings), 'věc', 'věci', 'věcí');
        $tiles[] = [
            'value' => (string) count($findings),
            'label' => $serious > 0 ? "{$things} k opravě, {$serious} s vysokou prioritou" : "{$things} k opravě",
        ];

        if ($ps = $m['pagespeed'] ?? null) {
            $tiles[] = ['value' => $ps['score'].' / 100', 'label' => 'rychlost na mobilu podle Google PageSpeed'];
            $tiles[] = ['value' => Number::format($ps['lcp_ms'] / 1000, 1, locale: 'cs').' s', 'label' => 'než se na mobilu ukáže hlavní obsah'];
        }

        $blocked = Findings::blockedAiBots($m);
        if ($blocked !== []) {
            $tiles[] = ['value' => count($blocked).' / '.count($m['ai_bots'] ?? $blocked), 'label' => 'AI robotů se na web nedostane'];
        }

        if (($m['sitemap_urls'] ?? null) !== null && count($tiles) < 4) {
            $tiles[] = ['value' => Number::format($m['sitemap_urls'], locale: 'cs'), 'label' => 'adres v mapě stránek'];
        }

        return array_slice($tiles, 0, 4);
    }

    /** @param  array<string, mixed>  $scout */
    private function body(Company $company, array $scout): string
    {
        $findings = $scout['findings'] ?? [];
        $passed = $scout['passed'] ?? [];
        $m = $scout['measurements'] ?? [];

        $md = ['## Shrnutí', '', $this->summary($company, $scout), ''];

        // Stav podle oblastí: nejhorší nález v oblasti, nebo „v pořádku".
        $md[] = '### Stav podle oblastí';
        $md[] = '';
        $md[] = '| Oblast | Stav | Poznámka |';
        $md[] = '|---|---|---|';

        foreach (Findings::AREAS as $area => $label) {
            $inArea = array_values(array_filter($findings, fn ($f) => $f['area'] === $area));
            $okInArea = array_values(array_filter($passed, fn ($p) => $p['area'] === $area));

            if ($inArea !== []) {
                $md[] = "| {$label} | [[".Findings::severityTag($inArea[0]['severity']).']] | '.$this->cell($inArea[0]['title']).' |';
            } elseif ($okInArea !== []) {
                $md[] = "| {$label} | [[v pořádku]] | ".$this->cell($okInArea[0]['title']).' |';
            }
        }

        $md[] = '';

        if ($findings === []) {
            $md[] = 'Zvenku jsme nenašli nic, co by hořelo. Další zjištění by vyžadovala přístup do měření a administrace.';
            $md[] = '';

            return implode("\n", [...$md, ...$this->closing($m)]);
        }

        // Ukázka: nejzávažnější nález celý, ať klient vidí, jak audit vypadá.
        $md[] = '## Nejdůležitější nález';
        $md[] = '';
        $md = [...$md, ...$this->finding($findings[0])];

        $md[] = Audit::LOCK_MARKER;
        $md[] = '';

        $md[] = '## Co udělat nejdřív';
        $md[] = '';
        $md[] = 'Jednotlivé kroky rozepíšeme do checklistu, ve kterém uvidíte, co je hotové.';
        $md[] = '';
        $md[] = '| # | Úkol | Priorita |';
        $md[] = '|---|---|---|';

        foreach ($findings as $i => $finding) {
            $md[] = '| '.($i + 1).' | '.$this->cell($finding['task']).' | [['.Findings::severityTag($finding['severity']).']] |';
        }

        $md[] = '';

        foreach (Findings::AREAS as $area => $label) {
            $inArea = array_values(array_filter(array_slice($findings, 1), fn ($f) => $f['area'] === $area));

            if ($inArea === []) {
                continue;
            }

            $md[] = "## {$label}";
            $md[] = '';

            foreach ($inArea as $finding) {
                $md = [...$md, ...$this->finding($finding)];
            }
        }

        return implode("\n", [...$md, ...$this->closing($m)]);
    }

    /** @param  array<string, mixed>  $scout */
    private function summary(Company $company, array $scout): string
    {
        $fromAi = $this->ai->auditSummary($company, $scout);

        if ($fromAi !== null) {
            return $fromAi;
        }

        $findings = $scout['findings'] ?? [];
        $passed = $scout['passed'] ?? [];
        $serious = array_values(array_filter($findings, fn ($f) => in_array($f['severity'], ['critical', 'high'], true)));

        $text = [];

        if ($passed !== []) {
            $text[] = 'V pořádku: '.implode(', ', array_map(fn ($p) => self::lower($p['title']), array_slice($passed, 0, 3))).'.';
        }

        $count = count($findings);

        $text[] = match (true) {
            $findings === [] => 'Zvenku jsme nenašli nic závažného.',
            $serious === [] => "Našli jsme {$count} ".self::plural($count, 'drobnost', 'drobnosti', 'drobností').', web zásadně nebrzdí žádná z nich.',
            default => "Našli jsme {$count} ".self::plural($count, 'věc', 'věci', 'věcí').' k opravě. Nejvíc spěchá: '
                .implode('; ', array_map(fn ($f) => self::lower($f['title']), array_slice($serious, 0, 3))).'.',
        };

        return implode(' ', $text);
    }

    /**
     * @param  array<string, string>  $finding
     * @return list<string>
     */
    private function finding(array $finding): array
    {
        return [
            '### [['.Findings::severityTag($finding['severity']).']] '.$finding['title'],
            '',
            $finding['detail'],
            '',
            '> **Oprava:** '.$finding['fix'],
            '',
        ];
    }

    /**
     * Co zvenku vidět nejde. Poctivé přiznání patří do každého auditu
     * z veřejných dat, viz docs/BRAND-STRATEGY.md, kapitola 9.3.
     *
     * @param  array<string, mixed>  $m
     * @return list<string>
     */
    private function closing(array $m): array
    {
        $lines = [
            '## Co zvenku nevidíme',
            '',
            'Audit vychází jen z veřejné části webu. Bez přístupu do Google Analytics, Search Console a administrace nevíme, kolik lidí na web chodí, odkud a kde odcházejí. Proto tu nejsou žádné odhady tržeb ani návštěvnosti.',
            '',
        ];

        if (($m['pagespeed'] ?? null) === null && ($m['reachable'] ?? false)) {
            $lines[] = 'Rychlost podle Google PageSpeed se tentokrát změřit nepodařilo. Doplníme ji při další kontrole.';
            $lines[] = '';
        }

        $lines[] = 'Měřili jsme úvodní stránku, robots.txt, mapu stránek a přístup AI robotů. Stav k datu v hlavičce.';

        return $lines;
    }

    /**
     * Checklist úkolů podle nálezů: kategorie je oblast, priorita podle
     * závažnosti. Sdílení se zapne spolu s plnou verzí auditu.
     *
     * @param  list<array<string, string>>  $findings
     */
    private function checklist(Client $client, array $findings, string $domain): Checklist
    {
        $checklist = Checklist::create([
            'client_id' => $client->getKey(),
            'is_template' => false,
            'is_public' => false,
            'name' => 'Úkoly z auditu '.$domain,
            'intro' => 'Kroky, které vyplynuly z auditu. Postupně je odškrtáváme.',
        ]);

        $order = 0;

        foreach (Findings::AREAS as $area => $label) {
            $inArea = array_values(array_filter($findings, fn ($f) => $f['area'] === $area));

            if ($inArea === []) {
                continue;
            }

            $category = $checklist->categories()->create([
                'title' => $label,
                'slug' => Str::slug($label),
                'order_column' => ++$order,
            ]);

            $section = $category->sections()->create(['title' => $label, 'order_column' => 1]);

            foreach ($inArea as $i => $finding) {
                $section->items()->create([
                    'checklist_id' => $checklist->getKey(),
                    'title' => $finding['task'],
                    'description' => $finding['fix'],
                    'priority' => match ($finding['severity']) {
                        'critical', 'high' => ChecklistPriority::Must,
                        'medium' => ChecklistPriority::Should,
                        default => ChecklistPriority::Nice,
                    },
                    'status' => ChecklistItemStatus::Todo,
                    'order_column' => $i + 1,
                ]);
            }
        }

        return $checklist;
    }

    /** 1 věc, 2 věci, 5 věcí. */
    private static function plural(int $n, string $one, string $few, string $many): string
    {
        return match (true) {
            $n === 1 => $one,
            $n >= 2 && $n <= 4 => $few,
            default => $many,
        };
    }

    /** Malé první písmeno, ale zkratky jako HTTPS nebo AI nechá být. */
    private static function lower(string $text): string
    {
        return preg_match('/^\p{Lu}\p{Lu}/u', $text) ? $text : Str::lcfirst($text);
    }

    /** Svislá čára by v Markdownu rozbila tabulku. */
    private function cell(string $text): string
    {
        return str_replace('|', '/', $text);
    }
}
