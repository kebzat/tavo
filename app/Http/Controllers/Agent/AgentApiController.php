<?php

namespace App\Http\Controllers\Agent;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use App\Http\Controllers\Controller;
use App\Models\Ads\AdReport;
use App\Models\AgentJob;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\Marketing\ClientBriefing;
use App\Support\AdGraphics\GraphicsAgent;
use App\Support\ClickUp\ClickUpAgent;
use App\Support\Knowledge\BrainAgent;
use App\Support\Marketing\ClientContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rozhraní pro agenta (Claude Code v kontejneru taveo-agent, příkaz `taveo`).
 *
 * Agent čte data klientů spočítaná systémem a zapisuje jen koncepty, které
 * vidí a schvaluje člověk: ranní přehled, návrhy úkolů, rozhodnutí do karty,
 * fakta / příčiny / doporučení ke konceptu reportu. Navíc smí posunout stav
 * schváleného úkolu (hotovou práci tak klient uvidí v „Co jsme udělali“)
 * a na pokyn člověka zapsat úkol rovnou do plánu klienta, schválit návrh
 * nebo úkol přesunout na jiný měsíc (od 27. 10. 2026, chtěl uživatel).
 * Nic neodesílá klientovi, nic nemění v reklamách. Viz docs/MARKETING.md.
 */
class AgentApiController extends Controller
{
    /** Víc návrhů úkolů najednou z jedné schůzky nedává smysl a zaplevelí seznam. */
    private const MAX_TASKS = 10;

    public function clients(): JsonResponse
    {
        $clients = Client::query()->active()->with(['adAccounts', 'adSettings'])->orderBy('name')->get();

        return response()->json($clients->map(fn (Client $client): array => [
            'slug' => $client->slug,
            'nazev' => $client->name,
            'reklamy' => $client->activeAdAccountIds() !== [],
            'cil' => $client->activeAdAccountIds() !== [] ? $client->adGoal()->getLabel() : null,
            'karta_vyplnena' => $client->profileFields() !== [],
            'projekt_agenta' => $client->agent_project,
            'upozorneni' => $client->adAlerts()->live()->count(),
        ])->values());
    }

    public function client(string $slug): JsonResponse
    {
        return response()->json(ClientContext::for($this->find($slug))->toArray());
    }

