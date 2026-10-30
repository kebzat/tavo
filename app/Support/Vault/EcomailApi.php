<?php

namespace App\Support\Vault;

use App\Enums\VaultType;
use App\Models\VaultEntry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dotazy agenta na API Ecomailu (api2.ecomailapp.cz) s klíčem z trezoru.
 * Agent pošle metodu a cestu (/lists, /campaigns, /pipelines), klíč přidá web
 * do hlavičky `key`. Číst smí s právem „read“, s „write“ měnit kontakty, seznamy,
 * koncepty kampaní a zakládat šablony. Nic, co pošle e-mail (kampaň, automatizace,
 * potvrzení odběru), ani mazání z databáze neprojde nikdy.
 */
class EcomailApi
{
    private const BASE = 'https://api2.ecomailapp.cz';

    // Čtecí části API (docs.ecomail.cz). Jiné cesty neprojdou, ani GET.
    private const READ_PREFIXES = ['lists', 'subscribers', 'campaigns', 'pipelines', 'template', 'domains', 'transactional/stats', 'tracker/transaction', 'account/settings/webhook'];

    // Změny s právem „write“: metoda a cesta (# = číslo).
    private const WRITE = [
        'POST /lists' => 'nový seznam',
        'PUT /lists/#' => 'úprava seznamu',
        'POST /lists/#/subscribe' => 'přidat kontakt',
        'POST /lists/#/subscribe-bulk' => 'přidat kontakty',
        'PUT /lists/#/update-subscriber' => 'upravit kontakt',
        'DELETE /lists/#/unsubscribe' => 'odhlásit kontakt ze seznamu',
        'POST /campaigns' => 'koncept kampaně',
        'PUT /campaigns/#' => 'úprava konceptu kampaně',
        'POST /template' => 'nová šablona',
    ];

    // Pole kampaně, která agent smí poslat. Plánování (scheduled_at) a stav ne, to by kampaň odeslalo.
    private const CAMPAIGN_FIELDS = ['title', 'from_name', 'from_email', 'reply_to', 'subject', 'preheader', 'html_text', 'recepient_lists', 'template_id', 'ga'];

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

    public function call(VaultEntry $entry, string $method, string $path, array $query = [], array $body = []): Response
    {
        $method = strtoupper($method);

        if (! self::supports($entry) || blank($entry->secret)) {
            throw new VaultException('U přístupu chybí klíč nebo adresa Ecomailu (ecomail.cz).');
        }

        if (str_contains($path, '://')) {
            throw new VaultException('Cesta k API Ecomailu musí být jako /lists, bez adresy.');
        }

        // Parametry v cestě (`/lists/1/subscribers?page=2`) platí, výslovný `dotaz` má přednost.
        parse_str((string) parse_url($path, PHP_URL_QUERY), $inPath);
        $query = [...$inPath, ...$query];
        $path = '/'.trim(preg_replace('~/+~', '/', rawurldecode(parse_url($path, PHP_URL_PATH) ?: '')), '/');

        if ($path === '/' || str_contains($path, '..')) {
            throw new VaultException('Cesta k API Ecomailu musí být jako /lists, bez .. a adresy.');
        }

        $lower = strtolower($path);

        if ($method === 'GET') {
            // Ecomail odesílá kampaň (/campaign/{id}/send) a obnovuje feedy (/feeds/{id}/refresh) přes GET.
            $readable = collect(self::READ_PREFIXES)->contains(fn (string $prefix): bool => $lower === '/'.$prefix || str_starts_with($lower, '/'.$prefix.'/'));

            if (! $readable || preg_match('~/(send|refresh|delete|trigger)$~', $lower)) {
                throw new VaultException('Tohle v Ecomailu agent nesmí. Číst jde: '.implode(', ', array_map(fn ($prefix) => '/'.$prefix, self::READ_PREFIXES)).'.');
            }
        } else {
            if (! $entry->agentAllows('write')) {
                throw new VaultException('U tohohle klíče smí agent Ecomail jen číst. Měnit může, když člověk v Nástrojích → Trezor nastaví „Co smí agent“ na „Číst i měnit“.');
            }

            $route = $method.' '.preg_replace('~/\d+(?=/|$)~', '/#', $lower);

            if (! array_key_exists($route, self::WRITE)) {
                throw new VaultException('Tuhle změnu v Ecomailu agent nesmí. Jde jen: '.implode(', ', array_map(
                    fn (string $route, string $label): string => $route.' ('.$label.')', array_keys(self::WRITE), self::WRITE,
                )).'. Odeslat kampaň, spustit automatizaci ani smazat kontakt z databáze nejde, to udělá člověk v Ecomailu.');
            }

            $body = $this->guard($route, $body);
        }

        $request = Http::withHeaders(['key' => (string) $entry->secret])
            ->withUserAgent('TAVEO trezor')
            ->acceptJson()
            ->timeout(60);

        return $method === 'GET'
            ? $request->get(self::BASE.$path, $query)
            : $request->send($method, self::BASE.$path, ['query' => $query, 'json' => $body]);
    }

    /** Pojistky v těle změny: nic se neodešle a odhlášený kontakt se znovu nepřihlásí. */
    private function guard(string $route, array $body): array
    {
        if (str_starts_with($route, 'POST /lists/#/subscribe') || $route === 'PUT /lists/#/update-subscriber') {
            $subscribers = $route === 'POST /lists/#/subscribe-bulk'
                ? (array) ($body['subscriber_data'] ?? [])
                : [(array) ($body['subscriber_data'] ?? [])];

            foreach ($subscribers as $subscriber) {
                // Stav 1 (přihlášen) by vrátil do seznamu i toho, kdo se odhlásil. Odhlásit (2) smí.
                if (is_array($subscriber) && array_key_exists('status', $subscriber) && (int) $subscriber['status'] !== 2) {
                    throw new VaultException('Stav kontaktu agent nastavit nesmí (jen 2 = odhlášen). Nový kontakt bude přihlášený, odhlášený se znovu nepřihlásí.');
                }
            }

            if ($route !== 'PUT /lists/#/update-subscriber') {
                // Bez potvrzovacího e-mailu (skip_confirmation), uvítacích automatizací a upozornění, odhlášené nechat být.
                $body = [...$body, 'trigger_autoresponders' => false, 'trigger_notification' => false, 'resubscribe' => false, 'skip_confirmation' => true];
            }
        }

        if (str_starts_with($route, 'POST /campaigns') || str_starts_with($route, 'PUT /campaigns')) {
            $extra = array_diff(array_keys($body), self::CAMPAIGN_FIELDS);

            if ($extra !== []) {
                throw new VaultException('U kampaně agent smí poslat jen '.implode(', ', self::CAMPAIGN_FIELDS).'. Navíc: '.implode(', ', $extra).'. Naplánovat ani odeslat kampaň nejde.');
            }
        }

        return $body;
    }
}
