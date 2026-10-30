<?php

namespace App\Support\Vault;

use App\Enums\VaultType;
use App\Models\VaultEntry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dotazy agenta na API Ecomailu (api2.ecomailapp.cz) s klíčem z trezoru.
 * Agent pošle cestu (/lists, /campaigns, /pipelines), klíč přidá web do
 * hlavičky `key`. Jen čtení (GET): mazat kontakty, měnit seznamy ani
 * posílat kampaně agent nemůže.
 */
class EcomailApi
{
    private const BASE = 'https://api2.ecomailapp.cz';

    /** Je tohle klíč k Ecomailu? Pozná se podle adresy u přístupu (ecomail.cz, ecomailapp.cz a jejich poddomény). */
    public static function supports(VaultEntry $entry): bool
    {
        if ($entry->type !== VaultType::ApiKey) {
            return false;
        }

        $url = trim((string) $entry->url);
        $host = strtolower((string) parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST));

        return in_array($host, ['ecomail.cz', 'ecomailapp.cz'], true)
            || str_ends_with($host, '.ecomail.cz')
            || str_ends_with($host, '.ecomailapp.cz');
    }

    public function call(VaultEntry $entry, string $method, string $path, array $query = []): Response
    {
        if (! self::supports($entry) || blank($entry->secret)) {
            throw new VaultException('U přístupu chybí klíč nebo adresa Ecomailu (ecomail.cz).');
        }

        if (strtoupper($method) !== 'GET') {
            throw new VaultException('Agent smí Ecomail jen číst (GET). Měnit kontakty, seznamy ani posílat kampaně nemůže.');
        }

        if (str_contains($path, '://')) {
            throw new VaultException('Cesta k API Ecomailu musí být jako /lists, bez adresy.');
        }

        // Parametry v cestě (`/lists/1/subscribers?page=2`) platí, výslovný `dotaz` má přednost.
        parse_str((string) parse_url($path, PHP_URL_QUERY), $inPath);
        $query = [...$inPath, ...$query];
        $path = '/'.ltrim(rawurldecode(parse_url($path, PHP_URL_PATH) ?: ''), '/');

        if ($path === '/' || str_contains($path, '..')) {
            throw new VaultException('Cesta k API Ecomailu musí být jako /lists, bez .. a adresy.');
        }

        return Http::withHeaders(['key' => (string) $entry->secret])
            ->withUserAgent('TAVEO trezor')
            ->acceptJson()
            ->timeout(60)
            ->get(self::BASE.$path, $query);
    }
}
