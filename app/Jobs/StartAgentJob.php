<?php

namespace App\Jobs;

use App\Models\AgentJob;
use App\Support\ClickUp\ClickUpAgent;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Spustí úlohu z ClickUpu. Na produkci neběží fronta, takže jede po odeslání
 * odpovědi webhooku. Samotná práce agenta běží v kontejneru, tady jen
 * dohledání úkolu, potvrzení v ClickUpu a předání.
 */
class StartAgentJob
{
    use Dispatchable;

    public function __construct(public int $jobId) {}

    public function handle(ClickUpAgent $agent): void
    {
        ignore_user_abort(true);
        set_time_limit(120);

        $job = AgentJob::find($this->jobId);

        if ($job?->status === AgentJob::STATUS_QUEUED) {
            $agent->start($job);
        }
    }
}
