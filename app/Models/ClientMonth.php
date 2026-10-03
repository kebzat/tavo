<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cíl měsíce a náš komentář k němu („co jsme zjistili“). */
class ClientMonth extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected static function booted(): void
    {
        static::saving(fn (self $month) => $month->month = $month->month?->startOfMonth());
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }
}
