<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kontrola jednoho člověka u auditu, nabídky, checklistu nebo reportu. Viz App\Models\Concerns\HasReviews. */
class Review extends Model
{
    protected $guarded = [];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOutdated(): bool
    {
        return $this->outdated_at !== null;
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'outdated_at' => 'datetime',
        ];
    }
}
