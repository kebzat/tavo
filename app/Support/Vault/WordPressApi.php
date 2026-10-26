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
    private const WRITE_BLOCKED = ['~^/wp/v2/users~', '~application-passwords~', '~^/wp/v2/settings~'];

    public function call(VaultEntry $entry, string $method, string $path, array $query = [], array $body = []): Response
    {
        $method = strtoupper($method);

        if ($entry->type !== VaultType::WordPress || blank($entry->app_password) || blank($entry->url)) {
            throw new VaultException('U přístupu chybí adresa webu nebo aplikační heslo WordPressu.');
        }

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new VaultException('Metoda '.$method.' není povolená.');
        }

        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: '', '/');

        if ($method !== 'GET') {
            if (! $entry->agentAllows('write')) {
                throw new VaultException('U tohoto přístupu smí agent WordPress jen číst. Změnit to jde v trezoru („Co smí agent“).');
            }

            foreach (self::WRITE_BLOCKED as $pattern) {
                if (preg_match($pattern, $path)) {
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
