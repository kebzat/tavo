<?php

namespace App\Models\Ads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Přesný dosah účtu za jedno přednastavené období (7 dní, 30 dní, měsíc…).
 * Dosah se přes dny sčítat nedá, proto ho Meta počítá za celé období
 * a každá synchronizace řádky účtu přepíše. Viz App\Support\Ads\PeriodReach.
 */
class AdPeriodReach extends Model
{
    protected $table = 'ad_period_reach';

    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'reach' => 'integer',
            'impressions' => 'integer',
            'frequency' => 'float',
            'unique_link_clicks' => 'integer',
        ];
    }
}
