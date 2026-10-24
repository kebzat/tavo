<?php

namespace App\Http\Controllers\Agent;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use App\Http\Controllers\Controller;
use App\Models\Ads\AdReport;
use App\Models\AgentJob;
use App\Models\Client;
use App\Models\Marketing\ClientBriefing;
use App\Support\AdGraphics\GraphicsAgent;
use App\Support\ClickUp\ClickUpAgent;
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
 * fakta / příčiny / doporučení ke konceptu reportu. Nic neodesílá klientovi,
 * nic nemění v reklamách. Viz docs/MARKETING.md.
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

    /** Návrhy úkolů (ze schůzky, e-mailu). Klient je neuvidí, dokud je člověk neschválí. */
    public function tasks(Request $request, string $slug): JsonResponse
    {
        $client = $this->find($slug);
        $data = $request->validate([
            'tasks' => ['required', 'array', 'min:1', 'max:'.self::MAX_TASKS],
            'tasks.*.title' => ['required', 'string', 'max:200'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            'tasks.*.area' => ['nullable', Rule::enum(WorkArea::class)],
            'tasks.*.priority' => ['nullable', Rule::in(['high', 'normal', 'low'])],
            'tasks.*.due_on' => ['nullable', 'date'],
            'tasks.*.waiting_for' => ['nullable', 'string', 'max:255'],
            'tasks.*.source' => ['nullable', 'string', 'max:255'],
        ]);

        $open = $client->taskProposals()->pluck('title')->map(fn (string $title): string => Str::lower($title))->all();
        $created = 0;

        foreach ($data['tasks'] as $task) {
            // Stejný návrh ze stejné schůzky podruhé nezakládat.
            if (in_array(Str::lower($task['title']), $open, true)) {
                continue;
            }

            $client->taskProposals()->create([
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
                'area' => $task['area'] ?? WorkArea::Marketing->value,
                'priority' => $task['priority'] ?? null,
                'due_on' => $task['due_on'] ?? null,
                'waiting_for' => $task['waiting_for'] ?? null,
                'source' => $task['source'] ?? 'agent',
                'status' => TaskStatus::Planned,
                'is_proposal' => true,
            ]);
            $open[] = Str::lower($task['title']);
            $created++;
        }

        return response()->json(['ok' => true, 'zalozeno' => $created]);
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
     * ({@see GraphicsAgent::finish()}). Jen jednou, doběhlá úloha se nemění.
     */
    public function jobResult(Request $request, AgentJob $job, ClickUpAgent $agent, GraphicsAgent $graphics): JsonResponse
    {
        // Grafika: konverzace po úloze zůstává otevřená a může poslat další výsledek.
        abort_unless($job->status === AgentJob::STATUS_RUNNING || $job->source === GraphicsAgent::SOURCE, 409, 'Úloha už je uzavřená.');

        $data = $request->validate([
            'ok' => ['required', 'boolean'],
            'vysledek' => ['nullable', 'string', 'max:50000'],
            'session' => ['nullable', 'uuid'],
            'chyba' => ['nullable', 'string', 'max:2000'],
        ]);

        $job->source === GraphicsAgent::SOURCE ? $graphics->finish($job, $data) : $agent->finish($job, $data);

        return response()->json(['ok' => true]);
    }

    private function find(string $slug): Client
    {
        return Client::query()->where('slug', $slug)->with(['adAccounts', 'adSettings'])->firstOrFail();
    }
}
