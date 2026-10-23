<?php

namespace App\Support\Agent;

use App\Enums\PublicationStatus;
use App\Models\AgentPublication;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Zveřejnění živého webu na serveru (projekt typu server, třeba grillnor).
 * Agent v terminálu a v aplikaci Claude pracuje v kopii webu, Zveřejnit ji
 * nahraje na živý web: snímek a záloha databáze, soubory, migrace, cache.
 * Změny obsahu v databázi jdou jako migrace, živá data zůstávají.
 *
 * Vrátit obnoví soubory živého webu ze snímku těsně před zveřejněním
 * (databáze zůstává, migrace se nevracejí). Úlohy z ClickUpu jdou dál rovnou
 * na živý web, mimo kopii. Viz docs/AGENT.md, Kopie živého webu.
 */
class CopyPublisher
{
    public function __construct(private readonly AgentServer $agent) {}

    /** @param  list<string>  $titles  nadpisy předání, která se zveřejní */
    public function start(string $project, array $titles, int $files, ?User $user): AgentPublication
    {
        $job = $this->agent->startCopyJob($project, 'zverejnit');

        return AgentPublication::create([
            'kind' => 'kopie',
            'project' => $project,
            'branch' => 'kopie',
            'title' => $titles !== [] ? implode(', ', $titles) : 'Úpravy webu ('.$files.' souborů)',
            'status' => PublicationStatus::Publishing,
            'job_id' => $job['id'],
            'user_id' => $user?->getKey(),
        ]);
    }

    public function revert(AgentPublication $original, ?User $user): AgentPublication
    {
        $job = $this->agent->startCopyJob($original->project, 'vratit', $original->created_at->getTimestamp());

        return AgentPublication::create([
            'kind' => 'kopie',
            'project' => $original->project,
            'branch' => 'kopie',
            'title' => 'Vrácení: '.$original->title,
            'status' => PublicationStatus::Publishing,
            'job_id' => $job['id'],
            'reverts_id' => $original->getKey(),
            'user_id' => $user?->getKey(),
        ]);
    }

    public function advance(AgentPublication $publication): void
    {
        try {
            $job = $this->agent->job($publication->project, (string) $publication->job_id);
        } catch (AgentUnavailable $e) {
            Log::warning('Zveřejnění kopie: služba agenta zatím neodpovídá', ['publication' => $publication->getKey(), 'error' => $e->getMessage()]);

            return;
        }

        if ($job === null || ! in_array($job['stav'], ['hotovo', 'chyba'], true)) {
            if ($publication->created_at->lt(now()->subMinutes(10))) {
                $publication->update(['status' => PublicationStatus::Failed, 'error' => 'Nahrání se do 10 minut nedokončilo. Mrkněte na frontu na serveru (taveo-agent-fronta).', 'finished_at' => now()]);
            }

            return;
        }

        $publication->update([
            'status' => $job['stav'] === 'hotovo' ? PublicationStatus::Done : PublicationStatus::Failed,
            'error' => $job['stav'] === 'hotovo' ? null : 'Nepovedlo se: '.$job['vystup'],
            'finished_at' => now(),
        ]);
    }
}
