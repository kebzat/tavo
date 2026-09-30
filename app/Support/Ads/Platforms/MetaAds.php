<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Period;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Meta Marketing API (Graph API), jen čtení.
 *
 * Přistupujeme tokenem systémového uživatele z Business Manageru Taveo.
 * Klient nám účet nasdílí jako partnerovi a od té chvíle ho token vidí
 * v /me/adaccounts. Nic dalšího od klienta nepotřebujeme.
 *
 * Čísla bereme na úrovni kampaní po dnech a s atribucí nastavenou v účtu,
 * aby seděla s tím, co klient vidí ve Správci reklam.
 */
class MetaAds implements AdsPlatform
{
    private const ACCOUNT_FIELDS = 'account_id,name,currency,timezone_name,account_status,business{name}';

    private const INSIGHT_FIELDS = 'campaign_id,campaign_name,objective,spend,impressions,reach,clicks,inline_link_clicks,actions,action_values';

    /**
     * Meta hlásí tutéž konverzi pod několika typy akcí (pixel, web, aplikace,
     * souhrn „omni“). Bereme první nalezený v tomhle pořadí. Omni je součet
     * napříč zdroji a odpovídá sloupci „Nákupy“ ve Správci reklam.
     */
    private const ACTIONS = [
        'purchases' => ['omni_purchase', 'purchase', 'offsite_conversion.fb_pixel_purchase', 'onsite_web_purchase'],
        'leads' => ['lead', 'onsite_conversion.lead_grouped', 'offsite_conversion.fb_pixel_lead', 'onsite_web_lead'],
        'add_to_cart' => ['omni_add_to_cart', 'add_to_cart', 'offsite_conversion.fb_pixel_add_to_cart'],
        'checkouts' => ['omni_initiated_checkout', 'initiate_checkout', 'offsite_conversion.fb_pixel_initiate_checkout'],
    ];

    /** Číselné stavy účtu z Graph API. */
    private const STATUSES = [
        1 => 'active',
        2 => 'disabled',
        3 => 'unsettled',
        7 => 'pending_risk_review',
        8 => 'pending_settlement',
        9 => 'grace_period',
        100 => 'pending_closure',
        101 => 'closed',
        201 => 'active',
        202 => 'closed',
    ];

    /** Delší období se stahuje po kusech, jedna odpověď by byla zbytečně velká. */
    private const CHUNK_DAYS = 31;

    public function platform(): AdPlatform
    {
        return AdPlatform::Meta;
    }

    public function isConfigured(): bool
    {
        return filled(config('ads.meta.token'));
    }

    public function accounts(): Collection
    {
        return $this->paginate('me/adaccounts', ['fields' => self::ACCOUNT_FIELDS, 'limit' => 200])
            ->map(fn (array $row): AccountInfo => $this->accountInfo($row))
            ->sortBy(fn (AccountInfo $account): string => mb_strtolower($account->name))
            ->values();
    }

    public function account(string $externalId): AccountInfo
    {
        return $this->accountInfo($this->request('act_'.$externalId, ['fields' => self::ACCOUNT_FIELDS]));
    }

    public function dailyStats(AdAccount $account, Period $period): Collection
    {
        $stats = collect();

        for ($from = $period->from; $from->lte($period->to); $from = $from->addDays(self::CHUNK_DAYS)) {
            $to = $from->addDays(self::CHUNK_DAYS - 1)->min($period->to);

            $rows = $this->paginate('act_'.$account->external_id.'/insights', [
                'level' => 'campaign',
                'time_increment' => 1,
                'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()]),
                'fields' => self::INSIGHT_FIELDS,
                'use_account_attribution_setting' => 'true',
                'limit' => 500,
            ]);

            $stats = $stats->concat($rows->map(fn (array $row): DailyStat => $this->dailyStat($row)));
        }

        return $stats->values();
    }

