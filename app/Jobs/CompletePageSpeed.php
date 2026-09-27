<?php

namespace App\Jobs;

use App\Models\Crm\Company;
use App\Support\Crm\Scout\ProspectScout;
use App\Support\Crm\Scout\WebScout;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Doměří PageSpeed firmám, které přišly přes API s měřením bez něj
 * (v Claude Code klíč k PageSpeed není). Jeden web trvá až minutu,
 * proto běží po odeslání odpovědi. Skóre se přepočítá se stejným
 * posudkem, Claude se znovu neptá.
 */
class CompletePageSpeed
{
    use Dispatchable;

    /** @param  list<int>  $companyIds */
    public function __construct(public array $companyIds) {}

    public function handle(WebScout $web, ProspectScout $scout): void
    {
        ignore_user_abort(true);
        set_time_limit(120 * max(1, count($this->companyIds)));

        foreach (Company::whereKey($this->companyIds)->get() as $company) {
            $data = $company->scout_data ?? [];
            $m = $data['measurements'] ?? [];

            if (! ($m['reachable'] ?? false) || ($m['pagespeed'] ?? null) !== null || empty($m['final_url'])) {
                continue;
            }

            $scout->record($company, $web->pageSpeedResult($m['final_url']) + $m, $data['ai'] ?? null, askAi: false);
            $scout->parkIfRejected($company);
        }
    }
}
