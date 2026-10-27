<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Support\Knowledge\KnowledgeBase;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Projekt, ze kterého agent volá (`X-Taveo-Projekt`, `taveo` ho posílá
 * podle složky konverzace). Projekt klienta pak čte a zapisuje jen svého
 * klienta a firemní věci: jiný slug, dokument z archivu jiného klienta,
 * jeho úkol nebo přístup vrátí 403. Projekt bez klienta (taveo, mozek,
 * google-reklamy) omezený není.
 *
 * Pojistka proti tomu, aby agent omylem smíchal data klientů, ne
 * zabezpečení: token je jeden pro všechny projekty. Viz docs/MOZEK.md.
 */
class AgentProjectScope
{
    public const HEADER = 'X-Taveo-Projekt';

    private const ATTRIBUTE = 'agent_project_client';

    /** Klient projektu, ze kterého agent volá (null = projekt bez klienta nebo bez hlavičky). */
    public static function client(Request $request): ?Client
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $project = trim((string) $request->header(self::HEADER));
        $client = $project !== '' ? Client::query()->where('agent_project', $project)->first() : null;

        if (! $client) {
            return $next($request);
        }

        $request->attributes->set(self::ATTRIBUTE, $client);
        $parameters = $request->route()?->parameters() ?? [];

        $slugs = collect([$parameters['slug'] ?? null, $request->input('klient')])
            ->filter(fn ($slug): bool => is_string($slug) && $slug !== '' && $slug !== $client->slug && $slug !== KnowledgeBase::FIRM_ARCHIVE);
        $foreign = collect($parameters)
            ->filter(fn ($value): bool => $value instanceof Model && $value->getAttribute('client_id') !== null && (int) $value->getAttribute('client_id') !== $client->getKey());

        if ($slugs->isNotEmpty() || $foreign->isNotEmpty()) {
            abort(403, "Tahle konverzace je projekt klienta {$client->name} ({$client->slug}). K datům jiného klienta odsud nejde, klienti se nemíchají. Když je to opravdu potřeba, ať to člověk udělá v projektu toho klienta.");
        }

        return $next($request);
    }
}
