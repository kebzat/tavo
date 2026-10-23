<?php

namespace App\Support\ClickUp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ClickUp API v2, jen to, co agent potřebuje: přečíst úkol a komentáře,
 * odpovědět do vlákna nebo přidat komentář, vypsat seznamy, členy týmu
 * a založit webhook. Úkoly ani komentáře nikdy neupravuje a nemaže.
 *
 * Token je osobní (pk_…), odpovědi agenta proto v ClickUpu nesou jméno
 * majitele tokenu. Poznají se podle začátku „Agent:“.
 */
class ClickUp
{
    private const BASE_URL = 'https://api.clickup.com/api/v2';

    public function configured(): bool
    {
        return filled(config('services.clickup.token'));
    }

    /**
     * Úkol: název, popis, odkaz a kde leží (seznam, prostor).
     *
     * @return array{id: string, name: string, description: string, url: ?string, list_id: ?string, list_name: ?string, space_id: ?string}
     */
    public function task(string $id): array
    {
        $task = $this->call(fn (PendingRequest $http) => $http->get('/task/'.rawurlencode($id)));

        return [
            'id' => (string) $task['id'],
            'name' => (string) ($task['name'] ?? ''),
            'description' => (string) ($task['text_content'] ?? $task['description'] ?? ''),
            'url' => $task['url'] ?? null,
            'list_id' => isset($task['list']['id']) ? (string) $task['list']['id'] : null,
            'list_name' => $task['list']['name'] ?? null,
            'space_id' => isset($task['space']['id']) ? (string) $task['space']['id'] : null,
        ];
    }

