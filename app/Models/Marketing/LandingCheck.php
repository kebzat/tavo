<?php

namespace App\Models\Marketing;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Poslední kontrola cílové stránky klienta (php artisan marketing:landing-check). */
class LandingCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'has_meta_pixel' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
