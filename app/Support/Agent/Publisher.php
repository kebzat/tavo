<?php

namespace App\Support\Agent;

use App\Enums\PublicationStatus;
use App\Models\AgentPublication;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tlačítko Zveřejnit v nástrojích: hotová práce agenta (větev agent/…)
 * jde přes pull request a testy do main a odtud nasazením na web.
 *
 * Web na tomhle serveru (taveo) jde jinou, rychlou cestou: {@see InstantPublisher}
 * ho nahraje rovnou ze serveru a GitHub je jen historie.
 *
 *   Odesílá se na GitHub → Běží testy → (sloučení) → Nasazuje se → Na webu
 *
 * Sloučí se jen commit, který člověk viděl (snímky, popis). Když agent
 * mezitím pokračoval, GitHub sloučení odmítne. Stav posouvá {@see advance()}:
 * plánovač každou minutu (agent:zverejneni) a stránka projektu, dokud je otevřená.
 *
 * Agent sám do main pořád nemůže. Token GitHubu má jen web.
 */
class Publisher
{
    /** Úpravy těchto cest mění, co běží na serveru: v nástrojích dostanou varování. */
    private const SENSITIVE = ['.github/', 'infra/'];

    private const FAILED_CONCLUSIONS = ['failure', 'timed_out', 'cancelled', 'action_required', 'startup_failure'];

    public function __construct(
        private readonly AgentServer $agent,
        private readonly GitHub $github,
    ) {}

    public function configured(): bool
    {
        return $this->github->configured();
    }

    public function repo(string $project): ?string
    {
        return GitHub::repoFromSource($this->agent->project($project)['zdroj'] ?? null);
    }

