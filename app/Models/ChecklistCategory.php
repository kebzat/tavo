<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Karta na rozcestníku sdíleného checklistu. Sdružuje několik sekcí,
 * aby úvodní stránka nebyla seznam patnácti odkazů.
 */
class ChecklistCategory extends Model
{
    protected $guarded = [];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class);
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(ChecklistItem::class, ChecklistSection::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    /**
     * @return array{total: int, done: int, percent: int}
     */
    public function progress(): array
    {
        $items = $this->relationLoaded('sections')
            ? $this->sections->flatMap(fn (ChecklistSection $section) => $section->items)
            : $this->items;

        return Checklist::progressFrom($items);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
