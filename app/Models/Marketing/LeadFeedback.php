<?php

namespace App\Models\Marketing;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Co se s leady z reklam stalo u klienta: kolik se ozvalo, kolik bylo
 * relevantních, kolik skončilo obchodem. Bez toho se levný lead nesmí
 * vydávat za úspěch.
 */
class LeadFeedback extends Model
{
    protected $table = 'lead_feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'deal_value' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
