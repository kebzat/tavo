<?php

namespace App\Jobs;

use App\Models\Ads\AdAccount;
use App\Support\Ads\AccountSync;
use App\Support\Ads\Period;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Stáhne historii nově propojeného účtu. Na produkci neběží fronta,
 * takže se spouští po odeslání odpovědi a správce nečeká na Metu.
 */
class BackfillAdAccount
{
    use Dispatchable;

    /**
     * Bez období se stáhne celá dostupná historie (config ads.backfill_months),
     * s obdobím jen to vybrané, třeba starší část, která ještě chybí.
     */
    public function __construct(public int $accountId, public ?string $from = null, public ?string $to = null) {}

    public function handle(AccountSync $sync): void
    {
        ignore_user_abort(true);
        set_time_limit(600);

        $account = AdAccount::find($this->accountId);

        if ($account === null) {
            return;
        }

        $sync->sync($account, $this->from && $this->to
            ? Period::custom($this->from, $this->to)
            : self::fullHistory());
    }

    /** Celá historie, kterou Meta vydá, až po včerejšek. */
    public static function fullHistory(): Period
    {
        $months = min(Period::MAX_HISTORY_MONTHS, max(1, (int) config('ads.backfill_months')));

        // Den rezervy, ať začátek přesně na hraně 37 měsíců Meta neodmítne.
        return Period::custom(now()->subMonthsNoOverflow($months)->addDay()->toDateString(), now()->subDay()->toDateString());
    }
}
