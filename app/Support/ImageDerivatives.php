<?php

namespace App\Support;

use App\Models\CaseStudy;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Hledá v obsahu obrázky, ze kterých se mají vyrobit zmenšeniny.
 *
 * Obrázky přicházejí dvěma cestami: přes MediaLibrary (náhledy referencí,
 * galerie, fotka zakladatelů) a jako holá cesta v JSON bloku skládaného obsahu.
 * Obojí končí na disku `public`, takže se s nimi dál pracuje stejně.
 */
final class ImageDerivatives
{
    /** Modely se skládaným obsahem, ve kterém můžou být obrázky bloků. */
    private const BLOCK_MODELS = [CaseStudy::class, Page::class];

    /** Zaregistruje přegenerování na uložení obsahu, ať na to nečeká návštěvník. */
    public static function listen(): void
    {
        Media::saved(function (Media $media): void {
            if ($media->disk === 'public') {
                ResponsiveImage::generate($media->getPathRelativeToRoot());
            }
        });

        foreach (self::BLOCK_MODELS as $model) {
            $model::saved(function (Model $record): void {
                self::fromBlocks($record)->each(ResponsiveImage::generate(...));
            });
        }
    }

    /**
     * Všechny obrázky v obsahu — podklad pro hromadné dogenerování.
     *
     * @return Collection<int, string>
     */
    public static function all(): Collection
    {
        $paths = Media::query()
            ->where('disk', 'public')
            ->get()
            ->map(fn (Media $media): string => $media->getPathRelativeToRoot());

        foreach (self::BLOCK_MODELS as $model) {
            $paths = $paths->merge(
                $model::query()->get()->flatMap(self::fromBlocks(...))
            );
        }

        return $paths->filter()->unique()->values();
    }

    /**
     * Cesty k obrázkům ve skládaném obsahu jednoho záznamu.
     *
     * @return Collection<int, string>
     */
    private static function fromBlocks(Model $record): Collection
    {
        return collect($record->blocks ?? [])
            ->flatMap(function (array $block): array {
                $data = $block['data'] ?? [];

                return array_map(fn (string $key) => $data[$key] ?? null, ['image', 'before', 'after']);
            })
            ->filter()
            ->values();
    }
}
