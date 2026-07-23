<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CaseStudy extends Model implements HasMedia
{
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

    public function thumbUrl(): ?string
    {
        return $this->getFirstMediaUrl(self::MEDIA_THUMB) ?: null;
    }

    public function imageAlt(string $collection = self::MEDIA_THUMB): string
    {
        return $this->getFirstMedia($collection)?->getCustomProperty('alt') ?: $this->title;
    }

    /**
     * Galerie pro detail reference. Rozměry se čtou ze souboru, aby šlo obrázku
     * dopředu rezervovat místo a stránka při načítání neposkakovala; výsledek
     * se cachuje, takže se soubor sahá jen jednou.
     *
     * @return Collection<int, array{url: string, alt: string, width: ?int, height: ?int}>
     */
    public function galleryImages(): Collection
    {
        return $this->getMedia(self::MEDIA_GALLERY)
            ->values()
            ->map(function (Media $media, int $index) {
                [$width, $height] = $this->imageDimensions($media);

                return [
                    'url' => $media->getUrl(),
                    'alt' => $media->getCustomProperty('alt')
                        ?: $this->title.' — ukázka '.($index + 1),
                    'width' => $width,
                    'height' => $height,
                ];
            });
    }

    /** @return array{0: ?int, 1: ?int} */
    private function imageDimensions(Media $media): array
    {
        return Cache::rememberForever(
            "media-dimensions-{$media->id}-{$media->updated_at?->timestamp}",
            function () use ($media) {
                $path = $media->getPath();

                if (! is_file($path)) {
                    return [null, null];
                }

                $size = @getimagesize($path);

                return $size ? [$size[0], $size[1]] : [null, null];
            },
        );
    }

    /** Má reference vyplněnou marketingovou i vývojářskou roli? */
    public function hasBothRoles(): bool
    {
        return ! empty($this->marketing_items) && ! empty($this->dev_items);
    }

    /**
     * Kam na stránce patří poznámka pod čarou. Původně visela jen pod metrikami,
     * takže u referencí bez čísel zmizela úplně — a právě ty ji potřebují nejvíc.
     *
     * @return 'none'|'results'|'roles'|'standalone'
     */
    public function disclaimerPlacement(): string
    {
        if (! $this->disclaimer) {
            return 'none';
        }

        if ($this->results) {
            return 'results';
        }

        if ($this->marketing_items || $this->dev_items) {
            return 'roles';
        }

        return 'standalone';
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
