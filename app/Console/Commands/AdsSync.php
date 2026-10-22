<?php

namespace App\Console\Commands;

use App\Models\Ads\AdAccount;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\AccountSync;
use App\Support\Ads\Period;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\Platforms\Platforms;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Denní stažení čísel ze všech zapnutých reklamních účtů.
 *
 * Jediné místo, které smí volat Metu, Google Ads a GA4 (ApiGuard::openForSync).
 * Všechno ostatní, web, reporty, upozornění i agent, čte z databáze.
 *
 * Bez parametrů přepíše posledních N dní (config ads.sync_days), protože
 * Meta konverze zpětně dopočítává, stáhne seznam nasdílených účtů a doplní
 * historii, o kterou se požádalo (nový účet, „Doplnit starší historii“).
 * Účet, který se dnes už stáhl, se podruhé nestahuje (jen s --force).
 */
class AdsSync extends Command
{
    protected $signature = 'ads:sync
        {--account= : Jen jeden účet (ID v naší databázi nebo číslo účtu u platformy)}
        {--days= : Kolik dní zpět přepsat, výchozí z config/ads.php}
        {--from= : Začátek období (Y-m-d), přebíjí --days}
        {--to= : Konec období (Y-m-d), výchozí včerejšek}
        {--force : Stáhnout i účty, které se dnes už stáhly}';

    protected $description = 'Stáhne denní čísla z reklamních účtů klientů (jediný přístup na platformy)';

    public function handle(AccountSync $sync, Platforms $platforms, AccountDirectory $directory): int
    {
        ApiGuard::openForSync();

        try {
            return $this->syncAll($sync, $platforms, $directory);
        } finally {
            ApiGuard::close();
        }
    }

    private function syncAll(AccountSync $sync, Platforms $platforms, AccountDirectory $directory): int
    {
        $period = $this->period();
        $daily = ! $this->option('from') && ! $this->option('account');

        // Seznam nasdílených účtů pro nabídku při propojování, jeden dotaz na platformu.
        if ($daily) {
            foreach ($platforms->configured() as $platform) {
                if ($platform->isDemo() || $directory->checkedAt($platform)?->isToday()) {
                    continue;
                }

                try {
                    $this->line($platform->shortLabel().': nasdílených účtů '.$directory->refresh($platform).'.');
                } catch (AdsApiException $e) {
                    $this->error($platform->shortLabel().': seznam účtů se nestáhl. '.$e->getMessage());
                }
            }
        }

        $accounts = AdAccount::query()
            ->active()
            ->when($this->option('account'), fn ($query, $id) => $query->where(fn ($query) => $query->whereKey($id)->orWhere('external_id', $id)))
            ->with('client')
            ->get()
            ->filter(fn (AdAccount $account): bool => $platforms->for($account->platform)->isConfigured());

        if ($accounts->isEmpty()) {
            $this->warn('Žádný účet k synchronizaci (nebo chybí přístup k platformě v .env).');

            return self::SUCCESS;
        }

        $failed = 0;
        // Dosah za přednastavená období jen při běžném denním běhu, ne při stahování historie.
        $withReach = ! $this->option('from');

        foreach ($accounts as $account) {
            $label = "{$account->client->name} · {$account->name}";

            if ($daily && ! $this->option('force') && $account->last_synced_at?->isToday()) {
                $this->line("{$label}: dnes už stažený.");

                continue;
            }

            $run = $sync->sync($account, $period, $withReach);

            if ($run->status === 'ok') {
                $this->info("{$label}: {$run->rows} řádků ({$period->label()}).");
                $this->backfill($sync, $account->refresh(), $label);
            } elseif ($run->status === 'skipped') {
                $this->line("{$label}: {$run->error}");
            } else {
                $failed++;
                $this->error("{$label}: {$run->error}");
            }
        }

        // Selhání jednoho účtu se hlásí upozorněním, naplánovaný běh kvůli
        // němu nepadá. Nenulový kód jen když nevyšlo nic.
        return $failed === $accounts->count() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Historie, o kterou se požádalo: od backfill_from do dne před nejstarším
     * číslem, které máme. Stahuje se po čtvrtletích, 3 měsíce = 1 dotaz.
     */
    private function backfill(AccountSync $sync, AdAccount $account, string $label): void
    {
        if ($account->backfill_from === null) {
            return;
        }

        $earliest = $account->isAnalytics() ? $account->traffic()->min('date') : $account->stats()->min('date');
        $to = $earliest ? Carbon::parse($earliest)->subDay() : now()->subDay();

        if ($account->backfill_from->lte($to)) {
            $period = Period::custom($account->backfill_from->toDateString(), $to->toDateString());
            $run = $sync->sync($account, $period);

            if ($run->status !== 'ok') {
                // Zkusí se zase zítra ráno, nic se neopakuje hned.
                $this->error("{$label}: historie se nestáhla. {$run->error}");

                return;
            }

            $this->info("{$label}: historie {$run->rows} řádků ({$period->label()}).");
        }

        $account->forceFill(['backfill_from' => null])->save();
    }

    private function period(): Period
    {
        $to = $this->option('to') ?: now()->subDay()->toDateString();

        if ($this->option('from')) {
            return Period::between($this->option('from'), $to);
        }

        $days = max(1, (int) ($this->option('days') ?: config('ads.sync_days')));

        return Period::between(Carbon::parse($to)->subDays($days - 1), $to);
    }
}
