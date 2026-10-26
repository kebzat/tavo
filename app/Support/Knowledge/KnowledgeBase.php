<?php

namespace App\Support\Knowledge;

use App\Models\AgentJob;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mozek TAVEO pro agenty: co jim dát do zadání, jak v mozku hledat a jak
 * z jejich práce vzniknou návrhy nových poznatků. Viz docs/MOZEK.md.
 *
 * Do zadání jde jen malý základ (poznatky „vždy v hlavě“ a poznatky
 * klienta úlohy), zbytek si agent dohledá přes API. Celý mozek by se do
 * zadání nevešel a agent by v něm to důležité přehlédl.
 */
class KnowledgeBase
{
    /** Návrh v závěru agenta: `[poznatek klient svet-cejlonu] Nadpis\ntext [/poznatek]`. */
    public const PROPOSAL_BLOCK = '/\[poznatek(?:[ \t]+(firma|klient|postup))?(?:[ \t]+([a-z0-9][a-z0-9-]*))?[ \t]*\]\s*(.*?)\s*\[\/poznatek\]/isu';

    /** Z jedné úlohy víc návrhů nedává smysl, zaplevelilo by to schvalování. */
    public const MAX_PER_JOB = 3;

    /** Kolik znaků mozku nejvýš do zadání úlohy (vždy v hlavě / klient). */
    private const BRIEFING_ALWAYS = 8000;

    private const BRIEFING_CLIENT = 6000;

    /**
     * Úvod konverzace v projektu (háček SessionStart) je menší: Claude Code
     * víc než 10 000 znaků z háčku do konverzace nevloží.
     */
    private const OPENING_ALWAYS = 2500;

    private const OPENING_CLIENT = 2500;

    private const OPENING_ARCHIVE = 8;

    /** Archiv firmy (podklady bez klienta) má v API a v `taveo archiv` místo slugu klienta tohle slovo. */
    public const FIRM_ARCHIVE = 'firma';

    /** Kolik dokumentů archivu vypsat jménem, zbytek dohledá `taveo archiv`. */
    private const ARCHIVE_LISTED = 12;

    /**
     * Schválené poznatky podle slov dotazu, nejlepší první. Česká slova se
     * zkracují na kmen (reklamy → rekla), ať se najdou i v jiném tvaru.
     *
     * @return Collection<int, KnowledgeEntry>
     */
    public function search(string $query, ?Client $client = null, bool $withProposals = false, int $limit = 15): Collection
    {
        $stems = self::stems($query);

        $candidates = KnowledgeEntry::query()
            ->with('client')
            ->whereIn('status', $withProposals ? [KnowledgeEntry::STATUS_APPROVED, KnowledgeEntry::STATUS_PROPOSED] : [KnowledgeEntry::STATUS_APPROVED])
            ->when($client, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('client_id', $client->getKey())->orWhereNull('client_id')))
            ->when($stems !== [], fn (Builder $q) => $q->where(function (Builder $q) use ($stems): void {
                foreach ($stems as $stem) {
                    $q->orWhere('title', 'like', "%{$stem}%")->orWhere('body', 'like', "%{$stem}%")->orWhere('tags', 'like', "%{$stem}%");
                }
            }))
            ->latest('updated_at')
            ->limit(300)
            ->get();

        if ($stems === []) {
            return $candidates->take($limit)->values();
        }

