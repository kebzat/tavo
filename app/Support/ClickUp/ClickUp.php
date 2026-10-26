<?php

namespace App\Support\ClickUp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * ClickUp API v2, jen to, co agent potřebuje: přečíst úkol a komentáře,
 * odpovědět do vlákna nebo přidat komentář, vypsat seznamy, členy týmu
 * a založit webhook. Pro načtení klientů ze složek ještě přečte celou složku
 * (úkoly, přílohy, Docs přes API v3). Úkoly ani komentáře nikdy neupravuje a nemaže.
 *
 * Token je osobní (pk_…), odpovědi agenta proto v ClickUpu nesou jméno
 * majitele tokenu. Poznají se podle začátku „Agent:“.
 */
class ClickUp
{
    /** ClickUp pustí 100 dotazů za minutu, pak vrací 429. Tolikrát se počká a zkusí znovu. */
    private const RATE_LIMIT_RETRIES = 3;

    public function configured(): bool
    {
        return filled(config('services.clickup.token'));
    }

    /**
     * Úkol: název, popis, odkaz a kde leží (seznam, prostor).
     *
     * @return array{id: string, name: string, description: string, url: ?string, list_id: ?string, list_name: ?string, folder_id: ?string, space_id: ?string}
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
            // Seznam přímo v prostoru má v ClickUpu skrytou složku „hidden“, ta ke klientovi nepatří.
            'folder_id' => isset($task['folder']['id']) && empty($task['folder']['hidden']) ? (string) $task['folder']['id'] : null,
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

    /**
     * Složky všech prostorů: každá je u nás jeden klient (Projekty PAVELVCELIS.cz,
     * Projekty TAVEO.cz). Pro načtení klientů z ClickUpu.
     *
     * @return list<array{id: string, name: string, space_id: string, space: string, lists: list<array{id: string, name: string}>}>
     */
    public function folders(): array
    {
        $teamId = (string) config('services.clickup.team_id');
        $folders = [];

        foreach ($this->call(fn (PendingRequest $http) => $http->get("/team/{$teamId}/space", ['archived' => 'false']))['spaces'] ?? [] as $space) {
            foreach ($this->call(fn (PendingRequest $http) => $http->get("/space/{$space['id']}/folder", ['archived' => 'false']))['folders'] ?? [] as $folder) {
                $folders[] = [
                    'id' => (string) $folder['id'],
                    'name' => (string) $folder['name'],
                    'space_id' => (string) $space['id'],
                    'space' => (string) $space['name'],
                    'lists' => collect($folder['lists'] ?? [])->map(fn (array $list): array => ['id' => (string) $list['id'], 'name' => (string) $list['name']])->values()->all(),
                ];
            }
        }

        return $folders;
    }

    /**
     * Jedna složka se seznamy (i archivovanými, ať se načte opravdu všechno).
     *
     * @return array{id: string, name: string, lists: list<array{id: string, name: string}>}
     */
    public function folder(string $id): array
    {
        $folder = $this->call(fn (PendingRequest $http) => $http->get('/folder/'.rawurlencode($id)));
        $archived = $this->call(fn (PendingRequest $http) => $http->get('/folder/'.rawurlencode($id).'/list', ['archived' => 'true']))['lists'] ?? [];

        return [
            'id' => (string) $folder['id'],
            'name' => (string) ($folder['name'] ?? ''),
            'lists' => collect([...($folder['lists'] ?? []), ...$archived])
                ->unique('id')
                ->map(fn (array $list): array => ['id' => (string) $list['id'], 'name' => (string) $list['name']])
                ->values()
                ->all(),
        ];
    }

    /**
     * Všechny úkoly seznamu i s hotovými a podúkoly, jak je vrací ClickUp
     * (popis v Markdownu). Po stránkách po stovce.
     *
     * @return list<array<string, mixed>>
     */
    public function listTasks(string $listId): array
    {
        $tasks = [];

        for ($page = 0; $page < 50; $page++) {
            $data = $this->call(fn (PendingRequest $http) => $http->get('/list/'.rawurlencode($listId).'/task', [
                'page' => $page,
                'archived' => 'false',
                'include_closed' => 'true',
                'subtasks' => 'true',
                'include_markdown_description' => 'true',
            ]));
            $tasks = [...$tasks, ...($data['tasks'] ?? [])];

            if (empty($data['tasks']) || ($data['last_page'] ?? true)) {
                break;
            }
        }

        return $tasks;
    }

    /**
     * Úkol se vším, co výpis seznamu nevrátí (přílohy, checklisty).
     *
     * @return array<string, mixed>
     */
    public function taskDetail(string $id): array
    {
        return $this->call(fn (PendingRequest $http) => $http->get('/task/'.rawurlencode($id), ['include_markdown_description' => 'true']));
    }

