<?php

namespace App\Http\Middleware;

use App\Enums\ApiAbility;
use App\Models\ApiToken;
use App\Models\ApiTokenCall;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ověření agenta u strojových endpointů CRM.
 *
 * Každý agent má vlastní token (Nástroje → Přístupy agentů) a smí jen to,
 * co má v oprávněních. Routa říká, které potřebuje:
 * `VerifyCrmToken::class.':audits:import'`.
 *
 * Starý společný CRM_IMPORT_TOKEN z .env zatím platí na všechno, ať se
 * agenti nerozbijí dřív, než dostanou vlastní token. Po přechodu se má
 * z .env smazat. Viz docs/SECURITY.md.
 *
 * Token se posílá jen hlavičkou. V adrese by skončil v logu serveru
 * a v historii prohlížeče.
 */
class VerifyCrmToken
{
    /** Kolik neplatných pokusů z jedné adresy za minutu snese, než ji odmítne. */
    private const MAX_FAILURES_PER_MINUTE = 10;

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $ability = ApiAbility::from($ability);
        $limiterKey = 'crm-token-failures:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_FAILURES_PER_MINUTE)) {
            abort(429, 'Příliš mnoho neplatných pokusů.');
        }

        $provided = (string) ($request->header('X-Crm-Token') ?? $request->bearerToken() ?? '');
        $token = ApiToken::findActive($provided);

        if ($token === null && ! $this->isLegacyToken($provided)) {
            RateLimiter::hit($limiterKey);
            Log::warning('Neplatný token na strojovém rozhraní CRM.', ['ip' => $request->ip(), 'path' => $request->path()]);

            abort(401, 'Neplatný token.');
        }

        if ($token !== null && ! $token->allows($ability)) {
            $this->record($request, $token, Response::HTTP_FORBIDDEN);

            abort(403, 'Token na tohle nemá oprávnění ('.$ability->value.').');
        }

        $response = $next($request);

        $this->record($request, $token, $response->getStatusCode());

        return $response;
    }

    private function isLegacyToken(string $provided): bool
    {
        $legacy = (string) config('crm.import_token');

        return $legacy !== '' && $provided !== '' && hash_equals($legacy, $provided);
    }

    private function record(Request $request, ?ApiToken $token, int $status): void
    {
        ApiTokenCall::create([
            'api_token_id' => $token?->getKey(),
            'endpoint' => (string) ($request->route()?->getName() ?? $request->path()),
            'ip' => $request->ip(),
            'status' => $status,
            'called_at' => now(),
        ]);

        $token?->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
    }
}
