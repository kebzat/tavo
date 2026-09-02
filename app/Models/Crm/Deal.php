<?php

namespace App\Models\Crm;

use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Models\User;
use App\Observers\Crm\DealObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Konkrétní obchodní příležitost. Firma jich může mít víc za sebou —
 * migrace na jaře, správa od podzimu — a každá si vede vlastní fázi i hodnotu.
 */
#[ObservedBy(DealObserver::class)]
class Deal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_deals';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** Rozjednané obchody — do součtů pipeline se uzavřené nepočítají. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value]);
    }

    /**
     * Očekávaná hodnota. Nezaokrouhluje se po jednotlivých obchodech, ale
     * až v součtu sloupce — jinak se chyby zaokrouhlení nasčítají.
     */
    public function weightedValue(): float
    {
        return ($this->value_czk ?? 0) * $this->probability / 100;
    }

    /** Jak dlouho obchod leží v současné fázi. Ležáky jsou vidět na kartě. */
    public function daysInStage(): int
    {
        return (int) ($this->stage_changed_at ?? $this->created_at ?? now())->diffInDays(now());
    }

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'package' => DealPackage::class,
            'probability' => 'integer',
            'value_czk' => 'integer',
            'expected_close_at' => 'date',
            'stage_changed_at' => 'datetime',
            'proposal_sent_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }
}
