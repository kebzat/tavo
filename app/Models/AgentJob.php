<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Úloha pro agenta z komentáře „@agent …“ v ClickUpu.
 * Průběh: queued → running → done / failed, nebo rejected (bez projektu, limit).
 */
class AgentJob extends Model
{
    protected $guarded = [];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Úlohy, které opravdu spustily agenta (do limitů se počítají jen ty). */
    public function scopeStarted(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RUNNING, self::STATUS_DONE, self::STATUS_FAILED]);
    }

    /** Odpověď, kterou jsme do ClickUpu napsali my, se nesmí brát jako nové zadání. */
    public static function isOurReply(string $commentId): bool
    {
        return self::query()->whereJsonContains('reply_comment_ids', $commentId)->exists();
    }

    public function rememberReply(?string $commentId): void
    {
        if ($commentId) {
            $this->update(['reply_comment_ids' => [...($this->reply_comment_ids ?? []), $commentId]]);
        }
    }

    protected function casts(): array
    {
        return [
            'reply_comment_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
