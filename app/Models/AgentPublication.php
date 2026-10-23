<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno zveřejnění hotové práce agenta (nebo vrácení dřívějšího).
 * Stav posouvá App\Support\Agent\Publisher.
 */
class AgentPublication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'pr_opened_at' => 'datetime',
            'merged_at' => 'datetime',
            'finished_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<self, $this> */
    public function reverts(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverts_id');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereIn('status', collect(PublicationStatus::cases())->filter->isRunning()->map->value->all());
    }

    public function isRestore(): bool
    {
        return $this->kind === 'restore';
    }

    public function isRevert(): bool
    {
        return $this->reverts_id !== null;
    }

    /**
     * Chyba, se kterou je potřeba něco udělat: karta ji nechá nahoře hodinu,
     * dokud ji někdo neskryje, nebo dokud stejná práce neprojde dalším pokusem.
     *
     * @param  iterable<self>  $recent  poslední zveřejnění projektu
     */
    public function needsAttention(iterable $recent): bool
    {
        if (! $this->status->isProblem() || $this->dismissed_at !== null || ! $this->finished_at?->gt(now()->subHour())) {
            return false;
        }

        // U kopie živého webu je větev vždycky „kopie“: projde-li později
        // jakékoli zveřejnění, nese i to, co napoprvé nevyšlo.
        return ! collect($recent)->contains(fn (self $later): bool => $later->id > $this->id
            && $later->branch === $this->branch
            && $later->isRevert() === $this->isRevert()
            && $later->status->isMerged());
    }

    public function pullUrl(): ?string
    {
        return $this->pr_number ? 'https://github.com/'.$this->repo.'/pull/'.$this->pr_number : null;
    }
}
