<?php

namespace App\Support\Agent;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Kousek GitHub API, který potřebuje tlačítko Zveřejnit: pull request,
 * testy, sloučení, sledování nasazení, vrácení.
 *
 * Token (GITHUB_TOKEN) je fine-grained, jen na repozitáře projektů agenta:
 * Contents a Pull requests pro zápis, Actions pro čtení.
 * Agent v kontejneru ho nikdy nevidí, leží jen v .env webu.
 */
class GitHub
{
    public function __construct(
        private readonly ?string $token,
        private readonly string $url = 'https://api.github.com/',
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('services.github.token'), config('services.github.url'));
    }

    public function configured(): bool
    {
        return filled($this->token);
    }

    /** `git@github.com:kebzat/tavo.git` → `kebzat/tavo` */
    public static function repoFromSource(?string $source): ?string
    {
        return preg_match('~github\.com[:/]([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+?)(\.git)?$~', (string) $source, $m) ? $m[1] : null;
    }

    /** Commit, na kterém větev na GitHubu je. Null = větev tam (zatím) není. */
    public function branchSha(string $repo, string $branch): ?string
    {
        $response = $this->request('get', "repos/{$repo}/branches/".rawurlencode($branch));

        return $response->status() === 404 ? null : $this->ok($response)->json('commit.sha');
    }

    /**
     * Co větev přidává oproti main.
     *
     * @return array{ahead: int, behind: int, files: list<string>, commits: list<string>}
     */
    public function compare(string $repo, string $base, string $head): array
    {
        $data = $this->ok($this->request('get', "repos/{$repo}/compare/".rawurlencode($base).'...'.rawurlencode($head)))->json();

        return [
            'ahead' => (int) ($data['ahead_by'] ?? 0),
            'behind' => (int) ($data['behind_by'] ?? 0),
            'files' => array_column($data['files'] ?? [], 'filename'),
            'commits' => array_map(fn (array $c): string => strtok((string) ($c['commit']['message'] ?? ''), "\n"), $data['commits'] ?? []),
        ];
    }

    /**
     * Byl tenhle commit větve už sloučený pull requestem? Po „Squash and merge“
     * na GitHubu má main jiný commit, porovnání větve s main to nepozná.
     */
    public function mergedPull(string $repo, string $branch, string $sha): bool
    {
        $owner = strtok($repo, '/');
        $pulls = $this->ok($this->request('get', "repos/{$repo}/pulls", ['head' => "{$owner}:{$branch}", 'state' => 'closed', 'per_page' => 20]))->json();

        return collect($pulls)->contains(fn (array $pull): bool => filled($pull['merged_at'] ?? null) && ($pull['head']['sha'] ?? null) === $sha);
    }

    /**
     * Otevřený pull request větve do main, nebo nový.
     *
     * @return array{number: int, node_id: string}
     */
    public function openPull(string $repo, string $branch, string $title, string $body): array
    {
        $owner = strtok($repo, '/');
        $existing = $this->ok($this->request('get', "repos/{$repo}/pulls", ['head' => "{$owner}:{$branch}", 'state' => 'open']))->json();

        $pull = $existing[0] ?? $this->ok($this->request('post', "repos/{$repo}/pulls", [
            'title' => $title,
            'body' => $body,
            'head' => $branch,
            'base' => 'main',
        ]))->json();

        return ['number' => (int) $pull['number'], 'node_id' => (string) $pull['node_id']];
    }

    /** @return array<string, mixed> */
    public function pull(string $repo, int $number): array
    {
        return $this->ok($this->request('get', "repos/{$repo}/pulls/{$number}"))->json();
    }

    /**
     * Sloučí pull request, jen když je pořád na zkontrolovaném commitu.
     * Vrací commit sloučení na main.
     */
    public function merge(string $repo, int $number, string $sha, string $title, ?string $message = null): string
    {
        $response = $this->request('put', "repos/{$repo}/pulls/{$number}/merge", array_filter([
            'sha' => $sha,
            'merge_method' => 'squash',
            'commit_title' => $title.' (#'.$number.')',
            'commit_message' => $message,
        ]));

        if (in_array($response->status(), [405, 409], true)) {
            throw new GitHubError($response->status() === 409
                ? 'Agent mezitím na úpravě pokračoval. Zkontrolujte ji znovu.'
                : 'GitHub úpravu nechce sloučit: '.($response->json('message') ?? 'neznámý důvod').'.', conflict: true);
        }

        return (string) $this->ok($response)->json('sha');
    }

    /**
     * Běhy GitHub Actions na commitu: testy pull requestu, na main testy
     * a nasazení.
     *
     * @return list<array{name: string, event: ?string, status: string, conclusion: ?string, url: ?string, updated_at: ?string}>
     */
    public function runs(string $repo, string $sha): array
    {
        $runs = $this->ok($this->request('get', "repos/{$repo}/actions/runs", ['head_sha' => $sha, 'per_page' => 30]))->json('workflow_runs') ?? [];

        return array_map(fn (array $run): array => [
            'name' => (string) $run['name'],
            'event' => $run['event'] ?? null,
            'status' => (string) $run['status'],
            'conclusion' => $run['conclusion'] ?? null,
            'url' => $run['html_url'] ?? null,
            'updated_at' => $run['updated_at'] ?? null,
        ], $runs);
    }

    public function deleteBranch(string $repo, string $branch): void
    {
        $response = $this->request('delete', "repos/{$repo}/git/refs/heads/".str_replace('%2F', '/', rawurlencode($branch)));

        if (! in_array($response->status(), [204, 404, 422], true)) {
            $this->ok($response);
        }
    }

    /**
     * Pull request, který vrátí sloučený pull request (tlačítko Revert na GitHubu).
     *
     * @return array{number: int, node_id: string, branch: string, sha: string}
     */
    public function revert(string $nodeId, string $title): array
    {
        $response = $this->ok($this->request('post', 'graphql', [
            'query' => 'mutation($id: ID!, $title: String!) { revertPullRequest(input: {pullRequestId: $id, title: $title}) { revertPullRequest { number id headRefName headRefOid } } }',
            'variables' => ['id' => $nodeId, 'title' => $title],
        ]));

        $pull = $response->json('data.revertPullRequest.revertPullRequest')
            ?? throw new GitHubError('GitHub vrácení nezaložil: '.($response->json('errors.0.message') ?? 'neznámý důvod').'.');

        return ['number' => (int) $pull['number'], 'node_id' => (string) $pull['id'], 'branch' => (string) $pull['headRefName'], 'sha' => (string) $pull['headRefOid']];
    }

    private function http(): PendingRequest
    {
        if (! $this->configured()) {
            throw new GitHubError('Chybí GITHUB_TOKEN v .env webu, viz docs/AGENT.md (Zveřejnění).');
        }

        return Http::baseUrl($this->url)
            ->withToken((string) $this->token)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->acceptJson()
            ->timeout(15);
    }

    /** @param  array<string, mixed>  $data */
    private function request(string $method, string $path, array $data = []): Response
    {
        try {
            return $this->http()->{$method}($path, $data);
        } catch (ConnectionException $e) {
            throw new GitHubError('GitHub neodpovídá.', previous: $e);
        }
    }

    private function ok(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw new GitHubError(match ($response->status()) {
            401 => 'GitHub token neplatí (vypršel?). Vytvořte nový, viz docs/AGENT.md.',
            403, 404 => 'GitHub token nemá k repozitáři přístup: '.($response->json('message') ?? $response->status()).'.',
            default => 'GitHub vrátil chybu '.$response->status().': '.($response->json('message') ?? '').'.',
        });
    }
}
