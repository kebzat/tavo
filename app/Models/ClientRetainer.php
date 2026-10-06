<?php

namespace App\Models;

use App\Enums\WorkArea;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Část měsíčního paušálu pro jednu oblast (vývoj webu 10 000 Kč, marketing
 * 5 000 Kč). Bez hodin v paušálu se hodiny jen ukazují, nad rámec se neúčtuje.
 *
 * Změna částky v čase je další řádek téže oblasti s vlastním Od a Do.
 * Předběžný řádek (is_tentative) je jen plán do Výhledu: nefakturuje se
 * a klient ho v přehledu nevidí.
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

    /** Platí v měsíci a je domluvený. Jen takový se fakturuje a ukazuje klientovi. */
    public function billedIn(CarbonInterface $month): bool
    {
        return ! $this->is_tentative && $this->activeIn($month);
    }

    protected function casts(): array
    {
        return [
            'area' => WorkArea::class,
            'monthly_fee' => 'integer',
            'included_hours' => 'float',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_tentative' => 'boolean',
        ];
    }
}