    /**
     * Všechny komentáře úkolu od nejstaršího. ClickUp jich vrací 25, starší
     * se načtou podle data a ID nejstaršího z předchozí dávky.
     *
     * @return list<array{author: string, date: ?string, text: string}>
     */
    public function allComments(string $taskId): array
    {
        $comments = [];
        $query = [];

        for ($page = 0; $page < 20; $page++) {
            $batch = $this->call(fn (PendingRequest $http) => $http->get('/task/'.rawurlencode($taskId).'/comment', $query))['comments'] ?? [];
            $comments = [...$comments, ...$batch];
            $oldest = end($batch);

            if (count($batch) < 25 || ! is_array($oldest)) {
                break;
            }

            $query = ['start' => $oldest['date'], 'start_id' => $oldest['id']];
        }

        return collect($comments)
            ->unique('id')
            ->sortBy(fn (array $comment): int => (int) ($comment['date'] ?? 0))
            ->map(fn (array $comment): array => [
                'author' => (string) ($comment['user']['username'] ?? 'někdo'),
                'date' => isset($comment['date']) ? date('j. n. Y H:i', (int) ($comment['date'] / 1000)) : null,
                'text' => trim((string) ($comment['comment_text'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * Docs uložené ve složce nebo v seznamu (pohled Doc). Hledá se podle
     * rodiče a pro jistotu se ověří, že rodič opravdu sedí.
     *
     * @return list<array{id: string, name: string}>
     */
    public function docs(string $parentId): array
    {
        $teamId = (string) config('services.clickup.team_id');
        $docs = [];
        $cursor = null;

        for ($page = 0; $page < 20; $page++) {
            $data = $this->call(fn (PendingRequest $http) => $http->get($this->v3()."/workspaces/{$teamId}/docs", array_filter([
                'parent_id' => $parentId,
                'deleted' => 'false',
                'next_cursor' => $cursor,
            ])));
            $docs = [...$docs, ...($data['docs'] ?? [])];
            $cursor = $data['next_cursor'] ?? null;

            if (blank($cursor) || empty($data['docs'])) {
                break;
            }
        }

        return collect($docs)
            ->filter(fn (array $doc): bool => (string) ($doc['parent']['id'] ?? '') === $parentId && empty($doc['deleted']))
            ->map(fn (array $doc): array => ['id' => (string) $doc['id'], 'name' => (string) ($doc['name'] ?? 'Doc')])
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Pohledy typu Doc ve složce. Doc přidaný jako pohled má stejné ID jako
     * pohled, takže se najde, i kdyby ho hledání podle rodiče nevrátilo.
     *
     * @return list<array{id: string, name: string}>
     */
    public function docViews(string $folderId): array
    {
        return collect($this->call(fn (PendingRequest $http) => $http->get('/folder/'.rawurlencode($folderId).'/view'))['views'] ?? [])
            ->filter(fn (array $view): bool => ($view['type'] ?? null) === 'doc')
            ->map(fn (array $view): array => ['id' => (string) $view['id'], 'name' => (string) ($view['name'] ?? 'Doc')])
            ->values()
            ->all();
    }

    /**
     * Stránky Docu v Markdownu, i vnořené (plochý seznam, `depth` od 0).
     *
     * @return list<array{name: string, depth: int, content: string}>
     */
    public function docPages(string $docId): array
    {
        $teamId = (string) config('services.clickup.team_id');
        $pages = $this->call(fn (PendingRequest $http) => $http->get($this->v3()."/workspaces/{$teamId}/docs/".rawurlencode($docId).'/pages', [
            'max_page_depth' => -1,
            'content_format' => 'text/md',
        ]));

        $flatten = function (array $pages, int $depth) use (&$flatten): array {
            $rows = [];

            foreach ($pages as $page) {
                if (! is_array($page) || ! empty($page['deleted']) || ! empty($page['archived'])) {
                    continue;
                }

                $rows[] = ['name' => (string) ($page['name'] ?? ''), 'depth' => $depth, 'content' => trim((string) ($page['content'] ?? ''))];
                $rows = [...$rows, ...$flatten($page['pages'] ?? [], $depth + 1)];
            }

            return $rows;
        };

        // ClickUp vrací buď rovnou pole stránek, nebo {pages: […]}.
        return $flatten(array_is_list($pages) ? $pages : ($pages['pages'] ?? []), 0);
    }

    /**
     * Příloha úkolu. Soubory na doménách ClickUpu dostanou token (u novějších
     * příloh je potřeba). Vrátí null, když je větší než limit nebo nejde stáhnout.
     */
    public function download(string $url, int $maxBytes): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! str_starts_with($url, 'https://') || $host === '') {
            return null;
        }

        $request = Http::timeout(120);

        if ($host === 'clickup.com' || str_ends_with($host, '.clickup.com') || str_ends_with($host, '.clickup-attachments.com')) {
            $request = $request->withHeaders(['Authorization' => (string) config('services.clickup.token')]);
        }

        try {
            $response = $request->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || strlen($response->body()) > $maxBytes) {
            return null;
        }

        return $response->body();
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

    /** Docs jsou jen v API v3. */
    private function v3(): string
    {
        return rtrim((string) config('services.clickup.url'), '/').'/api/v3';
    }

    /** @param  callable(PendingRequest): Response  $send */
    private function call(callable $send): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('ClickUp není nastavený, chybí CLICKUP_API_TOKEN.');
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $send(Http::baseUrl(rtrim((string) config('services.clickup.url'), '/').'/api/v2')
                    ->withHeaders(['Authorization' => (string) config('services.clickup.token')])
                    ->acceptJson()
                    ->timeout(20));

                // Překročený limit dotazů: počkat do obnovy (X-RateLimit-Reset, unix), nejvýš minutu.
                if ($response->status() === 429 && $attempt < self::RATE_LIMIT_RETRIES) {
                    $reset = (int) $response->header('X-RateLimit-Reset');
                    Sleep::for(max(1, min(60, $reset > 0 ? $reset - time() + 1 : 30)))->seconds();

                    continue;
                }

                $data = $response->throw()->json();
            } catch (ConnectionException|RequestException $e) {
                throw new RuntimeException('ClickUp: '.$e->getMessage(), previous: $e);
            }

            break;
        }

        return is_array($data) ? $data : [];
    }
}
