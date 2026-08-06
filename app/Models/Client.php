<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $guarded = [];

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }
}
