<?php

namespace App\Console\Commands;

use App\Models\AgentPublication;
use App\Support\Agent\InstantPublisher;
use App\Support\Agent\Publisher;
use Illuminate\Console\Command;

/**
 * Posune rozběhnutá zveřejnění hotové práce agenta (testy → sloučení →
 * nasazení, u taveo nahrání ze serveru), i když stránku v nástrojích nikdo
 * nemá otevřenou, a doplní historii na GitHubu k okamžitým zveřejněním.
 * Plánovač ho pouští každou minutu, bez rozběhnutých nic nevolá.
 */
class AgentPublish extends Command
{
    protected $signature = 'agent:zverejneni';

    protected $description = 'Posune rozběhnutá zveřejnění práce agenta (GitHub)';

    public function handle(Publisher $publisher, InstantPublisher $instant): int
    {
        AgentPublication::query()->running()->get()->each(fn (AgentPublication $publication) => $publisher->advance($publication));
        $instant->syncPendingHistory();

        return self::SUCCESS;
    }
}
