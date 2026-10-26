<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\ClickUp\ClientImport;
use Illuminate\Console\Command;

/**
 * Načte složky z ClickUpu do archivu klientů, kteří o to požádali
 * (Klienti → Načíst z ClickUpu, u klienta Načíst z ClickUpu znovu).
 * Bez žádosti nic nevolá. Načtení, které spadlo v půlce (restart serveru),
 * se po hodině zkusí znovu. Viz docs/AGENT.md, Klienti z ClickUpu.
 */
class ClickUpImport extends Command
{
    protected $signature = 'clickup:nacist';

    protected $description = 'Načte složky z ClickUpu do archivu klientů (jen na žádost z nástrojů)';

    public function handle(ClientImport $import): int
    {
        set_time_limit(0);

        $clients = Client::query()->whereNotNull('clickup_folder_id')->whereNotNull('clickup_import')->get()
            ->filter(fn (Client $client): bool => match ($client->clickup_import['status'] ?? null) {
                ClientImport::STATUS_QUEUED => true,
                ClientImport::STATUS_RUNNING => now()->subHour()->isAfter($client->clickup_import['started_at'] ?? now()),
                default => false,
            });

        foreach ($clients as $client) {
            $this->line('Načítám '.$client->name.'…');
            $import->run($client);
            $this->line(ClientImport::summary($client->refresh()) ?? '');
        }

        return self::SUCCESS;
    }
}
