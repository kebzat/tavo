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

    public function __construct(public int $accountId, public ?int $days = null) {}

    public function handle(AccountSync $sync): void
    {
        ignore_user_abort(true);
        set_time_limit(300);

        $account = AdAccount::find($this->accountId);

        if ($account === null) {
            return;
        }

        $days = $this->days ?? (int) config('ads.backfill_days');
        $yesterday = now()->subDay();

        $sync->sync($account, Period::between($yesterday->copy()->subDays($days - 1), $yesterday));
    }
}
