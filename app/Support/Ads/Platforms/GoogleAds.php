<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * Google Ads API (REST), jen čtení.
 *
 * Klientské účty jsou propojené pod manažerským účtem (MCC) Taveo a čteme
 * je OAuth tokenem jednoho uživatele Taveo. Od září 2026 se úroveň přístupu
 * k API řídí Google Cloud projektem, developer token posíláme, jen když
 * je vyplněný.
 *
 * Google má jednu metriku „konverze“. Podle cíle klienta ji ukládáme jako
 * nákupy, nebo jako poptávky, aby se sčítala s Metou do stejného sloupce.
 */
class GoogleAds implements AdsPlatform
{
    use GoogleApi;

    private const STATUSES = [
        'ENABLED' => 'active',
        'SUSPENDED' => 'disabled',
        'CANCELED' => 'closed',
        'CLOSED' => 'closed',
    ];

    public function platform(): AdPlatform
    {
        return AdPlatform::GoogleAds;
    }

    public function isConfigured(): bool
    {
        return filled(config('ads.google_ads.client_id'))
            && filled(config('ads.google_ads.client_secret'))
            && filled(config('ads.google_ads.refresh_token'))
            && filled(config('ads.google_ads.login_customer_id'));
    }

    public function accounts(): Collection
    {
        $rows = $this->search(
            $this->managerId(),
            'SELECT customer_client.id, customer_client.descriptive_name, customer_client.currency_code, customer_client.time_zone, customer_client.status '
            .'FROM customer_client WHERE customer_client.manager = false',
        );

        return $rows
            ->map(fn (array $row): AccountInfo => new AccountInfo(
                externalId: (string) $row['customerClient']['id'],
                name: (string) ($row['customerClient']['descriptiveName'] ?? 'Účet '.$row['customerClient']['id']),
                currency: (string) ($row['customerClient']['currencyCode'] ?? 'CZK'),
                timezone: $row['customerClient']['timeZone'] ?? null,
                status: self::STATUSES[$row['customerClient']['status'] ?? 'ENABLED'] ?? 'unknown',
            ))
            ->sortBy(fn (AccountInfo $account): string => mb_strtolower($account->name))
            ->values();
    }

    public function account(string $externalId): AccountInfo
    {
        $row = $this->search(
            $this->digits($externalId),
            'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, customer.status FROM customer LIMIT 1',
        )->first()['customer'] ?? [];

        return new AccountInfo(
            externalId: $this->digits($externalId),
            name: (string) ($row['descriptiveName'] ?? 'Účet '.$externalId),
            currency: (string) ($row['currencyCode'] ?? 'CZK'),
            timezone: $row['timeZone'] ?? null,
            status: self::STATUSES[$row['status'] ?? 'ENABLED'] ?? 'unknown',
        );
    }

    public function dailyStats(AdAccount $account, Period $period): Collection
    {
        $goal = $account->client?->adGoal() ?? PrimaryGoal::Purchases;

        return $this->search(
            $this->digits($account->external_id),
            'SELECT campaign.id, campaign.name, campaign.advertising_channel_type, segments.date, '
            .'metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value '
            ."FROM campaign WHERE segments.date BETWEEN '{$period->from->toDateString()}' AND '{$period->to->toDateString()}'",
        )->map(fn (array $row): DailyStat => $this->dailyStat($row, $goal))->values();
    }

    /** Google Ads dosah za období nevrací, dosah a frekvence jsou jen z Mety. */
    public function periodReach(AdAccount $account, array $periods): Collection
    {
        return collect();
    }

    /** @param  array<string, mixed>  $row */
    public function dailyStat(array $row, PrimaryGoal $goal): DailyStat
    {
        $metrics = $row['metrics'] ?? [];
        $conversions = (float) ($metrics['conversions'] ?? 0);
        $clicks = (int) ($metrics['clicks'] ?? 0);

        return new DailyStat(
            campaignId: (string) $row['campaign']['id'],
            campaignName: (string) ($row['campaign']['name'] ?? 'Kampaň '.$row['campaign']['id']),
            objective: $row['campaign']['advertisingChannelType'] ?? null,
            date: (string) $row['segments']['date'],
            spend: ((int) ($metrics['costMicros'] ?? 0)) / 1_000_000,
            impressions: (int) ($metrics['impressions'] ?? 0),
            clicks: $clicks,
            // Google nerozlišuje kliknutí a prokliky na web, u vyhledávání jsou to totéž.
            linkClicks: $clicks,
            purchases: $goal === PrimaryGoal::Leads ? 0 : $conversions,
            purchaseValue: $goal === PrimaryGoal::Leads ? 0 : (float) ($metrics['conversionsValue'] ?? 0),
            leads: $goal === PrimaryGoal::Leads ? $conversions : 0,
            raw: ['conversions' => $conversions, 'conversions_value' => (float) ($metrics['conversionsValue'] ?? 0)],
        );
    }

    /**
     * GAQL dotaz se stránkováním.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function search(string $customerId, string $query): Collection
    {
        $url = 'https://googleads.googleapis.com/'.config('ads.google_ads.api_version').'/customers/'.$customerId.'/googleAds:search';
        $rows = collect();
        $pageToken = null;
        $pages = 0;

        do {
            if (++$pages > 20) {
                throw new AdsApiException('Google Ads vrací nečekaně mnoho stránek výsledku, stahování přerušeno.');
            }

            $body = $this->google(fn (PendingRequest $http): Response => $http
                ->withHeaders(array_filter([
                    'developer-token' => config('ads.google_ads.developer_token'),
                    'login-customer-id' => $this->managerId(),
                ]))
                ->post($url, array_filter(['query' => $query, 'pageToken' => $pageToken])));

            $rows = $rows->concat($body['results'] ?? []);
            $pageToken = $body['nextPageToken'] ?? null;
        } while ($pageToken);

        return $rows;
    }

    private function managerId(): string
    {
        return $this->digits((string) config('ads.google_ads.login_customer_id'));
    }

    /** Čísla účtů se v rozhraní píšou s pomlčkami (123-456-7890), API je chce bez nich. */
    private function digits(string $id): string
    {
        return preg_replace('/\D/', '', $id) ?? $id;
    }

    protected function serviceName(): string
    {
        return 'Google Ads';
    }

    protected function guardKey(): string
    {
        return 'google_ads';
    }

    protected function tokenCacheKey(): string
    {
        return 'ads.google_ads.access_token';
    }

    protected function fetchToken(): array
    {
        return $this->tokenRequest('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => (string) config('ads.google_ads.client_id'),
            'client_secret' => (string) config('ads.google_ads.client_secret'),
            'refresh_token' => (string) config('ads.google_ads.refresh_token'),
        ]);
    }
}
