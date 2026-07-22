<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    public const STATUSES = [
        'new' => 'Nová',
        'in_progress' => 'Řešíme',
        'won' => 'Vyhráno',
        'lost' => 'Ztraceno',
    ];

    protected $guarded = [];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
