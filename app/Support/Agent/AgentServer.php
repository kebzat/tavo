<?php

namespace App\Support\Agent;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Služba agenta v kontejneru taveo-agent (infra/agent/terminal/server.js).
 *
 * Projekty a vlákna (okna tmuxu) čte a mění přes API na 127.0.0.1 se sdíleným
 * tajemstvím. Prohlížeč tajemství nikdy nedostane, jen vstupenku na jedno
 * vlákno platnou minutu ({@see terminalUrl()}).
 */
class AgentServer
{
    /** Jak dlouho platí vstupenka do terminálu. Stačí na připojení, ne na sdílení odkazu. */
    private const TICKET_TTL = 60;

    public function __construct(
        private readonly ?string $url,
        private readonly ?string $secret,
        private readonly ?string $wsUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.agent.url'),
            config('services.agent.secret'),
            config('services.agent.ws_url'),
        );
    }

    public function configured(): bool
    {
        return filled($this->url) && filled($this->secret);
    }

    /**
     * @return list<array{slug: string, typ: string, zdroj: string, vlakna: list<array{id: string, poradi: int, nazev: string, autor: ?string, aktivita: ?int, claude: bool}>}>
     */
    public function projects(): array
    {
        return $this->call(fn (PendingRequest $http) => $http->get('/api/projekty'));
    }

    /** @return array{slug: string, typ: string, zdroj: string, vlakna: list<array<string, mixed>>}|null */
    public function project(string $slug): ?array
    {
        return collect($this->projects())->firstWhere('slug', $slug);
    }

    /** Založí vlákno a vrátí jeho ID (@3). U webu na serveru před tím proběhne záloha. */
    public function startThread(string $slug, string $name, string $author, bool $continue = false): string
    {
        return $this->call(fn (PendingRequest $http) => $http->timeout(150)->post('/api/projekty/'.rawurlencode($slug).'/vlakna', [
            'nazev' => $name,
            'autor' => $author,
            'navazat' => $continue,
        ]))['id'];
    }

    public function renameThread(string $slug, string $thread, string $name): void
    {
        $this->call(fn (PendingRequest $http) => $http->post($this->threadPath($slug, $thread).'/prejmenovat', ['nazev' => $name]));
    }

    public function closeThread(string $slug, string $thread): void
    {
        $this->call(fn (PendingRequest $http) => $http->delete($this->threadPath($slug, $thread)));
    }

    /**
     * Nový projekt (repozitář z GitHubu, nebo bez kódu). U repozitáře napoprvé
     * neuspěje a ve výstupu vrátí klíče, které je potřeba přidat na GitHub.
     *
     * @return array{ok: bool, vystup: string}
     */
    public function addProject(string $slug, string $type, ?string $source = null): array
    {
        $response = $this->request()->timeout(200)->post('/api/projekty', [
            'projekt' => $slug,
            'typ' => $type,
            'zdroj' => $source,
        ]);

        if ($response->status() !== 200 && $response->status() !== 422) {
            throw AgentUnavailable::from($response->toException());
        }

        return [
            'ok' => (bool) $response->json('ok'),
            'vystup' => (string) $response->json('vystup'),
        ];
    }

    /** Adresa WebSocketu s podepsanou vstupenkou na jedno vlákno. */
    public function terminalUrl(string $slug, string $thread, string $user, ?string $host = null): string
    {
        $payload = rtrim(strtr(base64_encode((string) json_encode([
            'p' => $slug,
            'v' => $thread,
            'u' => $user,
            'exp' => time() + self::TICKET_TTL,
        ])), '+/', '-_'), '=');

        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, (string) $this->secret, true)), '+/', '-_'), '=');

        $base = filled($this->wsUrl)
            ? rtrim((string) $this->wsUrl, '/').'/'
            : 'wss://'.($host ?? parse_url((string) config('app.url'), PHP_URL_HOST)).'/agent-terminal/';

        return $base.'?t='.$payload.'.'.$signature;
    }

    private function threadPath(string $slug, string $thread): string
    {
        return '/api/projekty/'.rawurlencode($slug).'/vlakna/'.rawurlencode($thread);
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new AgentUnavailable('Agent není nastavený. Chybí AGENT_SECRET v .env, viz docs/AGENT.md.');
        }

        return Http::baseUrl((string) $this->url)
            ->withToken((string) $this->secret)
            ->acceptJson()
            ->timeout(10);
    }

    /** @param  callable(PendingRequest): Response  $send */
    private function call(callable $send): array
    {
        try {
            $data = $send($this->request())->throw()->json();
        } catch (ConnectionException|RequestException $e) {
            throw AgentUnavailable::from($e);
        }

        return is_array($data) ? $data : throw new AgentUnavailable('Agent vrátil nečekanou odpověď.');
    }
}
