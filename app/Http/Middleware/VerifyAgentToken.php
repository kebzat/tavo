<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token agenta v kontejneru taveo-agent (AGENT_API_TOKEN). Vlastní, ne
 * sdílený s CRM: agent smí číst data klientů a zapisovat jen koncepty
 * (ranní přehled, návrhy úkolů), nic dalšího. Bez nastaveného tokenu
 * endpointy neexistují.
 */
class VerifyAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.agent.api_token');

        if (strlen($expected) < 32) {
            Log::warning('Rozhraní agenta bylo voláno, ale AGENT_API_TOKEN není nastavený.');

            abort(404);
        }

        if (! hash_equals($expected, (string) $request->bearerToken())) {
            abort(401, 'Neplatný token.');
        }

        return $next($request);
    }
}
