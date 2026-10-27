<?php

namespace App\Console\Commands;

use App\Support\Agent\ClientProjects;
use Illuminate\Console\Command;

/**
 * Založí projekty agenta klientům, kteří na něj čekají (Projekty → Založit agenty
 * klientům, Klienti → Načíst z ClickUpu) a skillům (Mozek → Skilly). Bez žádosti nic nevolá.
 */
class AgentClientProjects extends Command
{
    protected $signature = 'agent:projekty-klientu';

    protected $description = 'Založí čekající projekty agenta pro klienty (po jednom, s odstupem)';

    public function handle(ClientProjects $projects): int
    {
        if (ClientProjects::queued()->isEmpty()) {
            return self::SUCCESS;
        }

        set_time_limit(0);
        $this->line('Založeno projektů: '.$projects->run());

        return self::SUCCESS;
    }
}
