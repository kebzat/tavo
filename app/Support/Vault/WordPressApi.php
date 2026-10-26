<?php

namespace App\Support\Vault;

use App\Enums\VaultType;
use App\Models\VaultEntry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dotazy agenta na REST API WordPressu s aplikačním heslem z trezoru.
 * Agent pošle metodu a cestu (/wp/v2/plugins), heslo přidá web. Měnit smí
 * jen s „číst i měnit“ a nikdy uživatele, aplikační hesla ani nastavení
 * webu: jinak by si mohl založit vlastní přístup, který trezor nezruší.
 */
class WordPressApi
{
    // WordPress porovnává cesty bez ohledu na velikost písmen, proto `i`.
    private const WRITE_BLOCKED = ['~^/wp/v2/users~i', '~application-passwords~i', '~^/wp/v2/settings~i'];

    // `_method` změní metodu (GET by zapisoval) a `rest_route` cestu, obojí by obešlo pravidla výš.
    private const QUERY_BLOCKED = ['_method', 'rest_route'];

    public function call(VaultEntry $entry, string $method, string $path, array $query = [], array $body = []): Response
    {
        $method = strtoupper($method);

        if ($entry->type !== VaultType::WordPress || blank($entry->app_password) || blank($entry->url)) {
            throw new VaultException('U přístupu chybí adresa webu nebo aplikační heslo WordPressu.');
        }

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new VaultException('Metoda '.$method.' není povolená.');
        }

        // Parametry v cestě (`/wp/v2/posts?per_page=100&page=2`) platí, výslovný `dotaz` má přednost.
        parse_str((string) parse_url($path, PHP_URL_QUERY), $inPath);
        $query = [...$inPath, ...$query];
        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: '', '/');

        if (array_intersect(array_map('strtolower', array_keys($query)), self::QUERY_BLOCKED) !== []) {
            throw new VaultException('Parametry _method a rest_route agent přes trezor posílat nesmí, metoda a cesta se zadávají zvlášť.');
        }

        if ($method !== 'GET') {
            if (! $entry->agentAllows('write')) {
                throw new VaultException('U tohoto přístupu smí agent WordPress jen číst. Změnit to jde v trezoru („Co smí agent“).');
            }

            foreach (self::WRITE_BLOCKED as $pattern) {
                if (preg_match($pattern, rawurldecode($path))) {
                    throw new VaultException('Uživatele, aplikační hesla a nastavení webu agent přes trezor měnit nesmí.');
                }
            }
        }

        $request = Http::withBasicAuth((string) $entry->username, str_replace(' ', '', (string) $entry->app_password))
            ->withUserAgent('TAVEO trezor')
            ->acceptJson()
            ->timeout(60);

        $url = rtrim((string) $entry->url, '/').'/wp-json'.$path;

        return $method === 'GET'
            ? $request->get($url, $query)
            : $request->send($method, $url, ['query' => $query, 'json' => $body]);
    }
}
