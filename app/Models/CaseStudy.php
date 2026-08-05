<?php

namespace App\Models;

use App\Models\Concerns\HasContentBlocks;
use App\Support\ResponsiveImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CaseStudy extends Model implements HasMedia
{
    use HasContentBlocks;
    use InteractsWithMedia;

    public const MEDIA_THUMB = 'thumb';

    /** Galerie na detailu — libovolný počet obrázků včetně žádného. */
    public const MEDIA_GALLERY = 'gallery';

    protected $guarded = [];

    protected $casts = [
        'is_featured' => 'boolean',
        'published' => 'boolean',
        'tags' => 'array',
        'problem_points' => 'array',
        'blocks' => 'array',
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
        // useDisk('public') — bez něj by se obrázky uložily podle výchozího disku
        // aplikace (`local`), odkud je web nedosáhne.
        $this->addMediaCollection(self::MEDIA_THUMB)->singleFile()->useDisk('public');
        $this->addMediaCollection(self::MEDIA_GALLERY)->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && $media->mime_type === 'image/svg+xml') {
            return;
        }

        $this->addMediaConversion('preview')->width(600)->nonQueued();
    }

    /**
     * Náhled reference i s rozměry a sadou zmenšenin pro `srcset`.
     *
     * @return array{src: string, srcset: ?string, width: ?int, height: ?int, alt: string}|null
     */
    public function thumbImage(): ?array
    {
        $media = $this->getFirstMedia(self::MEDIA_THUMB);

        if (! $media) {
            return null;
        }

        return ResponsiveImage::make($media->getPathRelativeToRoot(), $this->imageAlt());
    }

    /**
     * Cesta k náhledu na disku `public`. Používá se jako obrázek pro sdílení —
     * proto originál, ne WebP zmenšenina: LinkedIn a další čtečky odkazů si
     * s WebP neporadí.
     */
    public function thumbPath(): ?string
    {
        return $this->getFirstMedia(self::MEDIA_THUMB)?->getPathRelativeToRoot();
    }

    public function imageAlt(string $collection = self::MEDIA_THUMB): string
    {
        return $this->getFirstMedia($collection)?->getCustomProperty('alt') ?: $this->title;
    }

    /**
     * Galerie pro detail reference. Každý obrázek nese rozměry, aby mu šlo
     * dopředu rezervovat místo a stránka při načítání neposkakovala.
     *
     * @return Collection<int, array{src: string, srcset: ?string, width: ?int, height: ?int, alt: string}>
     */
    public function galleryImages(): Collection
    {
        return $this->getMedia(self::MEDIA_GALLERY)
            ->values()
            ->map(fn (Media $media, int $index) => ResponsiveImage::make(
                $media->getPathRelativeToRoot(),
                $media->getCustomProperty('alt') ?: $this->title.' — ukázka '.($index + 1),
            ))
            ->filter()
            ->values();
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
