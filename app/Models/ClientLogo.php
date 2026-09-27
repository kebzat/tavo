<?php

namespace App\Models;

use App\Support\ResponsiveImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Logo klienta do pásu na homepage. Název slouží i jako alt text.
 */
class ClientLogo extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const MEDIA_LOGO = 'logo';

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

    public function registerMediaCollections(): void
    {
        // useDisk('public') — viz komentář v CaseStudy::registerMediaCollections().
        // SVG je u log nejčastější formát a zmenšeniny z něj dělat nejde ani netřeba.
        $this->addMediaCollection(self::MEDIA_LOGO)
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/svg+xml', 'image/png', 'image/webp', 'image/jpeg']);
    }

    /**
     * @return array{src: string, srcset: ?string, width: ?int, height: ?int, alt: string}|null
     */
    public function logoImage(): ?array
    {
        $media = $this->getFirstMedia(self::MEDIA_LOGO);

        return $media ? ResponsiveImage::make($media->getPathRelativeToRoot(), $this->name) : null;
    }
}
