<?php

namespace App\Console\Commands;

use App\Models\Ads\AdAccount;
use App\Support\Ads\AccountSync;
use App\Support\Ads\Period;
use App\Support\Ads\Platforms\Platforms;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Denní stažení čísel ze všech zapnutých reklamních účtů.
 *
 * Bez parametrů přepíše posledních N dní (config ads.sync_days), protože
 * Meta konverze zpětně dopočítává. Historii jde stáhnout přes --from/--to.
 */
class AdsSync extends Command
{
    protected $signature = 'ads:sync
        {--account= : Jen jeden účet (ID v naší databázi nebo číslo účtu u platformy)}
        {--days= : Kolik dní zpět přepsat, výchozí z config/ads.php}
        {--from= : Začátek období (Y-m-d), přebíjí --days}
        {--to= : Konec období (Y-m-d), výchozí včerejšek}';

    protected $description = 'Stáhne denní čísla z reklamních účtů klientů';

    public function handle(AccountSync $sync, Platforms $platforms): int
    {
        $period = $this->period();

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

        foreach ($accounts as $account) {
            $run = $sync->sync($account, $period);

            if ($run->status === 'ok') {
                $this->info("{$account->client->name} · {$account->name}: {$run->rows} řádků ({$period->label()}).");
            } elseif ($run->status === 'skipped') {
                $this->line("{$account->client->name} · {$account->name}: {$run->error}");
            } else {
                $failed++;
                $this->error("{$account->client->name} · {$account->name}: {$run->error}");
            }
        }

        // Selhání jednoho účtu se hlásí upozorněním, naplánovaný běh kvůli
        // němu nepadá. Nenulový kód jen když nevyšlo nic.
        return $failed === $accounts->count() ? self::FAILURE : self::SUCCESS;
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
