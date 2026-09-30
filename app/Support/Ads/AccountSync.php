<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdDailyStat;
use App\Models\Ads\AdSyncRun;
use App\Models\Ads\AnalyticsDailyStat;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\AdsPlatform;
use App\Support\Ads\Platforms\AnalyticsPlatform;
use App\Support\Ads\Platforms\DailyStat;
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

    public function sync(AdAccount $account, Period $period): AdSyncRun
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
            return $this->run($account, $period);
        } finally {
            $lock->release();
        }
    }

    private function run(AdAccount $account, Period $period): AdSyncRun
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
            ])->save();

            $run->update(['status' => 'ok', 'rows' => $rows, 'finished_at' => now()]);
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
     * Kampaně z odpovědi, založené nebo přejmenované.
     *
     * @param  Collection<int, DailyStat>  $stats
     * @return array<string, int> external_id => id
     */
    private function campaigns(AdAccount $account, Collection $stats): array
    {
        $ids = [];

        // keyBy nechá poslední výskyt, takže platí nejnovější název kampaně.
        foreach ($stats->keyBy('campaignId') as $stat) {
            $campaign = AdCampaign::query()->updateOrCreate(
                ['ad_account_id' => $account->getKey(), 'external_id' => $stat->campaignId],
                ['name' => $stat->campaignName, 'objective' => $stat->objective],
            );

            $ids[$stat->campaignId] = $campaign->getKey();
        }

        return $ids;
    }
}
