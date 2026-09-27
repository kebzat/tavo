<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Support\Crm\AuditFromCompany;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Podrobný audit od Clauda. Trvá několik minut, proto běží až po odeslání
 * odpovědi prohlížeči (dispatchAfterResponse): administrace se hned vrátí
 * a audit se přepíše, jakmile Claude doběhne. Nepotřebuje frontu ani worker.
 */
class WriteDeepAudit
{
    use Dispatchable;

    public function __construct(public int $auditId) {}

    public function handle(AuditFromCompany $builder): void
    {
        ignore_user_abort(true);
        set_time_limit(900);

        $audit = Audit::find($this->auditId);

        if ($audit !== null) {
            $builder->rewriteWithClaude($audit);
        }
    }

    /** Označí audit jako rozepsaný a spustí přepis po odeslání odpovědi. */
    public static function start(Audit $audit): void
    {
        $audit->forceFill(['ai_status' => 'running', 'ai_note' => 'Spuštěno '.now()->format('j. n. Y H:i')])->save();

        self::dispatchAfterResponse($audit->getKey());
    }
}
