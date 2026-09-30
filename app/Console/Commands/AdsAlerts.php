<?php

namespace App\Console\Commands;

use App\Support\Ads\AlertEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Denní kontrola reklam. Běží po synchronizaci, ať pracuje s čerstvými čísly. */
class AdsAlerts extends Command
{
    protected $signature = 'ads:alerts {--date= : Den, ke kterému kontrolovat (Y-m-d), výchozí dnes}';

    protected $description = 'Vyhodnotí pravidla upozornění u reklam klientů';

    public function handle(AlertEngine $engine): int
    {
        $counts = $engine->run($this->option('date') ? Carbon::parse($this->option('date')) : null);

        $this->info("Nová upozornění: {$counts['opened']}, trvající: {$counts['updated']}, vyřešená: {$counts['resolved']}.");

        return self::SUCCESS;
    }
}
