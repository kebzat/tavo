<?php

namespace App\Models\Ads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Přesný dosah skupiny kampaní v jednom účtu za období. Viz App\Support\Ads\GroupReach. */
class AdGroupReach extends Model
{
    protected $table = 'ad_group_reach';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdCampaignGroup::class, 'ad_campaign_group_id');
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
