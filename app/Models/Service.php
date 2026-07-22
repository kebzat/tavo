<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = [];

    protected $casts = [
        'has_detail_page' => 'boolean',
        'published' => 'boolean',
        'target_groups' => 'array',
        'offerings' => 'array',
        'process_steps' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    public function url(): ?string
    {
        return $this->has_detail_page ? route('services.show', $this->slug) : null;
    }
}
