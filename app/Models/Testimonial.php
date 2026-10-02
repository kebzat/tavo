<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Recenze klienta do bloku „Co o nás říkají klienti" na homepage.
 * `person` říká, komu recenzi klient napsal (Pavel, nebo Tom), společné
 * zakázky nechávají pole prázdné.
 */
class Testimonial extends Model
{
    public const PEOPLE = [
        'pavel' => 'Pavel',
        'tom' => 'Tom',
    ];

    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /** Komu recenze patří, pro štítek u citace. Bez osoby `null`. */
    public function personName(): ?string
    {
        return self::PEOPLE[$this->person] ?? null;
    }
}
