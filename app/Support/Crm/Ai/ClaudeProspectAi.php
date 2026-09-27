<?php

namespace App\Support\Crm\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaWebFetchTool20260209;
use Anthropic\Beta\Messages\BetaWebSearchTool20260209;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Models\Crm\Company;
use App\Support\Crm\Domain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Úsudek Clauda nad proklepnutou firmou.
 *
 * Claude tu nic neměří. Dostane výsledky WebScout a text úvodní stránky
 * a řekne, co firma prodává, jestli sedí na Taveo a čím ji oslovit.
 * Čísla v auditu dál pocházejí jen z měření. Proto má prompt výslovný
 * zákaz vymýšlet údaje, které v podkladu nejsou.
 *
 * Každé volání má serverový fallback: když model požadavek odmítne,
 * API ho samo zopakuje na doporučeném náhradním modelu.
 */
class ClaudeProspectAi implements ProspectAi
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private const TAVEO = <<<'TXT'
        TAVEO jsou dva lidé z Hradce Králové: Pavel Včeliš (marketing, výkonnostní reklama na Meta) a Tomáš Kebza (weby a e-shopy na Shoptetu, WooCommerce, Shopify, Upgates, WordPressu a Laravelu). Nejsou agentura.
        Hledají jen e-shopy: menší a střední, s rozběhnutým webem a marketingem, kterým chybí společné priority a kapacita na změny. Firmy se službami bez e-shopu a agentury nehledají. Nabízejí konzultaci (2 400 Kč/h), jednorázovou spolupráci (1 200 Kč/h) a pravidelnou péči od 9 600 Kč měsíčně.
        Nehodí se: malé živnosti bez rozpočtu na marketing, weby na stavebnicích, kde nejde nic upravit, velké firmy s vlastním týmem, firmy bez zjevné poptávky po produktu.
        TXT;

    private ?string $lastError = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $workspaceId = null,
    ) {}

    public function enabled(): bool
    {
        return true;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function ping(): ?string
    {
        $data = $this->json('Odpověz slovem OK.', [
            'type' => 'object',
            'properties' => ['answer' => ['type' => 'string']],
            'required' => ['answer'],
            'additionalProperties' => false,
        ], effort: 'low');

        return $data === null ? ($this->lastError ?? 'Claude nevrátil odpověď.') : null;
    }

    public function judge(Company $company, array $scout): ?array
    {
        $prompt = self::TAVEO."\n\n"
            ."Posuď, jestli se firma hodí jako klient. Podklad je měření veřejné části webu a text úvodní stránky.\n"
            ."Pravidla: vycházej jen z podkladu. Nevymýšlej čísla, tržby ani návštěvnost. Když něco z podkladu nejde poznat, napiš to.\n\n"
            ."Firma v CRM: {$company->name}, segment {$company->segment->getLabel()}".($company->city ? ", {$company->city}" : '')."\n"
            .'Skóre z měření: '.($scout['base_score'] ?? '?')." / 100\n"
            .'Důvody skóre: '.implode('; ', $scout['reasons'] ?? [])."\n\n"
            .'Měření: '.json_encode($this->measurementsForPrompt($scout), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n"
            .'Nálezy: '.json_encode(array_map(fn ($f) => $f['title'].': '.$f['detail'], $scout['findings'] ?? []), JSON_UNESCAPED_UNICODE)."\n\n"
            ."Text úvodní stránky:\n".($scout['measurements']['text_excerpt'] ?? '');

        $data = $this->json($prompt, [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string', 'description' => 'Jedna věta: co firma prodává a komu. Česky.'],
                'adjustment' => ['type' => 'integer', 'description' => 'Posun skóre z měření, -20 až 20. Kladně zavedený podnik s rozpočtem, záporně malá živnost nebo firma mimo cílovku.'],
                'note' => ['type' => 'string', 'description' => 'Jedna až dvě věty pro obchodníka: proč se firma hodí nebo nehodí. Česky.'],
                'hook' => ['type' => 'string', 'description' => 'Jedna věta, kterou jde firmu oslovit. Konkrétní postřeh z nálezů, vykat, bez slibů výsledku. Česky, bez pomlčky „—“.'],
            ],
            'required' => ['summary', 'adjustment', 'note', 'hook'],
            'additionalProperties' => false,
        ], effort: 'medium');

        if ($data === null) {
            return null;
        }

        return [
            'summary' => (string) $data['summary'],
            'adjustment' => max(-20, min(20, (int) $data['adjustment'])),
            'note' => (string) $data['note'],
            'hook' => (string) $data['hook'],
        ];
    }

    public function auditSummary(Company $company, array $scout): ?string
    {
        $prompt = "Napiš úvodní odstavec auditu webu pro majitele firmy {$company->name}. Audit připravili Pavel a Tom z TAVEO.\n"
            ."Pravidla:\n"
            ."- 3 až 4 věty, česky, vykat, v první osobě množného čísla.\n"
            ."- Jen fakta z nálezů níž. Žádná čísla, která tam nejsou. Neslibuj růst tržeb ani pozic.\n"
            ."- Začni tím, co je v pořádku, pak dvě nebo tři nejzávažnější věci.\n"
            ."- Žádná pomlčka „—“, žádné fráze typu „komplexní řešení“, „posuneme na další úroveň“, „v dnešní digitální době“.\n"
            ."- Krátké věty. Konkrétně.\n\n"
            .'Co je v pořádku: '.implode('; ', array_column($scout['passed'] ?? [], 'title'))."\n"
            .'Nálezy: '.json_encode(array_map(fn ($f) => "[{$f['severity']}] {$f['title']}: {$f['detail']}", $scout['findings'] ?? []), JSON_UNESCAPED_UNICODE);

        $data = $this->json($prompt, [
            'type' => 'object',
            'properties' => ['paragraph' => ['type' => 'string']],
            'required' => ['paragraph'],
            'additionalProperties' => false,
        ], effort: 'medium');

        $paragraph = trim((string) ($data['paragraph'] ?? ''));

        // Pravidlo o pomlčce hlídáme i tady. Model ho občas poruší a text
        // jde klientovi.
        return $paragraph !== '' ? str_replace(' — ', ', ', $paragraph) : null;
    }

    /** Cena za milion tokenů (vstup, výstup) v USD. Neznámý model počítá jako Opus. */
    private const PRICES = [
        'claude-opus-5' => [5.0, 25.0],
        'claude-opus-5-5' => [4.0, 20.0],
        'claude-sonnet-5' => [2.0, 10.0],
        'claude-haiku-4-5' => [1.0, 5.0],
    ];

    public function deepAudit(Company $company, array $scout): ?array
    {
        $this->lastError = null;
        $domain = (string) $company->domain;
        $system = [['type' => 'text', 'text' => DeepAuditPrompt::system(), 'cacheControl' => ['type' => 'ephemeral']]];
        $messages = [['role' => 'user', 'content' => DeepAuditPrompt::user($company, $scout)]];
        $tools = [BetaWebFetchTool20260209::with(
            allowedDomains: array_values(array_filter([$domain, $domain !== '' ? 'www.'.$domain : null])),
            maxContentTokens: 12000,
            maxUses: 12,
        )];

        $cost = 0.0;
        $pages = 0;
        $text = '';

        try {
            // Server si stránky stahuje sám. Po deseti krocích se zastaví
            // (pause_turn) a pokračuje, když mu vrátíme dosavadní odpověď.
            for ($round = 0; $round < 4; $round++) {
                $response = $this->client()->beta->messages->create(
                    model: $this->model,
                    maxTokens: 32000,
                    system: $system,
                    messages: $messages,
                    tools: $tools,
                    outputConfig: ['effort' => 'high'],
                    fallbacks: 'default',
                    betas: [self::FALLBACK_BETA],
                );

                $cost += $this->costOf($response);

                foreach ($response->content as $block) {
                    if ($block->type === 'server_tool_use' && $block->name === 'web_fetch') {
                        $pages++;
                    }
                    if ($block->type === 'text') {
                        $text .= $block->text;
                    }
                }

                if ($response->stopReason !== 'pause_turn') {
                    break;
                }

                $messages = [$messages[0], ['role' => 'assistant', 'content' => $response->content]];
            }
        } catch (APIException $e) {
            $this->lastError = Str::limit($e->getMessage(), 300);
            Log::warning('Claude: podrobný audit selhal', ['company' => $company->getKey(), 'error' => $e->getMessage()]);

            return null;
        }

        if (in_array($response->stopReason, ['refusal', 'max_tokens'], true)) {
            $this->lastError = 'Odpověď bez výsledku: '.$response->stopReason;

            return null;
        }

        $parsed = DeepAuditPrompt::parse($text);

        if ($parsed === null) {
            $this->lastError = 'Claude nevrátil audit v očekávaném tvaru.';

            return null;
        }

        return $parsed + ['cost_usd' => round($cost, 2), 'pages' => $pages];
    }

    /** Přibližná cena jedné odpovědi podle spotřeby tokenů. */
    private function costOf(BetaMessage $response): float
    {
        [$in, $out] = self::PRICES[$this->model] ?? self::PRICES['claude-opus-5'];
        $u = $response->usage;

        $tokensIn = ($u->inputTokens ?? 0)
            + ($u->cacheCreationInputTokens ?? 0) * 1.25
            + ($u->cacheReadInputTokens ?? 0) * 0.1;

        return ($tokensIn * $in + ($u->outputTokens ?? 0) * $out) / 1_000_000;
    }

    public function discover(string $brief, int $count, array $knownDomains): array
    {
        $prompt = self::TAVEO."\n\n"
            ."Najdi {$count} českých firem, které stojí za oslovení. Zadání: {$brief}\n"
            ."Hledej přes webové vyhledávání. U každé firmy ověř, že má vlastní funkční web.\n"
            .'Vynech tyto domény, ty už máme: '.implode(', ', array_slice($knownDomains, 0, 300))."\n\n"
            .'Na konec odpovědi dej JSON v bloku ```json se seznamem objektů {"name", "website", "city", "reason"}. '
            .'Pole city může být null, reason je jedna věta česky, proč firma sedí.';

        try {
            $response = $this->client()->beta->messages->create(
                model: $this->model,
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => $prompt]],
                tools: [BetaWebSearchTool20260209::with(maxUses: 8)],
                outputConfig: ['effort' => 'high'],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
            );
        } catch (APIException $e) {
            $this->lastError = Str::limit($e->getMessage(), 300);
            Log::warning('Claude: vyhledání kandidátů selhalo', ['error' => $e->getMessage()]);

            return [];
        }

        $text = $this->text($response);

        if ($text === null || ! preg_match('/```json\s*(.*?)```/s', $text, $m)) {
            return [];
        }

        return collect(json_decode($m[1], true) ?: [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['website'] ?? null) && filled($row['name'] ?? null))
            ->map(fn (array $row): array => [
                'name' => Str::limit((string) $row['name'], 250, ''),
                'website' => (string) $row['website'],
                'city' => filled($row['city'] ?? null) ? (string) $row['city'] : null,
                'reason' => (string) ($row['reason'] ?? ''),
            ])
            ->reject(fn (array $row): bool => in_array(Domain::normalize($row['website']), $knownDomains, true))
            ->values()
            ->all();
    }

    /**
     * Dotaz se strukturovanou odpovědí. Null při chybě nebo odmítnutí,
     * volající pak pokračuje bez úsudku.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    private function json(string $prompt, array $schema, string $effort): ?array
    {
        $this->lastError = null;

        try {
            $response = $this->client()->beta->messages->create(
                model: $this->model,
                maxTokens: 4000,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
            );
        } catch (APIException $e) {
            $this->lastError = Str::limit($e->getMessage(), 300);
            Log::warning('Claude: dotaz selhal', ['error' => $e->getMessage()]);

            return null;
        }

        $text = $this->text($response);
        $data = $text !== null ? json_decode($text, true) : null;

        return is_array($data) ? $data : null;
    }

    /** Text poslední odpovědi. Odmítnutí nebo useknutá odpověď vrací null. */
    private function text(BetaMessage $response): ?string
    {
        if (in_array($response->stopReason, ['refusal', 'max_tokens'], true)) {
            $this->lastError = 'Odpověď bez výsledku: '.$response->stopReason;
            Log::info('Claude: odpověď bez výsledku', ['stop_reason' => $response->stopReason]);

            return null;
        }

        $text = '';

        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return $text !== '' ? $text : null;
    }

    /**
     * Měření bez dlouhého textu stránky a bez polí, která Claude
     * k úsudku nepotřebuje.
     *
     * @param  array<string, mixed>  $scout
     * @return array<string, mixed>
     */
    private function measurementsForPrompt(array $scout): array
    {
        return collect($scout['measurements'] ?? [])
            ->except(['text_excerpt', 'emails', 'phones', 'input_url', 'measured_at', 'internal_links'])
            ->all();
    }

    /**
     * Klíč, který nepatří žádnému workspace, musí workspace uvést
     * v hlavičce, jinak API vrací 400.
     */
    private function client(): Client
    {
        return new Client(apiKey: $this->apiKey, requestOptions: array_filter([
            'timeout' => 300,
            'extraHeaders' => filled($this->workspaceId) ? ['anthropic-workspace-id' => $this->workspaceId] : null,
        ]));
    }
}
