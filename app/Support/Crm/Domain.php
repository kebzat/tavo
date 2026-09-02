<?php

namespace App\Support\Crm;

/**
 * Normalizace webové adresy na doménu.
 *
 * Firmy se do CRM dostávají z rešerše, z CSV i ručně a pokaždé v jiném tvaru:
 * „example.cz", „www.example.cz", „https://example.cz/kontakt". Duplicita se
 * pozná jedině tak, že se všechny tvary srovnají na společný tvar.
 */
class Domain
{
    /**
     * Doména bez protokolu, bez www, bez cesty a malými písmeny.
     * Vrací null pro prázdný nebo nesmyslný vstup — takovou firmu prostě
     * proti duplicitám neporovnáváme.
     */
    public static function normalize(?string $website): ?string
    {
        $value = trim((string) $website);

        if ($value === '') {
            return null;
        }

        // Bez protokolu parse_url() celý řetězec považuje za cestu, ne za host.
        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower(ltrim($host, '.'));
        $host = preg_replace('~^www\.~', '', $host);

        // Doména bez tečky není doména (typicky „localhost" nebo překlep).
        return str_contains((string) $host, '.') ? $host : null;
    }

    /** Adresa použitelná v odkazu — doplní chybějící protokol. */
    public static function toUrl(?string $website): ?string
    {
        $value = trim((string) $website);

        if ($value === '') {
            return null;
        }

        return preg_match('~^[a-z][a-z0-9+.-]*://~i', $value) ? $value : 'https://'.$value;
    }
}
