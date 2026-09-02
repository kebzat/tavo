<?php

namespace App\Models\Crm;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Models\User;
use App\Observers\Crm\ActivityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Záznam o tom, co jsme udělali. Neupravuje se ani nemaže při běžné práci —
 * je to deník, ze kterého se počítají KPI a bere se historie firmy.
 */
#[ObservedBy(ActivityObserver::class)]
class Activity extends Model
{
    use HasFactory;

    protected $table = 'crm_activities';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Aktivity v zadaném období podle toho, kdy se staly. */
    public function scopeHappenedBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('happened_at', [$from, $to]);
    }

    /**
     * První aktivita u dané firmy = oslovení. Každá další je follow-up.
     * Rozlišuje se podle času, ne podle pořadí vložení — aktivity se logují
     * i zpětně a pořadí ID by pak lhalo.
     */
    public function isFirstForCompany(): bool
    {
        return ! self::query()
            ->where('company_id', $this->company_id)
            ->whereKeyNot($this->getKey())
            ->whereIn('type', array_column(ActivityType::outreach(), 'value'))
            ->where('happened_at', '<', $this->happened_at)
            ->exists();
    }

    /** Zkrácený text do časové osy a do denního souhrnu. */
    public function excerpt(int $length = 120): string
    {
        return Str::limit(trim(strip_tags((string) $this->body)), $length);
    }

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'outcome' => ActivityOutcome::class,
            'happened_at' => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }
}
