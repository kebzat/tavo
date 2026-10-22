<?php

namespace App\Models\Marketing;

use App\Enums\Ads\CreativeVerdict;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paměť kreativních testů: co jsme zkoušeli, proč, s jakým výsledkem a co z toho plyne. */
class CreativeTest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'budget' => 'decimal:2',
            'verdict' => CreativeVerdict::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
