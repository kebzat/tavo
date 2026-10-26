<?php

namespace App\Models\Ads;

use App\Support\Ads\Metrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Součty jedné reklamy za posledních 30 dní. Viz App\Support\Ads\TopAds. */
class AdTopAd extends Model
{
    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /** Součty reklamy jako Metrics, ať se poměry počítají stejně jako jinde. */
    public function metrics(): Metrics
    {
        return Metrics::fromArray($this->getAttributes());
    }

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }
}