    /** @param  array<string, mixed>  $row */
    public function dailyStat(array $row): DailyStat
    {
        $actions = $this->actionMap($row['actions'] ?? []);
        $values = $this->actionMap($row['action_values'] ?? []);

        return new DailyStat(
            campaignId: (string) $row['campaign_id'],
            campaignName: (string) ($row['campaign_name'] ?? 'Kampaň '.$row['campaign_id']),
            objective: $row['objective'] ?? null,
            date: (string) $row['date_start'],
            spend: (float) ($row['spend'] ?? 0),
            impressions: (int) ($row['impressions'] ?? 0),
            reach: (int) ($row['reach'] ?? 0),
            clicks: (int) ($row['clicks'] ?? 0),
            linkClicks: (int) ($row['inline_link_clicks'] ?? 0),
            purchases: $this->pick($actions, self::ACTIONS['purchases']),
            purchaseValue: $this->pick($values, self::ACTIONS['purchases']),
            leads: $this->pick($actions, self::ACTIONS['leads']),
            addToCart: $this->pick($actions, self::ACTIONS['add_to_cart']),
            checkouts: $this->pick($actions, self::ACTIONS['checkouts']),
            raw: ['actions' => $actions, 'action_values' => $values],
        );
    }

    /**
     * @param  list<array{action_type: string, value: string}>  $actions
     * @return array<string, float>
     */
    private function actionMap(array $actions): array
    {
        $map = [];

        foreach ($actions as $action) {
            if (isset($action['action_type'])) {
                $map[$action['action_type']] = (float) ($action['value'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, float>  $map
     * @param  list<string>  $types
     */
    private function pick(array $map, array $types): float
    {
        foreach ($types as $type) {
            if (array_key_exists($type, $map)) {
                return $map[$type];
            }
        }

        return 0.0;
    }

    /** @param  array<string, mixed>  $row */
    private function accountInfo(array $row): AccountInfo
    {
        return new AccountInfo(
            externalId: (string) ($row['account_id'] ?? preg_replace('/^act_/', '', (string) ($row['id'] ?? ''))),
            name: (string) ($row['name'] ?? 'Účet bez názvu'),
            currency: (string) ($row['currency'] ?? 'CZK'),
            timezone: $row['timezone_name'] ?? null,
            status: self::STATUSES[(int) ($row['account_status'] ?? 1)] ?? 'unknown',
            business: $row['business']['name'] ?? null,
        );
    }

    /**
     * Všechny stránky výsledku. Meta stránkuje odkazem `paging.next`,
     * který už v sobě nese token i parametry.
     *
     * @param  array<string, mixed>  $params
     * @return Collection<int, array<string, mixed>>
     */
    private function paginate(string $path, array $params): Collection
    {
        $body = $this->request($path, $params);
        $rows = collect($body['data'] ?? []);

        while ($next = $body['paging']['next'] ?? null) {
            $body = $this->send(fn (PendingRequest $http): Response => $http->get($next));
            $rows = $rows->concat($body['data'] ?? []);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $path, array $params): array
    {
        $url = rtrim(config('ads.meta.base_url'), '/').'/'.config('ads.meta.api_version').'/'.$path;

        return $this->send(fn (PendingRequest $http): Response => $http->get($url, $params + $this->auth()));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        if (! $this->isConfigured()) {
            throw new AdsApiException('Chybí META_SYSTEM_USER_TOKEN v .env.', authFailed: true);
        }

        try {
            $response = $call(Http::acceptJson()->timeout(60)->retry(2, 1000, throw: false));
        } catch (ConnectionException $e) {
            throw new AdsApiException('Meta neodpovídá: '.$e->getMessage());
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $code = (int) ($error['code'] ?? 0);
        $message = (string) ($error['error_user_msg'] ?? $error['message'] ?? 'HTTP '.$response->status());

        throw new AdsApiException(
            match (true) {
                $code === 190 => 'Token Meta je neplatný nebo mu chybí oprávnění ('.$message.')',
                in_array($code, [4, 17, 32, 613, 80004], true) => 'Meta omezila počet dotazů, zkusíme to příště ('.$message.')',
                in_array($code, [10, 200], true) || $response->status() === 403 => 'K účtu nemáme přístup. Nasdílel ho klient Business Manageru Taveo? ('.$message.')',
                default => 'Meta vrátila chybu: '.$message,
            },
            authFailed: $code === 190,
        );
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        $token = (string) config('ads.meta.token');
        $secret = config('ads.meta.app_secret');

        return array_filter([
            'access_token' => $token,
            'appsecret_proof' => filled($secret) ? hash_hmac('sha256', $token, $secret) : null,
        ]);
    }
}
