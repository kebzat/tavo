<?php

namespace App\Support\Agent;

use App\Enums\PublicationStatus;
use App\Models\AgentPublication;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Okamžité zveřejnění webu, který běží na tomhle serveru (taveo): bez GitHubu
 * a bez testů, na webu do pár sekund.
 *
 *   Zveřejnit → služba agenta sestaví vydání (větev spojená s main, sestavuje
 *   se předem, jakmile se úprava objeví v nástrojích) → root ho nahraje na web
 *   (záloha databáze, předchozí verze stranou, migrace, cache) → Na webu.
 *
 * Git je jen historie: po nahrání se úprava sloučí do main na GitHubu
 * ({@see syncHistory()}), na to se nečeká. Nasazení z GitHubu (deploy.yml)
 * pozná, že stejná verze už na webu je, a nic nedělá.
 *
 * Vrátit nahraje zpátky verzi webu před posledním nasazením (databáze zůstává).
 */
class InstantPublisher
{
    public function __construct(
        private readonly AgentServer $agent,
        private readonly GitHub $github,
    ) {}

    public static function enabled(string $project): bool
    {
        return in_array($project, (array) config('services.agent.deploy_projects'), true);
    }

    /** Sestavit vydání předem, ať je po kliknutí na webu hned. Jednou za commit. */
    public function prepare(string $project, string $sha): void
    {
        if (Cache::add('agent-vydani-'.$project.'-'.$sha, true, now()->addMinutes(30))) {
            rescue(fn () => $this->agent->startRelease($project, $sha, deploy: false), report: false);
        }
    }

    /** @param  array<string, mixed>  $item  hotová práce ze služby agenta */
    public function start(string $project, array $item, string $title, ?string $repo, ?User $user): AgentPublication
    {
        $job = $this->agent->startRelease($project, $item['sha'], deploy: true);

        return AgentPublication::create([
            'project' => $project,
            'repo' => $repo,
            'branch' => $item['vetev'],
            'sha' => $item['sha'],
            'title' => $title,
            'description' => $item['popis'],
            'status' => PublicationStatus::Publishing,
            'job_id' => $job['id'],
            'user_id' => $user?->getKey(),
        ]);
    }

    /** Web zpátky na verzi před posledním nasazením tlačítkem. */
    public function revert(AgentPublication $original, ?User $user): AgentPublication
    {
        $job = $this->agent->rollbackRelease($original->project);

        return AgentPublication::create([
            'project' => $original->project,
            'repo' => $original->repo,
            'branch' => $original->branch,
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
            // Přes …/vydani/<id>: tuhle cestu zná i služba před aktualizací (zveřejnění
            // nového kódu tlačítkem čeká na starou službu, ta se aktualizuje až po něm).
            $job = $this->agent->release($publication->project, (string) $publication->job_id);
        } catch (AgentUnavailable $e) {
            Log::warning('Zveřejnění: služba agenta zatím neodpovídá', ['publication' => $publication->getKey(), 'error' => $e->getMessage()]);

            return;
        }

        if ($job === null || in_array($job['stav'], ['sestavuje', 'nahrava'], true)) {
            if ($publication->created_at->lt(now()->subMinutes(10))) {
                $this->finish($publication, PublicationStatus::Failed, 'Nahrání se do 10 minut nedokončilo. Mrkněte na frontu na serveru (taveo-agent-fronta).');
            }

            return;
        }

        if ($job['stav'] === 'chyba') {
            $this->finish($publication, $job['konflikt'] ?? false ? PublicationStatus::Conflict : PublicationStatus::Failed, $job['konflikt'] ?? false
                ? $job['vystup'].' Napište agentovi: „Sluč do větve aktuální main a vyřeš konflikty.“ Pak zveřejněte znovu.'
                : 'Nepovedlo se: '.$job['vystup']);

            return;
        }

        $this->finish($publication, PublicationStatus::Done);
        $this->syncHistory($publication);
    }

    /**
     * Historie na GitHubu: úprava (nebo vrácení) se sloučí do main. Zkouší se
     * po nahrání a pak každou minutu (agent:zverejneni), dokud větev nedorazí
     * na GitHub. Když to nejde (konflikt), poznamená se to a dál se nezkouší.
     */
    public function syncHistory(AgentPublication $publication): void
    {
        if ($publication->merge_sha !== null || $publication->error !== null || $publication->repo === null || ! $this->github->configured()) {
            return;
        }

        try {
            if ($publication->isRevert()) {
                $original = $publication->reverts;

                if ($original?->pr_node_id === null || $original->merge_sha === null) {
                    return;
                }

                $pull = $this->github->revert($original->pr_node_id, $publication->title);
                $merged = $this->github->merge($publication->repo, $pull['number'], $pull['sha'], $publication->title);
                $publication->update(['pr_number' => $pull['number'], 'pr_node_id' => $pull['node_id'], 'merge_sha' => $merged, 'merged_at' => now()]);
                rescue(fn () => $this->github->deleteBranch($publication->repo, $pull['branch']), report: false);

                return;
            }

            // Schránka pošle větev na GitHub do minuty, do té doby není co slučovat.
            if ($this->github->branchSha($publication->repo, $publication->branch) !== $publication->sha) {
                return;
            }

            $pull = $this->github->openPull($publication->repo, $publication->branch, $publication->title, trim(($publication->description ?? '')."\n\n---\nZveřejnil(a) ".($publication->user?->name ?? 'neznámý').' tlačítkem v nástrojích Taveo, rovnou na web.'));
            $merged = $this->github->merge($publication->repo, $pull['number'], $publication->sha, $publication->title);
            $publication->update(['pr_number' => $pull['number'], 'pr_node_id' => $pull['node_id'], 'merge_sha' => $merged, 'merged_at' => now()]);

            rescue(fn () => $this->github->deleteBranch($publication->repo, $publication->branch), report: false);
            rescue(fn () => $this->agent->discardHandover($publication->project, Str::after($publication->branch, 'agent/'), $publication->sha), report: false);

            if ($publication->project === config('services.agent.infra_project')) {
                rescue(fn () => $this->agent->updateService(), report: false);
            }
        } catch (GitHubError $e) {
            // Web je v pořádku, jen historie neprošla: GitHub chvíli zlobí, nebo konflikt.
            if ($e->conflict || $publication->finished_at?->lt(now()->subDay())) {
                $publication->update(['error' => 'Na webu je, do historie na GitHubu se nedostalo: '.$e->getMessage()]);
            }
        }
    }

    /** Hotová zveřejnění, která ještě nejsou v historii na GitHubu. */
    public function syncPendingHistory(): void
    {
        AgentPublication::query()
            ->where('kind', 'publish')
            ->whereNotNull('job_id')
            ->where('status', PublicationStatus::Done)
            ->whereNull('merge_sha')
            ->whereNull('error')
            ->where('created_at', '>', now()->subDays(2))
            ->get()
            ->each(fn (AgentPublication $publication) => $this->syncHistory($publication));
    }

    private function finish(AgentPublication $publication, PublicationStatus $status, ?string $error = null): void
    {
        $publication->update(['status' => $status, 'error' => $error, 'finished_at' => now()]);
    }
}
