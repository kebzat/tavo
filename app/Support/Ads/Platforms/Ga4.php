<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Google Analytics 4 přes Data API a Admin API, jen čtení.
 *
 * Přistupujeme servisním účtem Taveo. Klient přidá jeho e-mail do své
 * GA4 property jako čtenáře a property se objeví v nabídce. Token se
 * získá podepsaným JWT, knihovnu od Googlu na to nepotřebujeme.
 */
class Ga4 implements AnalyticsPlatform
{
    use GoogleApi;

    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public function platform(): AdPlatform
    {
        return AdPlatform::Ga4;
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['client_email'] ?? null) && filled($credentials['private_key'] ?? null);
    }

    /** E-mail servisního účtu. Ten klient přidá do GA4 jako čtenáře. */
    public function serviceAccountEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    public function accounts(): Collection
    {
        $accounts = collect();
        $pageToken = null;
        $pages = 0;

        do {
            if (++$pages > 20) {
                throw new AdsApiException('Google Analytics vrací nečekaně mnoho stránek, stahování přerušeno.');
            }

            $body = $this->google(fn (PendingRequest $http): Response => $http->get(
                'https://analyticsadmin.googleapis.com/v1beta/accountSummaries',
                array_filter(['pageSize' => 200, 'pageToken' => $pageToken]),
            ));

            foreach ($body['accountSummaries'] ?? [] as $summary) {
                foreach ($summary['propertySummaries'] ?? [] as $property) {
                    $accounts->push(new AccountInfo(
                        externalId: str_replace('properties/', '', (string) $property['property']),
                        name: (string) ($property['displayName'] ?? $property['property']),
                        currency: '',
                        timezone: null,
                        status: 'active',
                        business: $summary['displayName'] ?? null,
                    ));
                }
            }

            $pageToken = $body['nextPageToken'] ?? null;
        } while ($pageToken);

        return $accounts->sortBy(fn (AccountInfo $account): string => mb_strtolower($account->name))->values();
    }

    public function account(string $externalId): AccountInfo
    {
        $property = $this->google(fn (PendingRequest $http): Response => $http->get(
            'https://analyticsadmin.googleapis.com/v1beta/properties/'.$externalId,
        ));

        return new AccountInfo(
            externalId: $externalId,
            name: (string) ($property['displayName'] ?? 'Property '.$externalId),
            currency: (string) ($property['currencyCode'] ?? 'CZK'),
            timezone: $property['timeZone'] ?? null,
            status: filled($property['deleteTime'] ?? null) ? 'closed' : 'active',
        );
    }

    public function dailyTraffic(AdAccount $account, Period $period): Collection
    {
        $body = $this->google(fn (PendingRequest $http): Response => $http->post(
            'https://analyticsdata.googleapis.com/v1beta/properties/'.$account->external_id.':runReport',
            [
                'dateRanges' => [['startDate' => $period->from->toDateString(), 'endDate' => $period->to->toDateString()]],
                'dimensions' => [['name' => 'date'], ['name' => 'sessionDefaultChannelGroup']],
                'metrics' => [
                    ['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'engagedSessions'],
                    ['name' => 'keyEvents'], ['name' => 'ecommercePurchases'], ['name' => 'purchaseRevenue'],
                ],
                'limit' => 100000,
            ],
        ));

        return collect($body['rows'] ?? [])->map(fn (array $row): TrafficStat => $this->trafficStat($row))->values();
    }

    /** @param  array<string, mixed>  $row */
    public function trafficStat(array $row): TrafficStat
    {
        $dimensions = array_column($row['dimensionValues'] ?? [], 'value');
        $metrics = array_column($row['metricValues'] ?? [], 'value');

        return new TrafficStat(
            date: Carbon::createFromFormat('Ymd', (string) $dimensions[0])->toDateString(),
            channel: (string) ($dimensions[1] ?? 'Unassigned'),
            sessions: (int) ($metrics[0] ?? 0),
            users: (int) ($metrics[1] ?? 0),
            engagedSessions: (int) ($metrics[2] ?? 0),
            keyEvents: (float) ($metrics[3] ?? 0),
            purchases: (float) ($metrics[4] ?? 0),
            revenue: (float) ($metrics[5] ?? 0),
        );
    }

    /**
     * Klíč servisního účtu. V .env buď celý JSON, nebo cesta k souboru.
     *
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $value = trim((string) config('ads.ga4.credentials'));

        if ($value === '') {
            return [];
        }

        if (! str_starts_with($value, '{')) {
            $path = str_starts_with($value, '/') ? $value : base_path($value);
            $value = is_readable($path) ? (string) file_get_contents($path) : '';
        }

        return json_decode($value, true) ?: [];
    }

    protected function serviceName(): string
    {
        return 'Google Analytics';
    }

    protected function guardKey(): string
    {
        return 'ga4';
    }

    protected function tokenCacheKey(): string
    {
        return 'ads.ga4.access_token';
    }

    protected function fetchToken(): array
    {
        $credentials = $this->credentials();
        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();

        $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $credentials['client_email'] ?? '',
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        if (! openssl_sign($unsigned, $signature, (string) ($credentials['private_key'] ?? ''), OPENSSL_ALGO_SHA256)) {
            throw new AdsApiException('Google Analytics: klíč servisního účtu je neplatný.', authFailed: true);
        }

        return $this->tokenRequest($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
        ]);
    }
}
