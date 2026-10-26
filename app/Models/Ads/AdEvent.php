<?php

namespace App\Models\Ads;

use App\Enums\Ads\AdEventType;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Termín v kalendáři reklam: konec licence UGC, začátek akce, příprava
 * Black Friday, konec kampaně, čekání na podklady. Od `due_on − remind_days`
 * se ukazuje v ranním přehledu a e-mailu a visí tam i po termínu, dokud
 * ho někdo neoznačí jako vyřešený.
 */
class AdEvent extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'remind_days' => 7,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdCampaignGroup::class, 'ad_campaign_group_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /** Nevyřešené termíny, na které se už má upozorňovat (nebo jsou po termínu). */
    public function scopeDue(Builder $query, CarbonInterface|string|null $today = null): Builder
    {
        return $query->open()->whereDate('remind_on', '<=', CarbonImmutable::parse($today ?? today())->toDateString());
    }

    public function isOverdue(CarbonInterface|string|null $today = null): bool
    {
        return $this->resolved_at === null && $this->due_on->lt(CarbonImmutable::parse($today ?? today())->startOfDay());
    }

    /** „dnes“, „zítra“, „za 5 dní“, „před 2 dny“. */
    public function whenLabel(CarbonInterface|string|null $today = null): string
    {
        $today = CarbonImmutable::parse($today ?? today())->startOfDay();
        $days = (int) $today->diffInDays($this->due_on, false);

        return match (true) {
            $days === 0 => 'dnes',
            $days === 1 => 'zítra',
            $days === -1 => 'včera',
            $days > 1 => 'za '.$days.' '.($days < 5 ? 'dny' : 'dní'),
            default => 'před '.abs($days).' dny',
        };
    }

    /** Na co se termín vztahuje: skupina, kampaň, konkrétní reklamy. */
    public function scopeLabel(): ?string
    {
        return collect([$this->group?->name, $this->campaign?->name, $this->ads])->filter()->implode(' · ') ?: null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            $event->remind_on = $event->due_on?->copy()->subDays(max(0, (int) $event->remind_days));
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AdEventType::class,
            'due_on' => 'date',
            'remind_on' => 'date',
            'remind_days' => 'integer',
            'links' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
