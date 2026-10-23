<?php

namespace App\Console\Commands;

use App\Models\AgentJob;
use App\Support\ClickUp\ClickUpAgent;
use Illuminate\Console\Command;

/**
 * Úlohy z ClickUpu, které se ztratily: běží déle, než služba agenta dovolí
 * (30 min a rezerva), takže výsledek už nepřijde (restart služby, pád).
 * Uzavře je a napíše to do ClickUpu, ať na odpověď nikdo nečeká.
 */
class ClickUpWatch extends Command
{
    protected $signature = 'clickup:hlidat';

    protected $description = 'Uzavře úlohy agenta z ClickUpu, které nedoběhly';

    /** Čekání ve frontě projektu + limit běhu 30 min + rezerva. */
    private const LOST_AFTER_MINUTES = 120;

    public function handle(ClickUpAgent $agent): int
    {
        $lost = AgentJob::query()
            ->where('status', AgentJob::STATUS_RUNNING)
            ->where('started_at', '<', now()->subMinutes(self::LOST_AFTER_MINUTES))
            ->get();

        foreach ($lost as $job) {
            rescue(fn () => $agent->finish($job, ['ok' => false, 'chyba' => 'úloha nedoběhla, výsledek nepřišel (restart služby agenta?)']), report: false);
        }

        $this->info('Uzavřeno: '.$lost->count());

        return self::SUCCESS;
    }
}
