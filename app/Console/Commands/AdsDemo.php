<?php

namespace App\Console\Commands;

use App\Support\Ads\DemoData;
use Illuminate\Console\Command;

/**
 * Ukázkoví klienti s vymyšlenými čísly, ať jde nástroj vyzkoušet bez
 * přístupů k Metě a Googlu. Čísla generuje ukázková platforma a denní
 * synchronizace je doplňuje, takže nestárnou.
 */
class AdsDemo extends Command
{
    protected $signature = 'ads:demo
        {--remove : Smaže ukázkové klienty i s jejich čísly}
        {--days=90 : Kolik dní historie vygenerovat}';

    protected $description = 'Založí (nebo smaže) ukázkové klienty v reklamách';

    public function handle(DemoData $demo): int
    {
        if ($this->option('remove')) {
            $this->info('Smazáno ukázkových klientů: '.$demo->remove().'.');

            return self::SUCCESS;
        }

        if (! config('ads.demo_enabled')) {
            $this->error('Ukázková data jsou vypnutá (ADS_DEMO=false).');

            return self::FAILURE;
        }

        $created = $demo->install(max(14, (int) $this->option('days')));
        $this->info("Hotovo. Nových ukázkových klientů: {$created}.");

        return self::SUCCESS;
    }
}
