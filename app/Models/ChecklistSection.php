<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Podnadpis uvnitř kategorie. Rozděluje dlouhou tabulku položek
 * na čitelné bloky.
 */
class ChecklistSection extends Model
{
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChecklistCategory::class, 'checklist_category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
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
        return Checklist::progressFrom($this->items);
    }
}
