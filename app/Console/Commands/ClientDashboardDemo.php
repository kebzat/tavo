<?php

namespace App\Console\Commands;

use App\Support\ClientDashboardDemo as Demo;
use Illuminate\Console\Command;

/**
 * Ukázkový přehled spolupráce s vymyšleným e-shopem. Plánovač ho obnovuje
 * 1. v měsíci, ať ukazuje aktuální měsíc a nestárne.
 */
class ClientDashboardDemo extends Command
{
    protected $signature = 'clients:dashboard-demo
        {--remove : Smaže ukázkového klienta}
        {--refresh : Jen obnoví data existující ukázky, smazanou nezaloží}';

    protected $description = 'Založí nebo obnoví ukázkový přehled spolupráce';

    public function handle(Demo $demo): int
    {
        if ($this->option('remove')) {
            $this->info($demo->remove() ? 'Ukázkový klient smazán.' : 'Ukázkový klient neexistuje.');

            return self::SUCCESS;
        }

        // Plánovač pouští --refresh: smazaná ukázka se sama znovu nezaloží.
        if ($this->option('refresh') && ! $demo->client()) {
            $this->info('Ukázkový klient neexistuje, nic neobnovuji.');

            return self::SUCCESS;
        }

        $this->info('Hotovo: '.$demo->install());

        return self::SUCCESS;
    }
}
