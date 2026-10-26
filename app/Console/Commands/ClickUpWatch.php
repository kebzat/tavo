<?php

namespace App\Console\Commands;

use App\Models\AgentJob;
use App\Support\AdGraphics\GraphicsAgent;
use App\Support\ClickUp\ClickUpAgent;
use App\Support\Knowledge\BrainAgent;
use Illuminate\Console\Command;

/**
 * Úlohy agenta, které se ztratily: běží déle, než služba agenta dovolí
 * (30 min a rezerva), takže výsledek už nepřijde (restart služby, pád).
 * Uzavře je. U ClickUpu to napíše do úkolu, u reklamní grafiky do zadání,
 * u mozku k podkladům nebo otázce, ať na výsledek nikdo nečeká.
 */
class ClickUpWatch extends Command
{
    protected $signature = 'clickup:hlidat';

    protected $description = 'Uzavře úlohy agenta (ClickUp, reklamní grafika, mozek), které nedoběhly';

    /** Čekání ve frontě projektu + limit běhu 30 min + rezerva. */
    private const LOST_AFTER_MINUTES = 120;

    public function handle(ClickUpAgent $agent, GraphicsAgent $graphics, BrainAgent $brain): int
    {
        $lost = AgentJob::query()
            ->where('status', AgentJob::STATUS_RUNNING)
            ->where('started_at', '<', now()->subMinutes(self::LOST_AFTER_MINUTES))
            ->get();

        foreach ($lost as $job) {
            $result = ['ok' => false, 'chyba' => 'úloha nedoběhla, výsledek nepřišel (restart služby agenta?)'];
            rescue(fn () => match ($job->source) {
                GraphicsAgent::SOURCE => $graphics->finish($job, $result),
                BrainAgent::SOURCE, BrainAgent::SOURCE_QUESTION => $brain->finish($job, $result),
                default => $agent->finish($job, $result),
            }, report: false);
        }

        $this->info('Uzavřeno: '.$lost->count());

        return self::SUCCESS;
    }
}