        return $candidates
            ->map(function (KnowledgeEntry $entry) use ($stems, $client): array {
                $title = Str::lower($entry->title);
                $body = Str::lower($entry->body.' '.implode(' ', $entry->tags ?? []));
                $score = collect($stems)->sum(fn (string $stem): int => (str_contains($title, $stem) ? 3 : 0) + (str_contains($body, $stem) ? 1 : 0));

                return [$entry, $score + ($client && $entry->client_id === $client->getKey() ? 2 : 0) + ($entry->always ? 1 : 0)];
            })
            ->sortByDesc(fn (array $item): int => $item[1])
            ->take($limit)
            ->map(fn (array $item): KnowledgeEntry => $item[0])
            ->values();
    }

    /** @return list<string> */
    public static function stems(string $query): array
    {
        return collect(preg_split('/[^\pL\pN]+/u', Str::lower($query)) ?: [])
            ->filter(fn (string $word): bool => mb_strlen($word) >= 3)
            ->map(fn (string $word): string => mb_strlen($word) > 5 ? mb_substr($word, 0, max(5, mb_strlen($word) - 2)) : $word)
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Část zadání úlohy (ClickUp, grafika): základ z mozku, jak hledat dál
     * a jak navrhnout nový poznatek.
     */
    public function briefing(?Client $client): string
    {
        $always = $this->fit(KnowledgeEntry::query()->approved()->where('always', true)->orderBy('layer')->orderBy('id')->get(), self::BRIEFING_ALWAYS);
        $forClient = $client
            ? $this->fit(KnowledgeEntry::query()->approved()->where('client_id', $client->getKey())->where('always', false)->latest('updated_at')->get(), self::BRIEFING_CLIENT)
            : collect();

        $lines = ['## Mozek TAVEO (schválené zkušenosti týmu)'];

        if ($always->isEmpty() && $forClient->isEmpty()) {
            $lines[] = 'Zatím v něm k téhle práci nic není.';
        } else {
            $lines[] = 'Drž se jich, pokud pokyn neříká jinak. Když si s pokynem odporují, napiš to do závěru.';
        }

        if ($always->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Platí vždy:';
            $lines[] = $always->map(fn (KnowledgeEntry $entry): string => $this->format($entry))->implode("\n");
        }

        if ($forClient->isNotEmpty()) {
            $lines[] = '';
            $lines[] = "Ke klientovi {$client->name}:";
            $lines[] = $forClient->map(fn (KnowledgeEntry $entry): string => $this->format($entry))->implode("\n");
        }

        $archive = $client ? $this->archiveLines($client) : [];

        if ($archive !== []) {
            $lines[] = '';
            $lines[] = "Archiv klienta {$client->name} (data, strategie, výsledky, marže; celé dokumenty, ne jen poznatky):";
            array_push($lines, ...$archive);
            $lines[] = 'Seznam s adresami ke stažení: curl -sf -H "Authorization: Bearer $(cat ~/.config/taveo/token)" '.route('agent.api.archiv', $client->slug);
        }

        return implode("\n", [
            ...$lines,
            '',
            'Další poznatky (postupy, jiní klienti, starší zkušenosti) najdeš v mozku, vrací JSON:',
            $this->searchCommand(),
            '',
            '## Nový poznatek do mozku',
            'Když se při práci dozvíš něco, co se příště bude hodit i jinému agentovi (jak to klient chce, co nefungovalo, ověřený postup), dej na úplný konec závěru blok, nejvýš '.self::MAX_PER_JOB.'. Do mozku přijde jako návrh a schválí ho člověk:',
            '[poznatek klient]',
            'Nadpis jednou větou',
            'Pár vět: co, proč, příklad.',
            '[/poznatek]',
            'Vrstva za slovem poznatek: klient (jen tenhle klient), firma (platí pro všechny), postup (návod na opakovanou práci). Žádné osobní údaje zákazníků ani hesla. Obyčejný průběh úlohy poznatek není.',
        ]);
    }

    /**
     * Úvod každé konverzace v projektu agenta (terminál, aplikace Claude):
     * háček SessionStart volá `taveo mozek-uvod` a tohle se vloží na začátek
     * konverzace. Agent tak zná mozek klienta, jeho archiv a ví, jak se učit.
     */
    public function opening(string $project, ?Client $client): string
    {
        $always = $this->fit(KnowledgeEntry::query()->approved()->where('always', true)->orderBy('layer')->orderBy('id')->get(), self::OPENING_ALWAYS);
        // Projekt bez klienta (web TAVEO, marketing): místo klienta firemní poznatky a archiv firmy.
        $own = KnowledgeEntry::query()->approved()->where('always', false)
            ->when($client, fn (Builder $q) => $q->where('client_id', $client->getKey()), fn (Builder $q) => $q->whereNull('client_id'))
            ->latest('updated_at')->get();
        $forClient = $this->fit($own, self::OPENING_CLIENT);
        $archive = $this->archiveLines($client, self::OPENING_ARCHIVE, 180);
        $slug = $client?->slug ?? self::FIRM_ARCHIVE;

        $lines = ['# Mozek TAVEO pro tuhle konverzaci', ''];
        $lines[] = $client
            ? "Projekt `{$project}` je klient **{$client->name}** (slug `{$client->slug}`). Níž je, co o něm mozek ví (schválené poznatky) a co je v jeho archivu. Drž se toho, pokud člověk neřekne jinak, a když si odporuje s tím, co vidíš, řekni to."
            : "Projekt `{$project}` není klient, je to práce pro TAVEO samo (web, nástroje, marketing). Níž jsou firemní poznatky a postupy a archiv firmy. Drž se jich, pokud člověk neřekne jinak.";

        if ($always->isNotEmpty()) {
            array_push($lines, '', '## Platí vždy', $always->map(fn (KnowledgeEntry $entry): string => $this->format($entry))->implode("\n"));
        }

        array_push($lines, '', $client ? "## Mozek klienta {$client->name}" : '## Firemní poznatky a postupy (nejnovější)', $forClient->isNotEmpty()
            ? $forClient->map(fn (KnowledgeEntry $entry): string => $this->format($entry))->implode("\n")
            : ($client ? 'Zatím nic schváleného. Tím spíš navrhuj, co se o klientovi dozvíš.' : 'Zatím nic dalšího.'));

        array_push($lines, '', $client ? '## Archiv klienta' : '## Archiv firmy', ...($archive !== []
            ? [...$archive, '', "Celé dokumenty: `taveo archiv {$slug}` (seznam), `taveo archiv-stahnout <id> <složka>` (stáhne text a soubory). Čti je, když se otázka týká dat, strategie, výsledků nebo marží, a cituj, odkud číslo je."]
            : [$client
                ? 'Zatím prázdný. Dokumenty klienta (data, strategie, výsledky, marže) nahrávají lidé v Nástrojích → Klienti → '.$client->name.' → Archiv, nebo je uložíš ty (níž).'
                : 'Zatím prázdný. Firemní dokumenty (strategie, ceník, čísla, zápisy) nahrávají lidé v Nástrojích → Mozek → Archiv a podklady, nebo je uložíš ty (níž).']));

        array_push($lines, '',
            '**Ukládání do archivu:** když ti člověk dá dokument '.($client ? 'klienta' : 'firmy').' (soubor, vložená tabulka, zápis ze schůzky, strategie, čísla) nebo ho spolu sepíšete, ulož ho do archivu, ať ho najdou i další konverzace. Řekni mu to jednou větou. Vložený text nejdřív ulož do souboru .md. Jednorázové pracovní soubory a nic s hesly nebo osobními údaji zákazníků tam nepatří.',
            "  `taveo archiv-nahrat {$slug} \"Název\" --druh obchod --k-datu 2026-09-30 --popis \"co v něm je, 1–3 věty\" soubor.xlsx`",
            '  Druhy: '.collect(KnowledgeSource::categories())->map(fn (string $label, string $key): string => "{$key} ({$label})")->implode(', ').'. `--poznatky` pošle dokument i knihovníkovi (strategie, zápisy, zkušenosti; u tabulek a exportů ne, stačí popis).');

        $scope = $client ? " {$client->slug}" : '';

        return implode("\n", [
            ...$lines,
            '',
            '## Hledání a učení',
            "- Víc v mozku: `taveo mozek \"slova\"{$scope}` (postupy, starší zkušenosti, jiní klienti).",
            '- Když se v konverzaci dozvíš něco, co se bude hodit příště (jak to klient chce, co fungovalo a co ne, domluva, číslo s datem), navrhni poznatek. Platit začne, až ho člověk schválí v Nástrojích → Mozek:',
            '  `taveo mozek-navrh '.($client ? 'klient' : 'firma')." \"Nadpis jednou větou\"{$scope} <<'EOF'` … pár vět: co, proč, příklad, datum u čísel … `EOF`".($client ? '' : ' (návod na opakovanou práci: `postup` místo `firma`)'),
            '  Před návrhem `taveo mozek-seznam` (ať nevznikne podruhé). Obyčejný průběh práce poznatek není. Žádná hesla ani osobní údaje zákazníků.',
            '- Když tě člověk opraví („tohle klient nechce“), je to přesně ten poznatek, který stojí za návrh.',
        ]);
    }

    /** @return list<string> dokumenty archivu klienta (bez klienta: archiv firmy), nejnovější první, s popisem obsahu */
    private function archiveLines(?Client $client, int $listed = self::ARCHIVE_LISTED, int $summary = 300): array
    {
        $query = fn (): Builder => KnowledgeSource::query()->where('client_id', $client?->getKey());
        $sources = $query()->latest('id')->limit($listed + 1)->get();
        $more = $sources->count() > $listed ? $query()->count() - $listed : 0;

        return $sources->take($listed)
            ->map(fn (KnowledgeSource $source): string => '- #'.$source->getKey().' '.$source->title
                .' ('.collect([$source->categoryLabel(), $source->document_date ? 'k '.$source->document_date->format('j. n. Y') : null, $source->sizeLabel()])->filter()->implode(', ').')'
                .($source->summary ? ': '.Str::limit(preg_replace('/\s*\R\s*/u', ' ', $source->summary), $summary) : ''))
            ->push(...($more > 0 ? ["- … a {$more} starších"] : []))
            ->values()
            ->all();
    }

    /** Hledání v mozku z kontejneru (token agenta leží v souboru, jako u `taveo`). */
    public function searchCommand(string $query = 'slova+k+hledani'): string
    {
        return 'curl -sf -H "Authorization: Bearer $(cat ~/.config/taveo/token)" \''.route('agent.api.mozek').'?q='.$query.'\'';
    }

    /**
     * Bloky `[poznatek …] … [/poznatek]` ze závěru agenta → návrhy v mozku.
     * Vrátí závěr bez bloků a počet založených návrhů.
     *
     * @return array{0: string, 1: int}
     */
    public function capture(AgentJob $job, string $result, string $originType): array
    {
        preg_match_all(self::PROPOSAL_BLOCK, $result, $blocks, PREG_SET_ORDER);
        $clean = trim(preg_replace(self::PROPOSAL_BLOCK, '', $result));
        $created = 0;

        foreach (array_slice($blocks, 0, self::MAX_PER_JOB) as [, $layer, $slug, $text]) {
            $lines = preg_split('/\R/u', trim($text)) ?: [];
            $title = trim(array_shift($lines) ?? '');
            $body = trim(implode("\n", $lines));

            $entry = $this->propose([
                'vrstva' => $layer ?: KnowledgeEntry::LAYER_CLIENT,
                'klient' => $slug ?: $job->client?->slug,
                'nadpis' => $title,
                'text' => $body ?: $title,
                'puvod' => Str::limit(($job->task_name ?: 'Úloha agenta').' ('.$job->created_at?->format('j. n. Y').')', 250, ''),
            ], [
                'origin_type' => $originType,
                'agent_job_id' => $job->getKey(),
                'proposed_by' => 'agent ('.($job->author ?: $job->source).')',
            ]);

            $created += $entry ? 1 : 0;
        }

        return [$clean, $created];
    }

    /**
     * Jeden návrh poznatku od agenta. Stejný nadpis, který už v mozku je
     * (platný nebo ke schválení), se nezakládá znovu. Agent nesmí nastavit
     * „vždy v hlavě“, to rozhoduje člověk.
     *
     * @param  array{vrstva?: ?string, klient?: ?string, nadpis?: ?string, text?: ?string, stitky?: mixed, puvod?: ?string, nahrazuje?: mixed}  $item
     * @param  array{origin_type: string, knowledge_source_id?: ?int, agent_job_id?: ?int, proposed_by?: ?string}  $context
     */
    public function propose(array $item, array $context): ?KnowledgeEntry
    {
        $title = Str::limit(trim((string) ($item['nadpis'] ?? '')), 200, '');
        $body = Str::limit(trim((string) ($item['text'] ?? '')), 5000, '');

        if ($title === '' || $body === '') {
            return null;
        }

        $exists = KnowledgeEntry::query()
            ->whereIn('status', [KnowledgeEntry::STATUS_APPROVED, KnowledgeEntry::STATUS_PROPOSED])
            ->whereRaw('lower(title) = ?', [Str::lower($title)])
            ->exists();

        if ($exists) {
            return null;
        }

        $client = filled($item['klient'] ?? null) ? Client::query()->where('slug', $item['klient'])->first() : null;
        $layer = array_key_exists((string) ($item['vrstva'] ?? ''), KnowledgeEntry::layers()) ? $item['vrstva'] : KnowledgeEntry::LAYER_COMPANY;
        // Poznatek o klientovi bez známého klienta platí jako obecný.
        $layer = $layer === KnowledgeEntry::LAYER_CLIENT && ! $client ? KnowledgeEntry::LAYER_COMPANY : $layer;
        $replaces = is_numeric($item['nahrazuje'] ?? null)
            ? KnowledgeEntry::query()->approved()->find((int) $item['nahrazuje'])
            : null;

        return KnowledgeEntry::create([
            'layer' => $layer,
            'client_id' => $layer === KnowledgeEntry::LAYER_CLIENT ? $client?->getKey() : null,
            'title' => $title,
            'body' => $body,
            'tags' => collect(is_array($item['stitky'] ?? null) ? $item['stitky'] : [])
                ->filter(fn ($tag): bool => is_string($tag) && trim($tag) !== '')
                ->map(fn (string $tag): string => Str::limit(Str::lower(trim($tag)), 40, ''))
                ->unique()->take(6)->values()->all() ?: null,
            'origin' => filled($item['puvod'] ?? null) ? Str::limit(trim((string) $item['puvod']), 250, '') : null,
            'status' => KnowledgeEntry::STATUS_PROPOSED,
            'replaces_id' => $replaces?->getKey(),
            ...$context,
        ]);
    }

    /** @return Collection<int, KnowledgeEntry> poznatky, které se vejdou do `$chars` znaků */
    private function fit(Collection $entries, int $chars): Collection
    {
        $used = 0;

        return $entries->takeWhile(function (KnowledgeEntry $entry) use (&$used, $chars): bool {
            $used += mb_strlen($entry->title) + mb_strlen($entry->body) + 20;

            return $used <= $chars;
        })->values();
    }

    private function format(KnowledgeEntry $entry): string
    {
        return "- #{$entry->getKey()} {$entry->title}: ".preg_replace('/\s*\R\s*/u', ' ', $entry->body);
    }
}
