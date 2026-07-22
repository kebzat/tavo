<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Founder extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const MEDIA_PHOTO = 'photo';

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_PHOTO)->singleFile();
    }

    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl(self::MEDIA_PHOTO) ?: null;
    }
}
