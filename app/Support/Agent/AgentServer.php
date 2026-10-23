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
 * Projekty a konverzace s Claudem čte a otevírá přes API na 127.0.0.1 se
 * sdíleným tajemstvím. Běžící konverzace je okno tmuxu. Prohlížeč tajemství
 * nikdy nedostane, jen vstupenku na jedno okno platnou minutu ({@see terminalUrl()}).
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
     * Projekty a jejich běžící okna (pro přehled: kolik konverzací běží a kdy naposledy).
     *
     * @return list<array{slug: string, typ: string, zdroj: string, vlakna: list<array{id: string, session: ?string, autor: ?string, aktivita: ?int, claude: bool}>}>
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

    /**
     * Konverzace projektu: běžící (mají okno) nahoře, pak uložené od nejnovější.
     *
     * @return list<array{session: ?string, nazev: string, autor: ?string, aktualizace: ?int, okno: ?string, trvala: bool, claude: bool, nova: bool}>
     */
    public function conversations(string $slug): array
    {
        return $this->call(fn (PendingRequest $http) => $http->get($this->projectPath($slug).'/konverzace'));
    }

    /**
     * Otevře konverzaci a vrátí ID okna (@3). Bez `$session` založí novou,
     * se `$session` naváže na uloženou (běží-li už, vrátí její okno).
     * `$replace` je prázdná nová konverzace, ze které se navazuje: zavře se.
     * U webu na serveru před novým oknem proběhne záloha.
     */
    public function openConversation(string $slug, string $author, ?string $session = null, ?string $replace = null): string
    {
        return $this->call(fn (PendingRequest $http) => $http->timeout(150)->post($this->projectPath($slug).'/konverzace', [
            'autor' => $author,
            'session' => $session,
            'nahradit' => $replace,
        ]))['okno'];
    }

    /**
     * Úloha na pozadí (z ClickUpu): agent ji odpracuje bez terminálu a výsledek
     * pošle zpět na /nastroje/api/agent/ulohy/{id}. Služba je řadí, v jednom
     * projektu běží nejvýš jedna. Se `session` naváže na předchozí konverzaci.
     *
     * @param  array{id: int, zadani: string, session: ?string, autor: string}  $task
     */
    public function runTask(string $slug, array $task): void
    {
        $this->call(fn (PendingRequest $http) => $http->post($this->projectPath($slug).'/ulohy', $task));
    }

    /** Uspí běžící konverzaci: proces skončí, konverzace zůstane uložená a jde probudit. */
    public function sleepWindow(string $slug, string $window): void
    {
        $this->call(fn (PendingRequest $http) => $http->delete($this->projectPath($slug).'/okna/'.rawurlencode($window)));
    }

    /** Trvalá konverzace se sama neuspí, zůstává dostupná i v aplikaci Claude. */
    public function pinWindow(string $slug, string $window, bool $pinned): void
    {
        $this->call(fn (PendingRequest $http) => $http->post($this->projectPath($slug).'/okna/'.rawurlencode($window).'/trvala', ['trvala' => $pinned]));
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

    /** Adresa WebSocketu s podepsanou vstupenkou na jedno okno. */
    public function terminalUrl(string $slug, string $window, string $user, ?string $host = null): string
    {
        $payload = rtrim(strtr(base64_encode((string) json_encode([
            'p' => $slug,
            'v' => $window,
            'u' => $user,
            'exp' => time() + self::TICKET_TTL,
        ])), '+/', '-_'), '=');

        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, (string) $this->secret, true)), '+/', '-_'), '=');

        $base = filled($this->wsUrl)
            ? rtrim((string) $this->wsUrl, '/').'/'
            : 'wss://'.($host ?? parse_url((string) config('app.url'), PHP_URL_HOST)).'/agent-terminal/';

        return $base.'?t='.$payload.'.'.$signature;
    }

    private function projectPath(string $slug): string
    {
        return '/api/projekty/'.rawurlencode($slug);
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
