<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Marketing\LandingCheck;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Ranní kontrola cílových stránek klientů s reklamami: odpovídá stránka
 * a je v ní Meta pixel? Výsledek čte pravidlo LandingPageProblem.
 *
 * Šetrně k webům klientů: jeden dotaz na adresu za den, nejvýš
 * Client::MAX_LANDING_PAGES adres na klienta, mezi dotazy pauza,
 * žádné opakování. Stránky se jen načtou, nic se neodesílá.
 */
class MarketingLandingCheck extends Command
{
    protected $signature = 'marketing:landing-check {--client= : Jen jeden klient (slug)}';

    protected $description = 'Zkontroluje cílové stránky klientů s reklamami';

    /** Kolik HTML stačí na nalezení pixelu (bývá v <head>). */
    private const MAX_BYTES = 600_000;

    public function handle(): int
    {
        $clients = Client::query()->withAds()
            ->when($this->option('client'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($clients as $client) {
            $urls = $client->landingUrls();

            foreach ($urls as $url) {
                $check = $this->check($url);
                LandingCheck::query()->updateOrCreate(
                    ['client_id' => $client->getKey(), 'url' => $url],
                    $check + ['checked_at' => now()],
                );

                $this->line($client->name.' · '.$url.': '.($check['ok'] ? 'OK' : 'CHYBA '.($check['error'] ?? $check['status_code'])));
                usleep(500_000);
            }

            // Adresy, které z karty zmizely, nemají dál svítit.
            $client->landingChecks()->whereNotIn('url', $urls)->delete();
        }

        return self::SUCCESS;
    }

    /** @return array{status_code: ?int, ok: bool, has_meta_pixel: ?bool, response_ms: ?int, error: ?string} */
    private function check(string $url): array
    {
        $started = microtime(true);

        try {
            $response = Http::withHeaders(['User-Agent' => 'TaveoKontrola/1.0 (+https://taveo.cz; kontrola cílové stránky jednou denně)'])
                ->withOptions(['allow_redirects' => ['max' => 3], 'stream' => false])
                ->timeout(20)
                ->get($url);
        } catch (ConnectionException $e) {
            return ['status_code' => null, 'ok' => false, 'has_meta_pixel' => null, 'response_ms' => null, 'error' => 'Stránka neodpovídá: '.mb_substr($e->getMessage(), 0, 150)];
        }

        $html = mb_substr((string) $response->body(), 0, self::MAX_BYTES);

        return [
            'status_code' => $response->status(),
            'ok' => $response->successful(),
            'has_meta_pixel' => $response->successful() ? (str_contains($html, 'connect.facebook.net') || str_contains($html, 'fbq(')) : null,
            'response_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $response->successful() ? null : 'HTTP '.$response->status(),
        ];
    }
}
