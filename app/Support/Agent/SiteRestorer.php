<?php

namespace App\Support\Agent;

use App\Enums\PublicationStatus;
use App\Models\AgentPublication;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Tlačítko Vrátit u hotové práce na živém webu (projekt typu server):
 * web se vrátí do stavu z doby, kdy agent začal (soubor `zacatek` v předání).
 *
 * Soubory jdou ze snímku webu, databáze ze zálohy jen na výslovné přání:
 * smazala by i objednávky a zprávy od té doby. Před vrácením se uloží
 * současný stav, takže jde vrátit i vrácení (`agent vratit`). Dělá to root
 * na serveru (fronta → `agent obnovit`), tady se jen čeká na výsledek.
 */
class SiteRestorer
{
    public function __construct(private readonly AgentServer $agent) {}

    /** @param  array<string, mixed>  $item  předání z {@see AgentServer::handovers()} */
    public function start(string $project, array $item, bool $database, ?User $user): AgentPublication
    {
        $time = (int) ($item['zacatek'] ?? 0);

        if ($time <= 0) {
            throw new AgentUnavailable('Není jasné, kdy agent začal. Požádejte ho o předání znovu.');
        }

        $job = $this->agent->restoreSite($project, $item['jmeno'], $time, $database);

        return AgentPublication::create([
            'kind' => 'restore',
            'project' => $project,
            'branch' => $item['jmeno'],
            'title' => 'Vrácení: '.$item['nadpis'],
            'description' => $database ? 'Soubory i databáze' : 'Jen soubory',
            'status' => PublicationStatus::Restoring,
            'job_id' => $job,
            'user_id' => $user?->getKey(),
        ]);
    }

    public function advance(AgentPublication $publication): void
    {
        try {
            $result = $this->agent->queueResult((string) $publication->job_id);
        } catch (AgentUnavailable $e) {
            Log::warning('Vrácení webu: agent zatím neodpovídá', ['publication' => $publication->getKey(), 'error' => $e->getMessage()]);

            return;
        }

        if ($result === null) {
            if ($publication->created_at->lt(now()->subMinutes(15))) {
                $publication->update(['status' => PublicationStatus::Failed, 'error' => 'Server vrácení do 15 minut nedokončil. Mrkněte na frontu (taveo-agent-fronta).', 'finished_at' => now()]);
            }

            return;
        }

        $publication->update([
            'status' => $result['ok'] ? PublicationStatus::Restored : PublicationStatus::Failed,
            'error' => $result['ok'] ? null : 'Vrácení se nepovedlo: '.$result['vystup'],
            'description' => $result['ok'] ? trim($publication->description.'. '.$result['vystup']) : $publication->description,
            'finished_at' => now(),
        ]);

        // Vrácená práce už ke kontrole není.
        if ($result['ok']) {
            rescue(fn () => $this->agent->discardHandover($publication->project, $publication->branch), report: false);
        }
    }
}
