<?php

namespace App\Models\Crm;

use App\Enums\Crm\DemandSource;
use App\Enums\Crm\DemandStatus;
use App\Enums\Crm\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Poptávka z externího portálu. Přitéká strojově ranním importem, většina
 * skončí bez reakce — proto vlastní tabulka a vazba na firmu až ve chvíli,
 * kdy z poptávky něco je.
 */
class Demand extends Model
{
    use HasFactory;

    protected $table = 'crm_demands';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Nové poptávky v pořadí, v jakém se na ně máme podívat. */
    public function scopeUntouched(Builder $query): Builder
    {
        return $query->where('status', DemandStatus::New)
            ->orderBy('priority')
            ->orderByDesc('posted_at');
    }

    protected function casts(): array
    {
        return [
            'source' => DemandSource::class,
            'status' => DemandStatus::class,
            'priority' => Priority::class,
            'posted_at' => 'date',
            'replied_at' => 'datetime',
        ];
    }
}
