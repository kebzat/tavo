<?php

namespace App\Support\Automations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Do kdy leží zásilka Zásilkovny na výdejním místě. Zaslat konec úložní
 * doby neposílá, veřejné sledování Zásilkovny (tracking.packeta.com) ano:
 * v HTML je payload Nuxtu s klíčem `storedUntil`. Nic jiného ze stránky
 * nečteme ani neukládáme.
 */
class PacketaTracking
{
    /** Kam smí engine pro sledování sáhnout. Adresu posílá Zaslat, ať nás nikam jinam neodvede. */
    private const HOSTS = ['packeta.com', 'zasilkovna.cz', 'packeta.cz', 'zasilkovna.sk', 'packeta.sk'];

    public static function allowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with(strtolower($url), 'https://')
            && collect(self::HOSTS)->contains(fn (string $allowed): bool => $host === $allowed || str_ends_with($host, '.'.$allowed));
    }

    /** Datum konce úložní doby (RRRR-MM-DD), nebo null, když ho stránka neřekne. */
    public function storedUntil(string $url): ?string
    {
        if (! self::allowed($url)) {
            return null;
        }

        $key = 'packeta-stored-until:'.sha1($url);

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(20)
                ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36')
                ->withHeaders(['Accept-Language' => 'cs'])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $date = $response->successful() ? self::parse($response->body()) : null;

        // Zásilkovna může úložní dobu prodloužit, proto se pamatuje jen pár hodin.
        if ($date !== null) {
            Cache::put($key, $date, now()->addHours(3));
        }

        return $date;
    }

    /**
     * Najde `storedUntil` ve stránce sledování. Nejdřív v `__NUXT_DATA__`
     * (pole, kde objekty odkazují na hodnoty indexy), pak jako obyčejný JSON
     * a nakonec první datum hned za textem „připravena k vyzvednutí“.
     */
    public static function parse(string $html): ?string
    {
        if (preg_match('~<script[^>]*id="__NUXT_DATA__"[^>]*>(.*?)</script>~s', $html, $match)) {
            $date = self::fromNuxtData(json_decode(html_entity_decode($match[1]), true));

            if ($date !== null) {
                return $date;
            }
        }

        if (preg_match('~"storedUntil"\s*:\s*"(\d{4}-\d{2}-\d{2})~', $html, $match) && self::valid($match[1])) {
            return $match[1];
        }

        if (preg_match('~připravena k vyzvednutí".{0,200}?"(\d{4}-\d{2}-\d{2})"~su', $html, $match) && self::valid($match[1])) {
            return $match[1];
        }

        return null;
    }

    private static function fromNuxtData(mixed $data): ?string
    {
        if (! is_array($data) || ! array_is_list($data)) {
            return null;
        }

        foreach ($data as $item) {
            if (! is_array($item) || array_is_list($item) || ! array_key_exists('storedUntil', $item)) {
                continue;
            }

            $value = $item['storedUntil'];
            $value = is_int($value) ? ($data[$value] ?? null) : $value;

            if (is_string($value) && preg_match('~^(\d{4}-\d{2}-\d{2})~', $value, $match) && self::valid($match[1])) {
                return $match[1];
            }
        }

        return null;
    }

    private static function valid(string $date): bool
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }
}
