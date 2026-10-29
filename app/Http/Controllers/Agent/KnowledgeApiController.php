<?php

namespace App\Http\Controllers\Agent;

use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AgentProjectScope;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\Skill;
use App\Support\Knowledge\BrainAgent;
use App\Support\Knowledge\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mozek TAVEO pro agenta (token agenta, jako `taveo`). Agent v mozku hledá,
 * čte podklady ke zpracování a posílá návrhy poznatků. Schvaluje je vždy
 * člověk v nástrojích, agent nic neschválí ani nesmaže. Viz docs/MOZEK.md.
 */
class KnowledgeApiController extends Controller
{
    /** Víc najednou z jedné dávky nedává smysl, agent má posílat průběžně. */
    private const MAX_BATCH = 20;

    /** Co agent smí uložit do archivu, stejné jako formulář v nástrojích. */
    private const ARCHIVE_EXTENSIONS = ['txt', 'md', 'csv', 'html', 'pdf', 'json', 'eml', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'svg'];

    /** Čeština bez \u escapů: agent to čte (a platí za každý znak). */
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Hledání: `?q=slova&klient=slug&skill=meta-ads&navrhy=1` (i neschválené návrhy). Bez
     * klienta jen firemní poznatky, v projektu klienta vždy jeho klient. Se skillem
     * jen poznatky skillu, i tady bez jiných klientů.
     */
    public function search(Request $request, KnowledgeBase $knowledge): JsonResponse
    {
        $client = $this->requestedClient($request);
        $skill = $request->filled('skill') ? Skill::query()->where('slug', $request->string('skill'))->firstOrFail() : null;

        return response()->json([
            'poznatky' => $knowledge->search((string) $request->string('q'), $client, $request->boolean('navrhy'), 20, $skill)
                ->map(fn (KnowledgeEntry $entry): array => $entry->toAgent())
                ->values(),
        ], options: self::JSON);
    }

