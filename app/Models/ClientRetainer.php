<?php

namespace App\Models;

use App\Enums\WorkArea;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Část měsíčního paušálu pro jednu oblast (vývoj webu 10 000 Kč, marketing
 * 5 000 Kč). Bez hodin v paušálu se hodiny jen ukazují, nad rámec se neúčtuje.
 */
class ClientRetainer extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Platí paušál aspoň část daného měsíce? */
    public function activeIn(CarbonInterface $month): bool
    {
        return ($this->starts_on === null || $this->starts_on->lte($month->copy()->endOfMonth()))
            && ($this->ends_on === null || $this->ends_on->gte($month->copy()->startOfMonth()));
    }

    protected function casts(): array
    {
        return [
            'area' => WorkArea::class,
            'monthly_fee' => 'integer',
            'included_hours' => 'float',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
