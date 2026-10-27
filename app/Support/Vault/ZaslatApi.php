<?php

namespace App\Support\Vault;

use App\Enums\VaultType;
use App\Models\VaultEntry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dotazy agenta na API Zaslat.cz (docs.zaslat.cz/api/v1) s klíčem z trezoru.
 * Agent pošle metodu a cestu (/shipments/list?tracking), klíč přidá web do
 * hlavičky X-Apikey. Jen čtení: GET a POST na výslovně čtecí cesty
 * (sledování, detail, štítek). Založit nebo zrušit zásilku agent nemůže,
 * stálo by to klienta peníze.
 */
class ZaslatApi
{
    // POST, které nic nemění: čtou stav, detail nebo štítek zásilek podle čísel.
    private const READ_POST = ['/shipments/tracking', '/shipments/detail', '/shipments/label'];

    /** Je tohle klíč k Zaslatu? Pozná se podle adresy u přístupu (zaslat.cz, www.zaslat.cz, test.zaslat.cz). */
    public static function supports(VaultEntry $entry): bool
    {
        return $entry->type === VaultType::ApiKey && self::base($entry) !== null;
    }

    public function call(VaultEntry $entry, string $method, string $path, array $query = [], array $body = []): Response
    {
        $method = strtoupper($method);
        $base = self::base($entry);

        if ($entry->type !== VaultType::ApiKey || $base === null || blank($entry->secret)) {
            throw new VaultException('U přístupu chybí klíč nebo adresa Zaslatu (zaslat.cz).');
        }

        // Parametry v cestě (`/shipments/list?tracking&status=ACTIVE`) platí, výslovný `dotaz` má přednost.
        parse_str((string) parse_url($path, PHP_URL_QUERY), $inPath);
        $query = [...$inPath, ...$query];
        $path = '/'.ltrim(rawurldecode(parse_url($path, PHP_URL_PATH) ?: ''), '/');
        $path = preg_replace('~^/api/v1(?=/)~i', '', $path);

        if (str_contains($path, '..') || str_contains($path, '://')) {
            throw new VaultException('Cesta k API Zaslatu musí být jako /shipments/list, bez .. a adresy.');
        }

        $read = $method === 'GET' || ($method === 'POST' && in_array(strtolower(rtrim($path, '/')), self::READ_POST, true));

        if (! $read) {
            throw new VaultException('Agent smí Zaslat jen číst: GET a POST na '.implode(', ', self::READ_POST).'. Zakládat ani rušit zásilky nemůže.');
        }

        $request = Http::withHeaders(['X-Apikey' => (string) $entry->secret])
            ->withUserAgent('TAVEO trezor')
            ->acceptJson()
            ->timeout(60);

        return $method === 'GET'
            ? $request->get($base.$path, $query)
            : $request->send('POST', $base.$path, ['query' => $query, 'json' => $body]);
    }

    private static function base(VaultEntry $entry): ?string
    {
        $url = trim((string) $entry->url);
        $host = strtolower((string) parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST));

        return match (true) {
            $host === 'test.zaslat.cz' => 'https://test.zaslat.cz/api/v1',
            $host === 'zaslat.cz', str_ends_with($host, '.zaslat.cz') => 'https://www.zaslat.cz/api/v1',
            default => null,
        };
    }
}
