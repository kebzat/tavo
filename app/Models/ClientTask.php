<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Úkol u klienta. Klient na přehledu vidí název, popis, stav a odpracované
 * hodiny, ne jednotlivé zápisy času ani interní poznámku.
 */
class ClientTask extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'task_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', TaskStatus::Done->value);
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            // Datum dokončení se doplní samo a při návratu do práce zmizí.
            if ($task->status === TaskStatus::Done) {
                $task->done_on ??= now()->toDateString();
            } else {
                $task->done_on = null;
            }

            $task->planned_for = $task->planned_for?->startOfMonth();
        });
    }

    protected function casts(): array
    {
        return [
            'area' => WorkArea::class,
            'status' => TaskStatus::class,
            'planned_for' => 'date',
            'done_on' => 'date',
        ];
    }
}