    /**
     * Komentáře úkolu od nejstaršího.
     *
     * @return list<array{id: string, author: string, date: ?string, text: string}>
     */
    public function comments(string $taskId): array
    {
        $data = $this->call(fn (PendingRequest $http) => $http->get('/task/'.rawurlencode($taskId).'/comment'));

        return collect($data['comments'] ?? [])
            ->map(fn (array $comment): array => [
                'id' => (string) $comment['id'],
                'author' => (string) ($comment['user']['username'] ?? 'někdo'),
                'date' => isset($comment['date']) ? date('j. n. H:i', (int) ($comment['date'] / 1000)) : null,
                'text' => (string) ($comment['comment_text'] ?? ''),
            ])
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * ID odpovědí ve vlákně komentáře.
     *
     * @return list<string>
     */
    public function replies(string $commentId): array
    {
        $data = $this->call(fn (PendingRequest $http) => $http->get('/comment/'.rawurlencode($commentId).'/reply'));

        return collect($data['comments'] ?? [])->pluck('id')->map(fn ($id): string => (string) $id)->all();
    }

    /**
     * Odpověď do vlákna komentáře. Vrátí ID nového komentáře. S `$mention`
     * (ID uživatele, nebo jejich seznam) je na konci označí, přijde jim upozornění.
     *
     * @param  string|list<string>|null  $mention
     */
    public function reply(string $commentId, string $text, string|array|null $mention = null): ?string
    {
        return $this->post('/comment/'.rawurlencode($commentId).'/reply', $text, $mention);
    }

    /**
     * Nový komentář k úkolu. Vrátí ID komentáře.
     *
     * @param  string|list<string>|null  $mention
     */
    public function comment(string $taskId, string $text, string|array|null $mention = null): ?string
    {
        return $this->post('/task/'.rawurlencode($taskId).'/comment', $text, $mention);
    }

    /**
     * Členové týmu (workspace): ID => jméno v ClickUpu.
     *
     * @return array<string, string>
     */
    public function members(): array
    {
        $teamId = (string) config('services.clickup.team_id');
        $team = collect($this->call(fn (PendingRequest $http) => $http->get('/team'))['teams'] ?? [])
            ->first(fn (array $team): bool => (string) $team['id'] === $teamId);

        return collect($team['members'] ?? [])
            ->mapWithKeys(fn (array $member): array => [(string) $member['user']['id'] => (string) ($member['user']['username'] ?? '')])
            ->all();
    }

    /**
     * Označení uživatele jde jen ve formátovaném komentáři (pole `comment`
     * se štítky). Když ho ClickUp odmítne, odejde aspoň prostý text.
     *
     * @param  string|list<string>|null  $mention
     */
    private function post(string $path, string $text, string|array|null $mention): ?string
    {
        // Štítky oddělené mezerou: štítek, mezera, štítek…
        $tags = collect((array) $mention)->filter(fn ($id): bool => filled($id))->values()
            ->flatMap(fn ($id, int $i): array => [...($i > 0 ? [['text' => ' ']] : []), ['type' => 'tag', 'user' => ['id' => (int) $id]]]);

        if ($tags->isNotEmpty()) {
            try {
                $data = $this->call(fn (PendingRequest $http) => $http->post($path, [
                    'comment' => [['text' => $text."\n\n"], ...$tags->all()],
                    'notify_all' => false,
                ]));

                return isset($data['id']) ? (string) $data['id'] : null;
            } catch (RuntimeException) {
                // Prostý text níž.
            }
        }

        $data = $this->call(fn (PendingRequest $http) => $http->post($path, [
            'comment_text' => $text,
            'notify_all' => false,
        ]));

        return isset($data['id']) ? (string) $data['id'] : null;
    }

    /**
     * Všechny seznamy týmu: prostor, složka, název. Pro přiřazení ke klientům.
     *
     * @return list<array{id: string, name: string, space_id: string, space: string, folder: ?string}>
     */
    public function lists(): array
    {
        $teamId = (string) config('services.clickup.team_id');
        $lists = [];

        foreach ($this->call(fn (PendingRequest $http) => $http->get("/team/{$teamId}/space", ['archived' => 'false']))['spaces'] ?? [] as $space) {
            $row = fn (array $list, ?string $folder): array => ['id' => (string) $list['id'], 'name' => (string) $list['name'], 'space_id' => (string) $space['id'], 'space' => (string) $space['name'], 'folder' => $folder];

            foreach ($this->call(fn (PendingRequest $http) => $http->get("/space/{$space['id']}/folder", ['archived' => 'false']))['folders'] ?? [] as $folder) {
                foreach ($folder['lists'] ?? [] as $list) {
                    $lists[] = $row($list, $folder['name']);
                }
            }

            foreach ($this->call(fn (PendingRequest $http) => $http->get("/space/{$space['id']}/list", ['archived' => 'false']))['lists'] ?? [] as $list) {
                $lists[] = $row($list, null);
            }
        }

        return $lists;
    }

    /** @return list<array<string, mixed>> */
    public function webhooks(): array
    {
        $teamId = (string) config('services.clickup.team_id');

        return $this->call(fn (PendingRequest $http) => $http->get("/team/{$teamId}/webhook"))['webhooks'] ?? [];
    }

    /**
     * Webhook jen na nové komentáře. Vrátí ID a tajemství pro ověření podpisu.
     *
     * @return array{id: string, secret: string}
     */
    public function createWebhook(string $endpoint): array
    {
        $teamId = (string) config('services.clickup.team_id');
        $data = $this->call(fn (PendingRequest $http) => $http->post("/team/{$teamId}/webhook", [
            'endpoint' => $endpoint,
            'events' => ['taskCommentPosted'],
        ]));

        return ['id' => (string) ($data['id'] ?? $data['webhook']['id']), 'secret' => (string) ($data['webhook']['secret'] ?? $data['secret'])];
    }

    /** Podpis webhooku: HMAC-SHA256 těla tajemstvím webhooku, hexadecimálně. */
    public static function validSignature(string $body, ?string $signature): bool
    {
        $secret = (string) config('services.clickup.webhook_secret');

        return $secret !== '' && is_string($signature) && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    /** @param  callable(PendingRequest): Response  $send */
    private function call(callable $send): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('ClickUp není nastavený, chybí CLICKUP_API_TOKEN.');
        }

        try {
            $data = $send(Http::baseUrl(self::BASE_URL)
                ->withHeaders(['Authorization' => (string) config('services.clickup.token')])
                ->acceptJson()
                ->timeout(20))->throw()->json();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('ClickUp: '.$e->getMessage(), previous: $e);
        }

        return is_array($data) ? $data : [];
    }
}
