<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Jedno otevření sdíleného dokumentu (audit, nabídka spolupráce, report).
 * Počty na dokumentu se z nich dopočítávají, viz TracksClientViews.
 */
class ClientView extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Hrubý popis zařízení pro výpis v administraci. */
    public function device(): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', (string) $this->user_agent) => 'tablet',
            (bool) preg_match('/Mobi|Android|iPhone/i', (string) $this->user_agent) => 'mobil',
            default => 'počítač',
        };
    }
}
