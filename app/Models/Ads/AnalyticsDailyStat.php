<?php

namespace App\Models\Ads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Návštěvnost jednoho kanálu za den podle GA4. */
class AnalyticsDailyStat extends Model
{
    protected $guarded = [];

    public const SUMS = ['sessions', 'users', 'engaged_sessions', 'key_events', 'purchases', 'revenue'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