    /**
     * Hotová práce, kterou jde zveřejnit: větve ze schránky, které main ještě
     * nemá. Ty, co se právě zveřejňují, ukazuje {@see recent()}.
     *
     * @return list<array<string, mixed>>
     */
    public function handovers(string $project): array
    {
        $repo = $this->repo($project);
        $instant = InstantPublisher::enabled($project);
        // Co se zveřejňuje, nebo už je (okamžitě) na webu a jen čeká na historii.
        $taken = AgentPublication::query()->where('project', $project)
            ->where(fn ($query) => $query->running()->orWhere(fn ($query) => $query->whereNotNull('job_id')->where('status', PublicationStatus::Done)))
            ->pluck('sha')->filter()->all();

        return collect($this->agent->handovers($project))
            ->reject(fn (array $item): bool => in_array($item['sha'], $taken, true))
            ->map(function (array $item) use ($repo, $project, $instant): ?array {
                $compare = $repo ? $this->compare($repo, $item['sha']) : null;

                // Main už úpravu má (sloučená dřív, ručně, i squashem): není co
                // zveřejnit. Ze schránky ji rovnou uklidit, ať se neukazuje dál.
                if (($compare['ahead'] ?? null) === 0 || ($compare !== null && $this->merged($repo, $item['vetev'], $item['sha']))) {
                    rescue(fn () => $this->agent->discardHandover($project, $item['jmeno'], $item['sha']), report: false);

                    return null;
                }

                $files = $compare['files'] ?? [];

                if ($instant) {
                    app(InstantPublisher::class)->prepare($project, $item['sha']);
                }

                return [
                    'name' => $item['jmeno'],
                    'branch' => $item['vetev'],
                    'sha' => $item['sha'],
                    'tests' => $compare === null || $instant ? null : $this->tests($repo, $item['sha'], $compare['behind']),
                    'instant' => $instant,
                    'title' => self::title($item),
                    'description' => $item['popis'],
                    'images' => $item['snimky'],
                    'time' => $item['cas'],
                    'commits' => $compare['commits'] ?? [],
                    'files' => count($files),
                    'sensitive' => array_values(array_filter($files, fn (string $file): bool => Str::startsWith($file, self::SENSITIVE))),
                    'on_github' => $instant || $compare !== null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<AgentPublication> */
    public function recent(string $project, int $limit = 10): array
    {
        return AgentPublication::query()->with('user')->where('project', $project)->latest('id')->limit($limit)->get()->all();
    }

    /** Zveřejnit hotovou práci. `$sha` je commit, který člověk zkontroloval. */
    public function start(string $project, string $name, string $sha, ?User $user): AgentPublication
    {
        // Dvojklik, nebo Pavel a Tom zároveň: jedno zveřejnění stačí.
        $running = AgentPublication::query()->where('project', $project)->where('sha', $sha)->running()->first();

        if ($running !== null) {
            return $running;
        }

        $item = collect($this->agent->handovers($project))->firstWhere('jmeno', $name);

        if ($item === null || $item['sha'] !== $sha) {
            throw new GitHubError('Agent mezitím na úpravě pokračoval. Zkontrolujte ji znovu.');
        }

        if (InstantPublisher::enabled($project)) {
            return app(InstantPublisher::class)->start($project, $item, self::title($item), $this->repo($project), $user);
        }

        $repo = $this->repo($project) ?? throw new GitHubError('Projekt nemá repozitář na GitHubu.');

        if (! $this->github->configured()) {
            throw new GitHubError('Chybí GITHUB_TOKEN v .env webu, viz docs/AGENT.md (Zveřejnění).');
        }

        $publication = AgentPublication::create([
            'project' => $project,
            'repo' => $repo,
            'branch' => $item['vetev'],
            'sha' => $sha,
            'title' => self::title($item),
            'description' => $item['popis'],
            'status' => PublicationStatus::Waiting,
            'user_id' => $user?->getKey(),
        ]);

        $this->advance($publication);

        return $publication;
    }

    /** Vrátit zveřejněnou úpravu: GitHub založí opačný pull request a ten jde stejnou cestou. */
    public function revert(AgentPublication $original, ?User $user): AgentPublication
    {
        // Živý web z kopie: soubory ze snímku před zveřejněním.
        if ($original->kind === 'kopie' && $original->status->isMerged()) {
            return app(CopyPublisher::class)->revert($original, $user);
        }

        // Nahrané rovnou ze serveru se vrací stejně: předchozí verze zpátky na web.
        if ($original->job_id !== null && $original->status->isMerged()) {
            return app(InstantPublisher::class)->revert($original, $user);
        }

        if (! $original->status->isMerged() || $original->pr_node_id === null) {
            throw new GitHubError('Vrátit jde jen úpravu, která je na webu.');
        }

        $title = 'Vrácení: '.$original->title;
        $pull = $this->github->revert($original->pr_node_id, $title);

        $publication = AgentPublication::create([
            'project' => $original->project,
            'repo' => $original->repo,
            'branch' => $pull['branch'],
            'sha' => $pull['sha'],
            'title' => $title,
            'status' => PublicationStatus::Testing,
            'pr_number' => $pull['number'],
            'pr_node_id' => $pull['node_id'],
            'pr_opened_at' => now(),
            'reverts_id' => $original->getKey(),
            'user_id' => $user?->getKey(),
        ]);

        $this->advance($publication);

        return $publication;
    }

    /** Posune zveřejnění o krok, kam až to jde. Volá se opakovaně, nic nepokazí. */
    public function advance(AgentPublication $publication): void
    {
        if (! $publication->status->isRunning()) {
            return;
        }

        // Vrácení živého webu z karty už není (karta u živého webu není),
        // starý záznam se jen nechá v historii.
        if ($publication->isRestore()) {
            return;
        }

        if ($publication->status === PublicationStatus::Publishing) {
            $publication->kind === 'kopie'
                ? app(CopyPublisher::class)->advance($publication)
                : app(InstantPublisher::class)->advance($publication);

            return;
        }

        Cache::lock('agent-publication-'.$publication->getKey(), 60)->get(function () use ($publication): void {
            $publication->refresh();

            try {
                match ($publication->status) {
                    PublicationStatus::Waiting => $this->openPull($publication),
                    PublicationStatus::Testing => $this->mergeWhenGreen($publication),
                    PublicationStatus::Deploying => $this->watchDeploy($publication),
                    default => null,
                };
            } catch (GitHubError $e) {
                // Spojení nebo GitHub chvíli zlobí: zkusí se za minutu znovu.
                // Konflikt (agent pokračoval, main se změnil) čekáním nezmizí.
                if ($e->conflict) {
                    $this->finish($publication, PublicationStatus::Conflict, $e->getMessage());
                } elseif ($publication->updated_at->lt(now()->subMinutes(30))) {
                    $this->finish($publication, PublicationStatus::Failed, $e->getMessage());
                } else {
                    Log::warning('Zveřejnění: GitHub zatím nevyšel', ['publication' => $publication->getKey(), 'error' => $e->getMessage()]);
                }
            }
        });
    }

    /** Krok 1: počkat, až schránka pošle větev na GitHub, a založit pull request. */
    private function openPull(AgentPublication $publication): void
    {
        if ($this->github->branchSha($publication->repo, $publication->branch) !== $publication->sha) {
            if ($publication->created_at->lt(now()->subMinutes(10))) {
                $this->finish($publication, PublicationStatus::Failed, 'Úprava se do 10 minut nedostala na GitHub. Běží na serveru přeposílání (agent preposlat v cronu)?');
            }

            return;
        }

        $body = trim(($publication->description ?? '')."\n\n---\nZveřejnil(a) ".($publication->user?->name ?? 'neznámý').' tlačítkem v nástrojích Taveo.');
        $pull = $this->github->openPull($publication->repo, $publication->branch, $publication->title, $body);

        $publication->update([
            'status' => PublicationStatus::Testing,
            'pr_number' => $pull['number'],
            'pr_node_id' => $pull['node_id'],
            'pr_opened_at' => now(),
        ]);

        $this->mergeWhenGreen($publication);
    }

    /**
     * Krok 2: po zelených testech sloučit do main. Testy běží už po odeslání
     * větve (ci.yml, push agent/…), takže když jsou hotové, slučuje se hned.
     */
    private function mergeWhenGreen(AgentPublication $publication): void
    {
        $runs = $this->github->runs($publication->repo, $publication->sha);
        $behind = $this->github->compare($publication->repo, 'main', $publication->sha)['behind'];
        $state = self::testState($runs, $behind);

        // Repozitář bez testů: po pěti minutách bez jediného běhu se slučuje.
        if ($state === null && $publication->pr_opened_at?->gt(now()->subMinutes(5))) {
            return;
        }

        if ($state === 'failed') {
            $failed = collect($runs)->first(fn (array $run): bool => in_array($run['conclusion'], self::FAILED_CONCLUSIONS, true));
            $publication->update(['details_url' => $failed['url'] ?? null]);
            $this->finish($publication, PublicationStatus::TestsFailed, 'Neprošlo: '.($failed['name'] ?? 'testy').'. Napište agentovi, ať se na testy podívá a opraví je, pak zveřejněte znovu.');

            return;
        }

        if ($state === 'running') {
            if ($publication->pr_opened_at?->lt(now()->subMinutes(45))) {
                $this->finish($publication, PublicationStatus::Failed, 'Testy běží přes 45 minut. Mrkněte na GitHub, jestli nestojí.');
            }

            return;
        }

        $pull = $this->github->pull($publication->repo, (int) $publication->pr_number);

        if (($pull['mergeable'] ?? null) === null && ! ($pull['merged'] ?? false)) {
            return; // GitHub ještě počítá, jestli jde sloučit.
        }

        if (($pull['mergeable'] ?? null) === false) {
            $this->finish($publication, PublicationStatus::Conflict, 'Na webu se mezitím změnilo něco, co se s úpravou překrývá. Napište agentovi: „Sluč do větve aktuální main a vyřeš konflikty.“ Pak zveřejněte znovu.');

            return;
        }

        $mergeSha = ($pull['merged'] ?? false)
            ? (string) $pull['merge_commit_sha']
            : $this->github->merge($publication->repo, (int) $publication->pr_number, $publication->sha, $publication->title);

        $publication->update([
            'status' => PublicationStatus::Deploying,
            'merge_sha' => $mergeSha,
            'merged_at' => now(),
            'details_url' => null,
        ]);

        $this->afterMerge($publication);
    }

    /** Uklidit větev a u projektu se službou agenta ji nechat aktualizovat. */
    private function afterMerge(AgentPublication $publication): void
    {
        rescue(fn () => $this->github->deleteBranch($publication->repo, $publication->branch), report: false);

        if (! $publication->isRevert()) {
            rescue(fn () => $this->agent->discardHandover($publication->project, Str::after($publication->branch, 'agent/'), $publication->sha), report: false);
        }

        if ($publication->project === config('services.agent.infra_project')) {
            rescue(fn () => $this->agent->updateService(), report: false);
        }
    }

    /**
     * Krok 3: počkat na nasazení (běh GitHub Actions s „deploy“ v názvu,
     * spuštěný pushem do main). Testy na main nasazení neblokují, ty už
     * proběhly v pull requestu.
     */
    private function watchDeploy(AgentPublication $publication): void
    {
        $runs = collect($this->github->runs($publication->repo, (string) $publication->merge_sha));
        $deploy = $runs->first(fn (array $run): bool => (bool) preg_match('/deploy|nasaz/i', $run['name'])
            && in_array($run['event'] ?? 'push', ['push', 'workflow_dispatch', 'workflow_run'], true));

        if ($deploy !== null) {
            if ($deploy['status'] !== 'completed') {
                return;
            }

            $publication->update(['details_url' => $deploy['url']]);
            $deploy['conclusion'] === 'success'
                ? $this->finish($publication, PublicationStatus::Done)
                : $this->finish($publication, PublicationStatus::Failed, 'Nasazení na web selhalo. Na webu zůstala předchozí verze.');

            return;
        }

        // Bez nasazovacího běhu (repozitář ho nemá): sloučeno, až všechno
        // doběhne a dvě minuty se nic nového neobjeví. „Na webu“ by lhalo.
        $quiet = $runs->every(fn (array $run): bool => $run['status'] === 'completed' && $run['updated_at'] !== null && now()->subMinutes(2)->gt($run['updated_at']));

        if ($quiet && $publication->merged_at?->lt(now()->subMinutes(3))) {
            $this->finish($publication, PublicationStatus::Merged);
        } elseif ($publication->merged_at?->lt(now()->subMinutes(45))) {
            $this->finish($publication, PublicationStatus::Failed, 'Nasazení se do 45 minut nedokončilo. Mrkněte na GitHub (Actions).');
        }
    }

    /**
     * Výsledek testů commitu z běhů GitHub Actions: passed / failed / running,
     * null = žádné testy. Každý workflow stačí jednou zelený (push větve
     * i pull request testují totéž). Větev pozadu za main ale testuje jen
     * pull request, ten zkouší výsledek sloučení.
     *
     * @param  list<array{name: string, event: ?string, status: string, conclusion: ?string}>  $runs
     */
    public static function testState(array $runs, int $behind): ?string
    {
        $runs = collect($runs)
            ->reject(fn (array $run): bool => (bool) preg_match('/deploy|nasaz/i', $run['name']))
            ->when($behind > 0, fn ($runs) => $runs->where('event', 'pull_request'));

        if ($runs->isEmpty()) {
            return null;
        }

        $states = $runs->groupBy('name')->map(fn ($group): string => match (true) {
            $group->contains(fn (array $run): bool => in_array($run['conclusion'], ['success', 'neutral', 'skipped'], true)) => 'passed',
            $group->contains(fn (array $run): bool => $run['status'] !== 'completed') => 'running',
            default => 'failed',
        });

        return match (true) {
            $states->contains('failed') => 'failed',
            $states->contains('running') => 'running',
            default => 'passed',
        };
    }

    private function finish(AgentPublication $publication, PublicationStatus $status, ?string $error = null): void
    {
        $publication->update(['status' => $status, 'error' => $error, 'finished_at' => now()]);
    }

    /** @return array{ahead: int, behind: int, files: list<string>, commits: list<string>}|null */
    private function compare(string $repo, string $sha): ?array
    {
        // Porovnává se commit ze schránky. Dokud není na GitHubu (nebo chybí
        // token), vrací null a v nástrojích je „odesílá se“. To se neukládá.
        return Cache::remember('agent-compare-'.$repo.'-'.$sha, now()->addMinutes(2), fn (): ?array => rescue(
            fn (): array => $this->github->compare($repo, 'main', $sha),
            null,
            report: false,
        ));
    }

    /** Stav testů hotové práce pro kartu (před kliknutím na Zveřejnit). */
    private function tests(string $repo, string $sha, int $behind): ?string
    {
        return Cache::remember('agent-tests-'.$repo.'-'.$sha.'-'.$behind, now()->addSeconds(20), fn (): ?string => rescue(
            fn (): ?string => self::testState($this->github->runs($repo, $sha), $behind),
            null,
            report: false,
        ));
    }

    /** Sloučený pull request se už nezmění, „ne“ se ptá znovu za dvě minuty. */
    private function merged(string $repo, string $branch, string $sha): bool
    {
        $key = 'agent-merged-'.$repo.'-'.$sha;

        if (Cache::get($key)) {
            return true;
        }

        $merged = (bool) rescue(fn (): bool => $this->github->mergedPull($repo, $branch, $sha), false, report: false);
        Cache::put($key, $merged, $merged ? now()->addDays(30) : now()->addMinutes(2));

        return $merged;
    }

    /** Nadpis z popisu agenta („# …“), jinak z commitu. */
    private static function title(array $item): string
    {
        $heading = preg_match('/^#\s+(.+)$/m', (string) $item['popis'], $m) ? trim($m[1]) : null;

        return Str::limit($heading ?: $item['nadpis'], 180, '…');
    }
}
