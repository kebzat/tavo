<?php

namespace App\Support\Automations;

use App\Models\Automation;
use App\Support\Vault\LoginBroker;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Odkazy z účtu klienta rovnou do detailu objednávky v administraci Upgates.
 * Detail má adresu podle interního ID (`/orders/edit-order/default/4441/`),
 * které v exportu objednávek není a od čísla objednávky (04430) se liší
 * nepravidelně. Web se proto přihlásí přístupem z trezoru (jako nahrávání
 * importu) a ID najde filtrem seznamu objednávek
 * `/orders/?ordersGrid-filter[order_number]=…`. Filtr si Upgates pamatuje jen
 * v relaci (ověřeno 28. 10. 2026), klientovi seznam objednávek nezmění.
 *
 * ID se nemění, ukládají se natrvalo do `automatizace/{id}/odkazy.json`
 * (jen čísla a ID). Když hledání selže, číslo zůstane bez odkazu a zkusí se
 * při dalším běhu. Viz docs/AUTOMATIZACE.md.
 */
class UpgatesOrderLinks
{
    /** Kolik nových objednávek dohledat najednou (každá je jeden dotaz do administrace). */
    public const MAX_LOOKUPS = 40;

    public function __construct(private LoginBroker $broker) {}

    /** @param  iterable<string>  $numbers  čísla objednávek, ke kterým chceme odkaz */
    public function resolve(Automation $automation, iterable $numbers): void
    {
        $known = self::read($automation);
        $missing = collect($numbers)->map(fn ($number): string => trim((string) $number))
            ->filter(fn (string $number): bool => preg_match('~^[A-Za-z0-9-]{1,20}$~', $number) && ! isset($known['ids'][$number]))
            ->unique()
            ->take(self::MAX_LOOKUPS)
            ->values();
        $entry = $automation->upgatesEntry();

        if ($missing->isEmpty() || $entry === null) {
            return;
        }

        $origin = $this->origin($entry->loginUrl());

        try {
            $jar = new CookieJar;
            foreach ($this->broker->login($entry) as $cookie) {
                $jar->setCookie(new SetCookie(['Name' => $cookie['name'], 'Value' => $cookie['value'], 'Domain' => $cookie['domain'], 'Path' => $cookie['path'] ?: '/', 'Secure' => $cookie['secure']]));
            }

            foreach ($missing as $number) {
                $html = Http::withOptions(['cookies' => $jar])->timeout(30)
                    ->get($origin.'/orders/', ['ordersGrid-filter' => ['order_number' => $number]])
                    ->body();
                preg_match_all('~/orders/edit-order/default/(\d+)/~', $html, $matches);
                $ids = array_values(array_unique($matches[1]));

                // Jen jednoznačný výsledek: filtr hledá i část čísla.
                if (count($ids) === 1 && str_contains($html, $number)) {
                    $known['ids'][$number] = (int) $ids[0];
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        $known['base'] = $origin;
        Storage::disk('local')->put(self::path($automation), json_encode($known));
        $entry->record('automatizace', 'upgates_order_links', 'Odkazy na objednávky ('.$missing->count().')');
    }

    /** Adresa detailu objednávky v administraci, nebo null, když ID zatím neznáme. */
    public static function url(Automation $automation, string $number): ?string
    {
        $data = self::read($automation);
        $id = $data['ids'][trim($number)] ?? null;

        return $id && filled($data['base'] ?? null) ? $data['base'].'/orders/edit-order/default/'.$id.'/' : null;
    }

    /** @return array{base?: string, ids: array<string, int>} */
    private static function read(Automation $automation): array
    {
        $path = self::path($automation);
        $data = Storage::disk('local')->exists($path) ? json_decode((string) Storage::disk('local')->get($path), true) : null;

        return is_array($data) ? $data + ['ids' => []] : ['ids' => []];
    }

    private static function path(Automation $automation): string
    {
        return 'automatizace/'.$automation->getKey().'/odkazy.json';
    }

    private function origin(?string $url): string
    {
        $parts = parse_url((string) $url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }
}