    /**
     * Platné poznatky a návrhy jen s nadpisem (kontrola, ať agent nezakládá
     * totéž): firemní a s `?klient=slug` (v projektu klienta vždy) i klienta.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requestedClient($request);

        return response()->json(KnowledgeEntry::query()
            ->with(['client:id,slug', 'skill:id,slug'])
            ->where(fn ($query) => $query->whereNull('client_id')->when($client, fn ($query) => $query->orWhere('client_id', $client->getKey())))
            ->whereIn('status', [KnowledgeEntry::STATUS_APPROVED, KnowledgeEntry::STATUS_PROPOSED])
            ->orderBy('layer')->orderBy('id')
            ->limit(3000)
            ->get(['id', 'layer', 'client_id', 'skill_id', 'title', 'status'])
            ->map(fn (KnowledgeEntry $entry): array => array_filter([
                'id' => $entry->getKey(),
                'vrstva' => $entry->layer,
                'klient' => $entry->client?->slug,
                'skill' => $entry->skill?->slug,
                'nadpis' => $entry->title,
                'stav' => $entry->status === KnowledgeEntry::STATUS_PROPOSED ? 'navrh' : null,
            ]))
            ->values(), options: self::JSON);
    }

    /**
     * Úvod konverzace v projektu agenta (háček SessionStart, `taveo mozek-uvod`):
     * `?projekt=svet-cejlonu`. Klient se pozná podle projektu agenta na kartě.
     */
    public function opening(Request $request, KnowledgeBase $knowledge): Response
    {
        $project = (string) $request->validate(['projekt' => ['required', 'string', 'max:100']])['projekt'];
        $client = Client::query()->where('agent_project', $project)->first();

        return response($knowledge->opening($project, $client), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Archiv klienta (`firma` = archiv firmy), nejnovější první: `?druh=obchod` jen jeden druh, `?skill=meta-ads` jen k jednomu skillu. */
    public function archive(Request $request, string $slug): JsonResponse
    {
        $client = $this->archiveOwner($slug);
        $skill = $request->filled('skill') ? Skill::query()->where('slug', $request->string('skill'))->firstOrFail() : null;

        return response()->json([
            'klient' => $client?->slug ?? KnowledgeBase::FIRM_ARCHIVE,
            'druhy' => KnowledgeSource::categories(),
            'dokumenty' => $this->documents(KnowledgeSource::query()->where('client_id', $client?->getKey())->when($skill, fn ($query) => $query->where('skill_id', $skill->getKey())), $request),
        ], options: self::JSON);
    }

    /** Skilly TAVEO (`taveo skilly`, `taveo skilly-sync`): kdy který použít, jeho agent a SKILL.md. */
    public function skills(): JsonResponse
    {
        return response()->json(Skill::query()->orderBy('sort')->get()->map(fn (Skill $skill): array => $skill->toAgent())->values(), options: self::JSON);
    }

    /** Skill pro agenta (`taveo skill <slug> [klient]`): pokyny, know-how, poznatky klienta ke skillu, archiv. Text. */
    public function skill(Request $request, Skill $skill, KnowledgeBase $knowledge): Response
    {
        $client = $this->requestedClient($request);

        return response($knowledge->skill($skill, $client), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Archiv skillu: case studies, výsledky, vzory bez klienta a s `?klient=slug`
     * (v projektu klienta vždy) i dokumenty toho klienta označené skillem. Jiní klienti ne.
     */
    public function skillArchive(Request $request, Skill $skill): JsonResponse
    {
        return response()->json([
            'skill' => $skill->slug,
            'druhy' => KnowledgeSource::categories(),
            'dokumenty' => $this->documents(KnowledgeBase::skillSources($skill, $this->requestedClient($request))->with('client'), $request, withClient: true),
        ], options: self::JSON);
    }

    /** Agent uloží podklady do archivu skillu (`taveo skill-nahrat`), s `uceni` z nich knihovník navrhne poznatky skillu. */
    public function skillUpload(Request $request, Skill $skill, BrainAgent $brain): JsonResponse
    {
        return $this->store($request, $brain, null, $skill);
    }

    /** @return list<array<string, mixed>> */
    private function documents($query, Request $request, bool $withClient = false): array
    {
        return $query
            ->when($request->filled('druh'), fn ($query) => $query->where('category', $request->string('druh')))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (KnowledgeSource $source): array => $withClient && $source->client ? ['klient' => $source->client->slug, ...$source->toAgent()] : $source->toAgent())
            ->values()
            ->all();
    }

    /**
     * Agent uloží dokument do archivu klienta (`taveo archiv-nahrat`): soubor,
     * který mu člověk dal v konverzaci, nebo text, který z ní sepsal. S `uceni`
     * ho přečte i knihovník a navrhne poznatky, jinak se jen uloží s popisem.
     */
    public function upload(Request $request, string $slug, BrainAgent $brain): JsonResponse
    {
        $client = $this->archiveOwner($slug);
        $skill = $request->filled('skill') ? Skill::query()->where('slug', $request->string('skill'))->firstOrFail() : null;

        return $this->store($request, $brain, $client, $skill);
    }

    /** Uložení do archivu klienta / firmy (bez klienta), případně označené skillem. */
    private function store(Request $request, BrainAgent $brain, ?Client $client, ?Skill $skill): JsonResponse
    {
        $data = $request->validate([
            'nazev' => ['required', 'string', 'max:200'],
            'druh' => ['nullable', 'string', 'in:'.implode(',', array_keys(KnowledgeSource::categories()))],
            'k_datu' => ['nullable', 'date_format:Y-m-d'],
            'popis' => ['nullable', 'string', 'max:2000'],
            'poznamka' => ['nullable', 'string', 'max:2000'],
            'text' => ['nullable', 'string', 'max:'.KnowledgeSourceResource::MAX_TEXT, 'required_without:soubory'],
            'soubory' => ['nullable', 'array', 'max:20'],
            'soubory.*' => ['file', 'max:'.KnowledgeSourceResource::MAX_FILE_KILOBYTES, 'extensions:'.implode(',', self::ARCHIVE_EXTENSIONS)],
            'uceni' => ['nullable', 'boolean'],
        ]);

        $learn = (bool) ($data['uceni'] ?? false);
        $source = KnowledgeSource::create([
            'title' => $data['nazev'],
            'client_id' => $client?->getKey(),
            'skill_id' => $skill?->getKey(),
            'category' => $data['druh'] ?? null,
            'document_date' => $data['k_datu'] ?? null,
            'summary' => $data['popis'] ?? null,
            'note' => $data['poznamka'] ?? null,
            'body' => $data['text'] ?? null,
            'files' => collect($request->file('soubory', []))->map(fn ($file): array => [
                'path' => $file->store('mozek', 'local'),
                'name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 150, ''),
            ])->values()->all() ?: null,
            'author' => 'agent',
            'learn' => $learn,
            'status' => $learn ? KnowledgeSource::STATUS_QUEUED : KnowledgeSource::STATUS_STORED,
        ]);

        if ($learn) {
            $brain->process($source);
        }

        return response()->json([
            'ok' => true,
            'id' => $source->getKey(),
            'stav' => $source->refresh()->statusLabel(),
            'chyba' => $source->error,
            'v_nastrojich' => KnowledgeSourceResource::getUrl('view', ['record' => $source], panel: 'tools'),
            'stranka' => $source->pageUrl(),
        ], 201, options: self::JSON);
    }

    /** Jeden dokument (podklady) s adresami ke stažení (`taveo archiv-stahnout`). */
    public function sourceInfo(KnowledgeSource $source): JsonResponse
    {
        return response()->json($source->toAgent(), options: self::JSON);
    }

    /** Vložený text podkladů (Markdown / prostý text). */
    public function source(KnowledgeSource $source): Response
    {
        return response((string) $source->body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function file(KnowledgeSource $source, int $index): StreamedResponse
    {
        $file = ($source->files ?? [])[$index] ?? null;
        abort_unless(is_array($file) && Storage::disk('local')->exists($file['path'] ?? ''), 404);

        return Storage::disk('local')->download($file['path'], $file['name'] ?? basename($file['path']));
    }

    /**
     * Návrhy poznatků. U podkladů nejvýš BrainAgent::MAX_PER_SOURCE celkem.
     * S podklady jde poslat i `popis` (co v nich je, pro archiv klienta).
     */
    public function propose(Request $request, KnowledgeBase $knowledge): JsonResponse
    {
        $data = $request->validate([
            'zdroj' => ['nullable', 'integer', 'exists:knowledge_sources,id'],
            'popis' => ['nullable', 'string', 'max:2000', 'required_without:poznatky'],
            'poznatky' => ['array', 'max:'.self::MAX_BATCH, 'required_without:popis'],
            'poznatky.*.vrstva' => ['required', 'string', 'in:'.implode(',', array_keys(KnowledgeEntry::layers()))],
            'poznatky.*.klient' => ['nullable', 'string', 'max:100'],
            'poznatky.*.skill' => ['nullable', 'string', 'max:40'],
            'poznatky.*.nadpis' => ['required', 'string', 'max:300'],
            'poznatky.*.text' => ['required', 'string', 'max:8000'],
            'poznatky.*.stitky' => ['nullable', 'array', 'max:10'],
            'poznatky.*.puvod' => ['nullable', 'string', 'max:500'],
            'poznatky.*.nahrazuje' => ['nullable', 'integer'],
        ]);

        $source = isset($data['zdroj']) ? KnowledgeSource::find($data['zdroj']) : null;
        $project = AgentProjectScope::client($request);
        abort_if($project && $source?->client_id && $source->client_id !== $project->getKey(), 403, 'Podklady patří jinému klientovi.');
        // Z podkladů klienta a z projektu klienta jen poznatky toho klienta (a obecné bez něj).
        $scope = $project ?? $source?->client;

        if (filled($data['popis'] ?? null)) {
            abort_unless($source, 422, 'Popis patří k podkladům, chybí zdroj.');
            $source->update(['summary' => trim($data['popis'])]);
        }

        $room = $source ? max(0, BrainAgent::MAX_PER_SOURCE - $source->entries()->count()) : self::MAX_BATCH;
        $created = 0;
        $skipped = [];
        $reasons = [];

        foreach ($data['poznatky'] ?? [] as $item) {
            $entry = $created < $room ? $knowledge->propose($item, [
                'origin_type' => $source ? 'podklad' : 'agent',
                'knowledge_source_id' => $source?->getKey(),
                'agent_job_id' => $source?->agent_job_id,
                'skill_id' => $source?->skill_id,
                'proposed_by' => 'agent'.($source?->author ? ' (podklady od '.$source->author.')' : ''),
            ], $scope) : 'Z jedněch podkladů už víc návrhů nejde.';

            if ($entry instanceof KnowledgeEntry) {
                $created++;
            } else {
                $skipped[] = $item['nadpis'];
                $reasons[] = ['nadpis' => $item['nadpis'], 'proc' => $entry];
            }
        }

        return response()->json([
            'ok' => true,
            'zalozeno' => $created,
            'preskoceno' => $skipped,
            'proc' => $reasons ?: null,
            'zbyva' => $source ? max(0, $room - $created) : null,
            'popis' => filled($data['popis'] ?? null) ? 'uložen' : null,
        ], options: self::JSON);
    }

    /** Klient z `?klient=slug`, jinak klient projektu, ze kterého agent volá (AgentProjectScope). */
    private function requestedClient(Request $request): ?Client
    {
        return $request->filled('klient')
            ? Client::query()->where('slug', $request->string('klient'))->firstOrFail()
            : AgentProjectScope::client($request);
    }

    /** Klient archivu, null = archiv firmy (podklady bez klienta, projekt taveo a jiné bez klienta). */
    private function archiveOwner(string $slug): ?Client
    {
        return $slug === KnowledgeBase::FIRM_ARCHIVE ? null : Client::query()->where('slug', $slug)->firstOrFail();
    }
}
