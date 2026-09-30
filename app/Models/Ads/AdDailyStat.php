<?php

namespace App\Models\Ads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Čísla jedné kampaně za jeden den. Jen součty, poměry počítá App\Support\Ads\Metrics. */
class AdDailyStat extends Model
{
    protected $guarded = [];

    /** Sčítatelné sloupce. Z nich se skládají souhrny za období. */
    public const SUMS = [
        'spend', 'impressions', 'reach', 'clicks', 'link_clicks',
        'purchases', 'purchase_value', 'leads', 'add_to_cart', 'checkouts',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'raw' => 'array',
        ];
    }
}