    /** Ranní přehled klienta za dnešek. Další zápis týž den ho přepíše. */
    public function briefing(Request $request, string $slug): JsonResponse
    {
        $client = $this->find($slug);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'sources' => ['nullable', 'array', 'max:20'],
        ]);

        // whereDate: sloupec date se ukládá i s časem, prosté = by dnešek nenašlo.
        $briefing = ClientBriefing::query()->where('client_id', $client->getKey())->whereDate('date', today())->first()
            ?? new ClientBriefing(['client_id' => $client->getKey(), 'date' => today()]);
        $briefing->fill(['body' => $data['body'], 'sources' => $data['sources'] ?? null, 'author' => 'agent'])->save();

        return response()->json(['ok' => true, 'id' => $briefing->getKey()]);
    }

    /**
     * Návrhy úkolů (ze schůzky, e-mailu). Klient je neuvidí, dokud je člověk neschválí.
     * Se „schvaleno“ (člověk to agentovi v konverzaci řekl) jdou úkoly rovnou do plánu
     * klienta na měsíc „v_planu_na“ (bez něj na tento měsíc), přiřazené tomu, kdo má
     * u klienta naposledy založený úkol.
     */
    public function tasks(Request $request, string $slug): JsonResponse
    {
        $client = $this->find($slug);
        $data = $request->validate([
            'schvaleno' => ['sometimes', 'boolean'],
            'tasks' => ['required', 'array', 'min:1', 'max:'.self::MAX_TASKS],
            'tasks.*.title' => ['required', 'string', 'max:200'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            'tasks.*.area' => ['nullable', Rule::enum(WorkArea::class)],
            'tasks.*.priority' => ['nullable', Rule::in(['high', 'normal', 'low'])],
            'tasks.*.due_on' => ['nullable', 'date'],
            'tasks.*.v_planu_na' => ['nullable', 'date_format:Y-m'],
            'tasks.*.waiting_for' => ['nullable', 'string', 'max:255'],
            'tasks.*.source' => ['nullable', 'string', 'max:255'],
        ]);
        $approved = (bool) ($data['schvaleno'] ?? false);

        // Stejný úkol podruhé nezakládat: u návrhů proti návrhům, u schválených proti otevřeným úkolům.
        $existing = $approved ? $client->tasks()->open() : $client->taskProposals();
        $open = $existing->pluck('title')->map(fn (string $title): string => Str::lower($title))->all();
        $userId = $approved ? $client->tasks()->whereNotNull('user_id')->latest('id')->value('user_id') : null;
        $ids = [];

        foreach ($data['tasks'] as $task) {
            if (in_array(Str::lower($task['title']), $open, true)) {
                continue;
            }

            $ids[] = $client->tasks()->create([
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
                'area' => $task['area'] ?? WorkArea::Marketing->value,
                'priority' => $task['priority'] ?? null,
                'due_on' => $task['due_on'] ?? null,
                'planned_for' => $approved ? $this->month($task['v_planu_na'] ?? null) : null,
                'user_id' => $userId,
                'waiting_for' => $task['waiting_for'] ?? null,
                'source' => $task['source'] ?? 'agent',
                'status' => TaskStatus::Planned,
                'is_proposal' => ! $approved,
                'internal_note' => $approved ? today()->format('j. n. Y').', agent: zapsáno rovnou do plánu na pokyn člověka.' : null,
            ])->getKey();
            $open[] = Str::lower($task['title']);
        }

        return response()->json(['ok' => true, 'zalozeno' => count($ids), 'ids' => $ids]);
    }

    /** Úkoly klienta s id pro změnu stavu: návrhy, otevřené a hotové za tento měsíc. */
    public function taskList(string $slug): JsonResponse
    {
        $client = $this->find($slug);
        $row = fn (ClientTask $task): array => [
            'id' => $task->getKey(),
            'ukol' => $task->title,
            'stav' => $task->status->value,
            'oblast' => $task->area?->getLabel(),
            'v_planu_na' => $task->planned_for?->format('Y-m'),
            'termin' => $task->due_on?->toDateString(),
            'ceka_se_na' => $task->waiting_for,
            'hotovo_dne' => $task->done_on?->toDateString(),
        ];

        return response()->json([
            'navrhy' => $client->taskProposals()->orderBy('id')->get()->map($row)->values(),
            'otevrene' => $client->tasks()->open()->orderBy('planned_for')->orderBy('id')->get()->map($row)->values(),
            'hotove_tento_mesic' => $client->tasks()->where('status', TaskStatus::Done->value)
                ->where('done_on', '>=', today()->startOfMonth()->toDateString())->orderBy('done_on')->get()->map($row)->values(),
        ]);
    }

    /**
     * Stav schváleného úkolu. Hotový úkol klient uvidí na přehledu v „Co jsme
     * udělali“ za měsíc dokončení. Poznámka jde jen do interní poznámky.
     * Návrh od agenta musí nejdřív schválit člověk.
     */
    public function taskStatus(Request $request, ClientTask $task): JsonResponse
    {
        abort_if($task->is_proposal, 409, 'Návrh úkolu ještě neschválil člověk, stav se mu nemění.');

        $data = $request->validate([
            'stav' => ['required', Rule::enum(TaskStatus::class)],
            'poznamka' => ['nullable', 'string', 'max:2000'],
            'ceka_na' => ['nullable', 'string', 'max:255'],
        ]);

        $status = TaskStatus::from($data['stav']);
        $line = today()->format('j. n. Y').', agent: '.$status->getLabel().(filled($data['poznamka'] ?? null) ? '. '.$data['poznamka'] : '');

        $task->fill([
            'status' => $status,
            'internal_note' => trim(($task->internal_note ?? '')."\n".$line),
        ]);

        if ($status === TaskStatus::Waiting && filled($data['ceka_na'] ?? null)) {
            $task->waiting_for = $data['ceka_na'];
        }

        $task->save();

        return response()->json(['ok' => true, 'id' => $task->getKey(), 'stav' => $task->status->value, 'hotovo_dne' => $task->done_on?->toDateString()]);
    }

    /**
     * Název, popis pro klienta, měsíc v plánu, termín nebo schválení návrhu,
     * když to agentovi řekne člověk. Změna jde do interní poznámky úkolu.
     */
    public function taskUpdate(Request $request, ClientTask $task): JsonResponse
    {
        $data = $request->validate([
            'v_planu_na' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'termin' => ['sometimes', 'nullable', 'date'],
            'schvalit' => ['sometimes', 'accepted'],
            'nazev' => ['sometimes', 'string', 'max:200'],
            'popis' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        abort_if($data === [], 422, 'Není co změnit: nazev, popis, v_planu_na, termin nebo schvalit.');

        $changes = [];
        if (array_key_exists('nazev', $data)) {
            $task->title = $data['nazev'];
            $changes[] = 'nový název';
        }
        if (array_key_exists('popis', $data)) {
            $task->description = $data['popis'];
            $changes[] = 'nový popis';
        }
        if (array_key_exists('v_planu_na', $data)) {
            $task->planned_for = $this->month($data['v_planu_na']);
            $changes[] = 'v plánu na '.($data['v_planu_na'] ?? 'později');
        }
        if (array_key_exists('termin', $data)) {
            $task->due_on = $data['termin'];
            $changes[] = 'termín '.($data['termin'] ?? 'žádný');
        }
        if (isset($data['schvalit']) && $task->is_proposal) {
            $task->is_proposal = false;
            $task->planned_for ??= $this->month(null);
            $task->user_id ??= $task->client->tasks()->whereNotNull('user_id')->latest('id')->value('user_id');
            $changes[] = 'schváleno na pokyn člověka';
        }

        if ($changes !== []) {
            $task->internal_note = trim(($task->internal_note ?? '')."\n".today()->format('j. n. Y').', agent: '.implode(', ', $changes).'.');
            $task->save();
        }

        return response()->json([
            'ok' => true,
            'id' => $task->getKey(),
            'navrh' => $task->is_proposal,
            'nazev' => $task->title,
            'v_planu_na' => $task->planned_for?->format('Y-m'),
            'termin' => $task->due_on?->toDateString(),
        ]);
    }

    /** Rozhodnutí do karty klienta, se zdrojem a datem. */
    public function decision(Request $request, string $slug): JsonResponse
    {
        $client = $this->find($slug);
        $data = $request->validate([
            'decision' => ['required', 'string', 'max:2000'],
            'source' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $decisions = $client->decisions ?? [];
        $decisions[] = [
            'date' => $data['date'] ?? today()->toDateString(),
            'decision' => $data['decision'],
            'source' => $data['source'],
            'valid_until' => $data['valid_until'] ?? null,
            'added_by' => 'agent',
        ];

        $client->forceFill(['decisions' => $decisions])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Fakta, možné příčiny a doporučení ke konceptu reportu. Jen do konceptu
     * a jen do prázdných polí: co už Pavel napsal, se nepřepisuje.
     */
    public function report(Request $request, AdReport $report): JsonResponse
    {
        abort_unless($report->status->value === 'draft', 409, 'Report už odešel, nedoplňuje se.');

        $data = $request->validate([
            'facts' => ['nullable', 'string', 'max:10000'],
            'causes' => ['nullable', 'string', 'max:10000'],
            'recommendations' => ['nullable', 'string', 'max:10000'],
            'worked' => ['nullable', 'string', 'max:10000'],
            'not_worked' => ['nullable', 'string', 'max:10000'],
        ]);

        $report->forceFill(collect($data)->filter(fn ($value, string $key): bool => filled($value) && blank($report->{$key}))->all())->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Výsledek úlohy od služby agenta. Úloha z ClickUpu odpoví do ClickUpu
     * ({@see ClickUpAgent::finish()}), reklamní grafika uloží návrhy
     * ({@see GraphicsAgent::finish()}), mozek návrhy poznatků nebo odpověď
     * ({@see BrainAgent::finish()}). Jen jednou, doběhlá úloha se nemění.
     */
    public function jobResult(Request $request, AgentJob $job, ClickUpAgent $agent, GraphicsAgent $graphics, BrainAgent $brain): JsonResponse
    {
        // Grafika: konverzace po úloze zůstává otevřená a může poslat další výsledek.
        abort_unless($job->status === AgentJob::STATUS_RUNNING || $job->source === GraphicsAgent::SOURCE, 409, 'Úloha už je uzavřená.');

        $data = $request->validate([
            'ok' => ['required', 'boolean'],
            'vysledek' => ['nullable', 'string', 'max:50000'],
            'session' => ['nullable', 'uuid'],
            'chyba' => ['nullable', 'string', 'max:2000'],
        ]);

        match ($job->source) {
            GraphicsAgent::SOURCE => $graphics->finish($job, $data),
            BrainAgent::SOURCE, BrainAgent::SOURCE_QUESTION => $brain->finish($job, $data),
            default => $agent->finish($job, $data),
        };

        return response()->json(['ok' => true]);
    }

    /** „2026-10“ na první den měsíce, bez měsíce tento měsíc. */
    private function month(?string $month): string
    {
        return ($month ?? today()->format('Y-m')).'-01';
    }

    private function find(string $slug): Client
    {
        return Client::query()->where('slug', $slug)->with(['adAccounts', 'adSettings'])->firstOrFail();
    }
}
