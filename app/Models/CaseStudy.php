<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CaseStudy extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const MEDIA_THUMB = 'thumb';

    public const MEDIA_HERO = 'hero';

    protected $guarded = [];

    protected $casts = [
        'is_featured' => 'boolean',
        'published' => 'boolean',
        'tags' => 'array',
        'problem_points' => 'array',
        'marketing_items' => 'array',
        'dev_items' => 'array',
        'results' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CaseStudyCategory::class, 'case_study_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_THUMB)->singleFile();
        $this->addMediaCollection(self::MEDIA_HERO)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && $media->mime_type === 'image/svg+xml') {
            return;
        }

        $this->addMediaConversion('preview')->width(600)->nonQueued();
    }

    public function thumbUrl(): ?string
    {
        return $this->getFirstMediaUrl(self::MEDIA_THUMB) ?: null;
    }

    public function heroUrl(): ?string
    {
        return $this->getFirstMediaUrl(self::MEDIA_HERO) ?: $this->thumbUrl();
    }

    public function imageAlt(string $collection = self::MEDIA_THUMB): string
    {
        return $this->getFirstMedia($collection)?->getCustomProperty('alt') ?: $this->title;
    }

    /** Následující reference v pořadí (pro blok „Další projekt"). */
    public function next(): ?self
    {
        return static::published()
            ->where('id', '!=', $this->id)
            ->orderByRaw('order_column > ? DESC', [$this->order_column])
            ->ordered()
            ->first();
    }
}
