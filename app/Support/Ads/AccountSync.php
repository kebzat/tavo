<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdSyncRun;
use App\Models\Ads\AdTopAd;
use App\Models\Ads\AnalyticsDailyStat;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\AdsPlatform;
use App\Support\Ads\Platforms\AnalyticsPlatform;
use App\Support\Ads\Platforms\DailyStat;
use App\Support\Ads\Platforms\HasAdInsights;
use App\Support\Ads\Platforms\HasCampaignStatus;
use App\Support\Ads\Platforms\MetaAds;
use App\Support\Ads\Platforms\Platforms;
use App\Support\Ads\Platforms\TrafficStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stáhne čísla jednoho účtu za období a přepíše jimi, co v databázi bylo.
 * Reklamní účty jdou do ad_daily_stats, GA4 do analytics_daily_stats.
 *
 * Přepisuje se celé období najednou: řádky v rozsahu smaže a vloží znovu.
 * Kampaň, které Meta zpětně ubrala konverzi nebo kterou za den nevrátila
 * vůbec, tak nezůstane se starým číslem. Opakované spuštění nic nezdvojí.
 */
class AccountSync
{
    public function __construct(private readonly Platforms $platforms) {}

    /**
     * @param  bool  $withReach  stáhnout i přesný dosah za přednastavená období
     *                           (jeden dotaz navíc). Ranní běh ano,
     *                           doplňování historie ne.
     */
    public function sync(AdAccount $account, Period $period, bool $withReach = false): AdSyncRun
    {
        // Jeden účet se nikdy nestahuje dvakrát současně (ranní běh a tlačítko,
        // dvojklik). Druhý pokus se jen zapíše jako přeskočený.
        $lock = Cache::lock('ads.sync.account.'.$account->getKey(), 600);

        if (! $lock->get()) {
            return $account->syncRuns()->create([
                'date_from' => $period->from,
                'date_to' => $period->to,
                'status' => 'skipped',
                'error' => 'Účet se právě stahuje, druhé stažení přeskočeno.',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        try {
            return $this->run($account, $period, $withReach);
        } finally {
            $lock->release();
        }
    }

    private function run(AdAccount $account, Period $period, bool $withReach): AdSyncRun
    {
        $run = $account->syncRuns()->create([
            'date_from' => $period->from,
            'date_to' => $period->to,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $platform = $this->platforms->for($account->platform);

        try {
            $info = $platform->account($account->external_id);

            $rows = match (true) {
                $platform instanceof AdsPlatform => DB::transaction(fn (): int => $this->store($account, $period, $platform->dailyStats($account, $period))),
                $platform instanceof AnalyticsPlatform => DB::transaction(fn (): int => $this->storeTraffic($account, $period, $platform->dailyTraffic($account, $period))),
            };

            $account->forceFill([
                'name' => $info->name,
                'currency' => $info->currency ?: $account->currency,
                'timezone' => $info->timezone,
                'status' => $info->status,
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ]);
            $account->markDataThrough($period);
            $account->save();

            $run->update(['status' => 'ok', 'rows' => $rows, 'finished_at' => now()]);

            if ($withReach && $platform instanceof AdsPlatform && $account->platform->hasPeriodReach()) {
                $this->syncReach($account, $platform);
                $this->syncGroupReach($account, $platform);
            }

            if ($withReach && $platform instanceof MetaAds) {
                $this->syncIssues($account, $platform);
            }

            if ($withReach && $platform instanceof HasCampaignStatus) {
                $this->syncCampaignStatuses($account, $platform);
            }

            if ($withReach && $platform instanceof HasAdInsights) {
                $this->syncTopAds($account, $platform);
            }
        } catch (AdsApiException $e) {
            $account->forceFill(['last_sync_error' => $e->getMessage()])->save();
            $run->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);

            Log::warning('Reklamy: synchronizace účtu selhala.', [
                'account' => $account->external_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $run;
    }

    /**
     * Přesný dosah za přednastavená období, jeden dotaz na účet. Když se
     * nepovede, denní čísla platí dál a zůstane včerejší dosah. Kvůli dosahu
     * se synchronizace jako chybná neoznačuje.
     */
    private function syncReach(AdAccount $account, AdsPlatform $platform): void
    {
        $periods = PeriodReach::ranges();

        try {
            $stats = $platform->periodReach($account, $periods);
        } catch (AdsApiException $e) {
            Log::warning('Reklamy: dosah za období se nestáhl.', [
                'account' => $account->external_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        DB::transaction(fn () => PeriodReach::store($account, $periods, $stats));
    }

    /**
     * Přesný dosah skupin kampaní, jeden dotaz na skupinu, která má v účtu
     * kampaně. Když se nepovede, dlaždice dosahu skupiny ukáže pomlčku.
     */
    private function syncGroupReach(AdAccount $account, AdsPlatform $platform): void
    {
        $periods = PeriodReach::ranges();
        $groups = AdCampaignGroup::query()
            ->where('client_id', $account->client_id)
            ->whereHas('campaigns', fn ($query) => $query->where('ad_account_id', $account->getKey()))
            ->get();

        foreach ($groups as $group) {
            $externalIds = GroupReach::externalIds($group, $account);

            try {
                $stats = $platform->periodReach($account, $periods, $externalIds);
            } catch (AdsApiException $e) {
                Log::warning('Reklamy: dosah skupiny se nestáhl.', [
                    'account' => $account->external_id,
                    'group' => $group->name,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            DB::transaction(fn () => GroupReach::store($group, $account, $periods, $externalIds, $stats));
        }
    }

    /** Součty reklam za 30 dní (nejlepší reklamy), jeden dotaz na účet. Když se nepovede, zůstanou včerejší. */
    private function syncTopAds(AdAccount $account, HasAdInsights $platform): void
    {
        $period = Period::preset('30d');

        try {
            $ads = $platform->adInsights($account, $period);
        } catch (AdsApiException $e) {
            Log::warning('Reklamy: čísla reklam se nestáhla.', [
                'account' => $account->external_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $now = now();

        DB::transaction(function () use ($account, $ads, $period, $now): void {
            AdTopAd::query()->where('ad_account_id', $account->getKey())->delete();
            AdTopAd::query()->insert(array_map(fn (array $ad): array => $ad + [
                'ad_account_id' => $account->getKey(),
                'date_from' => $period->from->toDateString(),
                'date_to' => $period->to->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ], $ads));
        });
    }

    /** Stav kampaní (běží, vypnutá), jeden dotaz na účet. Když se nepovede, zůstane včerejší. */
    private function syncCampaignStatuses(AdAccount $account, HasCampaignStatus $platform): void
    {
        try {
            $statuses = $platform->campaignStatuses($account);
        } catch (AdsApiException $e) {
            Log::warning('Reklamy: stav kampaní se nestáhl.', [
                'account' => $account->external_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($account->campaigns()->get() as $campaign) {
            $status = $statuses[$campaign->external_id] ?? null;

            if ($status !== null && $status !== $campaign->status) {
                $campaign->update(['status' => $status]);
            }
        }
    }

    /**
     * Zamítnuté reklamy pro upozornění, jeden dotaz na účet Mety a den.
     * Když se nepovede, čísla platí dál a upozornění vychází ze včerejška.
     */
    private function syncIssues(AdAccount $account, MetaAds $platform): void
    {
        try {
            $account->forceFill(['issues' => $platform->adIssues($account), 'issues_checked_at' => now()])->save();
        } catch (AdsApiException $e) {
            Log::warning('Reklamy: zamítnuté reklamy se nestáhly.', [
                'account' => $account->external_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param  Collection<int, DailyStat>  $stats */
    private function store(AdAccount $account, Period $period, Collection $stats): int
    {
        $account->stats()->whereBetween('date', $period->bounds())->delete();

        $campaigns = $this->campaigns($account, $stats);
        $now = now();

        $rows = $stats
            // Den bez útraty i zobrazení je jen šum v tabulce.
            ->filter(fn (DailyStat $stat): bool => $stat->spend > 0 || $stat->impressions > 0)
            ->map(fn (DailyStat $stat): array => [
                'ad_account_id' => $account->getKey(),
                'ad_campaign_id' => $campaigns[$stat->campaignId],
                ...$stat->columns(),
                'raw' => json_encode($stat->raw, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        foreach ($rows->chunk(500) as $chunk) {
            AdDailyStat::query()->insert($chunk->values()->all());
        }

        return $rows->count();
    }

    /** @param  Collection<int, TrafficStat>  $stats */
    private function storeTraffic(AdAccount $account, Period $period, Collection $stats): int
    {
        $account->traffic()->whereBetween('date', $period->bounds())->delete();
        $now = now();

        $rows = $stats->map(fn (TrafficStat $stat): array => [
            'ad_account_id' => $account->getKey(),
            ...$stat->columns(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($rows->chunk(500) as $chunk) {
            AnalyticsDailyStat::query()->insert($chunk->values()->all());
        }

        return $rows->count();
    }

    /**
     * Kampaně z odpovědi, založené nebo přejmenované. Nezařazená kampaň,
     * jejíž název odpovídá pravidlu některé skupiny klienta, se zařadí sama.
     * Ostatní čekají na zařazení (přehled je ukáže).
     *
     * @param  Collection<int, DailyStat>  $stats
     * @return array<string, int> external_id => id
     */
    private function campaigns(AdAccount $account, Collection $stats): array
    {
        $ids = [];
        $groups = AdCampaignGroup::query()->where('client_id', $account->client_id)->ordered()->get()
            ->filter(fn (AdCampaignGroup $group): bool => $group->ruleWords() !== []);

        // keyBy nechá poslední výskyt, takže platí nejnovější název kampaně.
        foreach ($stats->keyBy('campaignId') as $stat) {
            $campaign = AdCampaign::query()->updateOrCreate(
                ['ad_account_id' => $account->getKey(), 'external_id' => $stat->campaignId],
                ['name' => $stat->campaignName, 'objective' => $stat->objective],
            );

            if ($campaign->ad_campaign_group_id === null && $campaign->grouped_by === null) {
                $match = $groups->first(fn (AdCampaignGroup $group): bool => $group->matchesName($campaign->name));

                if ($match) {
                    $campaign->update(['ad_campaign_group_id' => $match->getKey(), 'grouped_by' => 'rule']);
                }
            }

            $ids[$stat->campaignId] = $campaign->getKey();
        }

        return $ids;
    }
}
