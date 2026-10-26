<?php

namespace App\Console\Commands;

use App\Models\Ads\AdAccount;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\AccountSync;
use App\Support\Ads\AlertEngine;
use App\Support\Ads\Period;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\Platforms\Platforms;
use Carbon\CarbonImmutable;
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
 * Když ranní stažení pár dní neprošlo, doplní se i chybějící dny
 * (config ads.catch_up_days), ať v číslech nezůstane díra.
 *
 * S --requested vyřídí ruční obnovy z detailu klienta (každých 5 minut,
 * u účtu nejvýš jednou za ads.refresh_cooldown_minutes) a hned po nich
 * znovu vyhodnotí upozornění klienta.
 */
class AdsSync extends Command
{
    protected $signature = 'ads:sync
        {--account= : Jen jeden účet (ID v naší databázi nebo číslo účtu u platformy)}
        {--days= : Kolik dní zpět přepsat, výchozí z config/ads.php}
        {--from= : Začátek období (Y-m-d), přebíjí --days}
        {--to= : Konec období (Y-m-d), výchozí včerejšek}
        {--force : Stáhnout i účty, které se dnes už stáhly}
        {--requested : Jen účty, u kterých někdo požádal o ruční obnovu (tlačítko Obnovit čísla)}';

    protected $description = 'Stáhne denní čísla z reklamních účtů klientů (jediný přístup na platformy)';

    public function handle(AccountSync $sync, Platforms $platforms, AccountDirectory $directory, AlertEngine $alerts): int
    {
        ApiGuard::openForSync();

        try {
            return $this->option('requested')
                ? $this->syncRequested($sync, $platforms, $alerts)
                : $this->syncAll($sync, $platforms, $directory);
        } finally {
            ApiGuard::close();
        }
    }

    /**
     * Ruční obnovy z detailu klienta. Stáhne stejné období jako ranní běh
     * i s dosahem a zamítnutými reklamami, pak přepočítá upozornění.
     */
    private function syncRequested(AccountSync $sync, Platforms $platforms, AlertEngine $alerts): int
    {
        $accounts = AdAccount::query()
            ->active()
            ->whereNotNull('refresh_requested_at')
            ->with('client')
            ->get();

        if ($accounts->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $label = "{$account->client->name} · {$account->name}";
            $account->forceFill(['refresh_requested_at' => null, 'refreshed_at' => now()])->save();

            if (! $platforms->for($account->platform)->isConfigured()) {
                $this->warn("{$label}: platforma není napojená, obnova přeskočena.");

                continue;
            }

            $period = $this->dailyPeriod($account);
            $run = $sync->sync($account, $period, withReach: true);
            $run->status === 'ok'
                ? $this->info("{$label}: obnoveno {$run->rows} řádků ({$period->label()}).")
                : $this->error("{$label}: {$run->error}");
        }

        foreach ($accounts->pluck('client')->unique('id') as $client) {
            $alerts->check($client->load(['adAccounts', 'adSettings']), CarbonImmutable::today());
        }

        return self::SUCCESS;
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
        $fixed = $this->option('from') || $this->option('to') || $this->option('days');

        foreach ($accounts as $account) {
            $label = "{$account->client->name} · {$account->name}";

            if ($daily && ! $this->option('force') && $account->last_synced_at?->isToday()) {
                $this->line("{$label}: dnes už stažený.");

                continue;
            }

            // Běžný ranní běh doplní i dny, které chybí od posledního úspěšného stažení.
            $run = $sync->sync($account, $fixed ? $period : $this->dailyPeriod($account), $withReach);

            if ($run->status === 'ok') {
                $this->info("{$label}: {$run->rows} řádků ({$run->date_from->format('j. n.')}–{$run->date_to->format('j. n. Y')}).");
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

    /**
     * Ranní období účtu: posledních N dní (Meta dopočítává konverze), a když
     * stažení pár dní neprošlo, i chybějící dny od posledního kompletního.
     */
    private function dailyPeriod(AdAccount $account): Period
    {
        $to = CarbonImmutable::yesterday();
        $from = $to->subDays(max(1, (int) config('ads.sync_days')) - 1);

        if ($account->data_through !== null) {
            $gapFrom = CarbonImmutable::parse($account->data_through)->addDay();
            $from = $from->min($gapFrom)->max($to->subDays(max(1, (int) config('ads.catch_up_days')) - 1));
        }

        return Period::between($from, $to);
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
