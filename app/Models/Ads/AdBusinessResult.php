<?php

namespace App\Models\Ads;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Objednávky a tržby e-shopu za období, zapsané z administrace e-shopu. */
class AdBusinessResult extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'orders' => 'integer',
            'revenue' => 'float',
        ];
    }
}
