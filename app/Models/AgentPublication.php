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

    public function pullUrl(): ?string
    {
        return $this->pr_number ? 'https://github.com/'.$this->repo.'/pull/'.$this->pr_number : null;
    }
}
