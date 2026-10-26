<?php

namespace App\Support\Agent;

use Illuminate\Support\Facades\Cache;

/**
 * Kolik z předplatného Claude je vyčerpáno: 5h relace a týdenní limit,
 * stejná čísla jako /usage v Claude Code. Ukazuje se v hlavičce nástrojů.
 *
 * Data jdou ze služby agenta ({@see AgentServer::usage()}) a drží se
 * dvě minuty, ať každá otevřená záložka nevolá Anthropic sama. Když služba
 * neodpovídá, drží se chyba minutu.
 */
final class ClaudeUsage
{
    private const CACHE_KEY = 'agent.predplatne';

    /** @param  list<UsageLimit>  $limits */
    public function __construct(
        public readonly ?string $plan,
        public readonly array $limits,
        public readonly ?string $error = null,
    ) {}

    public static function current(AgentServer $agent): self
    {
        $data = Cache::get(self::CACHE_KEY);

        if ($data === null) {
            try {
                $data = $agent->usage();
                Cache::put(self::CACHE_KEY, $data, now()->addMinutes(2));
            } catch (AgentUnavailable $e) {
                $data = ['chyba' => $e->getMessage()];
                Cache::put(self::CACHE_KEY, $data, now()->addMinute());
            }
        }

        return isset($data['chyba']) ? new self(null, [], $data['chyba']) : self::fromApi($data);
    }

    /**
     * Odpověď služby: `limity` je novější seznam (relace, týden, týden
     * pro jeden model), `five_hour` a `seven_day` starší tvar pro případ,
     * že seznam chybí.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $limits = is_array($data['limity'] ?? null)
            ? collect($data['limity'])->map(fn (array $limit): ?UsageLimit => UsageLimit::fromLimit($limit))
            : collect([
                UsageLimit::fromWindow('session', $data['five_hour'] ?? null),
                UsageLimit::fromWindow('weekly_all', $data['seven_day'] ?? null),
            ]);

        return new self($data['plan'] ?? null, $limits->filter()->values()->all());
    }

    /**
     * Relace a týden: dvě čísla, která jsou vidět pořád. Ostatní jsou v rozbalení.
     *
     * @return list<UsageLimit>
     */
    public function headline(): array
    {
        return array_values(array_filter($this->limits, fn (UsageLimit $limit): bool => $limit->kind !== 'weekly_scoped'));
    }

    public function planLabel(): ?string
    {
        return match ($this->plan) {
            null => null,
            'max' => 'Claude Max',
            'pro' => 'Claude Pro',
            'team' => 'Claude Team',
            'enterprise' => 'Claude Enterprise',
            default => 'Claude '.ucfirst($this->plan),
        };
    }
}
