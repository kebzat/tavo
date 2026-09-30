<?php

namespace App\Models\Ads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Záznam jednoho stažení čísel z účtu. Z posledních běhů se pozná, jestli synchronizace jede. */
class AdSyncRun extends Model
{
    public $timestamps = false;

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
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
