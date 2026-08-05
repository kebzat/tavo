<?php

namespace App\Support;

use App\Settings\SeoSettings;

/**
 * Složí hodnoty pro <head>: titulek, popisek, obrázek pro sdílení a pokyny
 * pro roboty. Drží se v PHP, ať v šabloně nezůstává @php blok s logikou
 * a ať se na výstup dá napsat test.
 */
final class PageMeta
{
    /**
     * @param  array<string, mixed>  $schema  strukturovaná data navíc k organizaci
     * @return array{
     *     title: string,
     *     description: ?string,
     *     canonical: string,
     *     robots: string,
     *     image: ?string,
     *     imageWidth: ?int,
     *     imageHeight: ?int,
     *     imageAlt: ?string,
     *     schema: list<array<string, mixed>>,
     *     gtmId: ?string,
     * }
     */
    public static function build(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        ?string $imageAlt = null,
        array $schema = [],
    ): array {
        $seo = app(SeoSettings::class);

        $imageUrl = $seo->imageUrl($image ?: $seo->og_image);
        [$imageWidth, $imageHeight] = self::imageSize($image ?: $seo->og_image);

        return [
            'title' => ($title ?: $seo->default_title).$seo->title_suffix,
            'description' => $description ?: $seo->default_description,
            'canonical' => url()->current(),
            'robots' => self::robots($seo),
            'image' => $imageUrl,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
            'imageAlt' => $imageUrl ? ($imageAlt ?: ($title ?: $seo->default_title)) : null,
            'schema' => array_merge([StructuredData::professionalService()], $schema),
            'gtmId' => $seo->gtm_id,
        ];
    }

    /**
     * Pokyny pro roboty. Na neveřejném webu prosté noindex, jinak povolení
     * velkého náhledu a plného úryvku — bez toho Google ve výsledcích ukáže
     * jen ořezaný text a miniaturu.
     */
    private static function robots(SeoSettings $seo): string
    {
        return $seo->indexable
            ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            : 'noindex, nofollow';
    }

    /**
     * Rozměry obrázku pro sdílení. Facebook i LinkedIn si bez nich napoprvé
     * vykreslí odkaz bez obrázku, než si ho sami stáhnou a změří.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function imageSize(?string $image): array
    {
        // Obrázek z cizí domény si nezměříme a stahovat ho kvůli tomu nebudeme.
        if (blank($image) || str_starts_with($image, 'http')) {
            return [null, null];
        }

        // Soubor v repozitáři (/images/…) leží v public/, ne na disku `public`.
        if (str_starts_with($image, '/')) {
            $path = public_path(ltrim($image, '/'));

            if (! is_file($path)) {
                return [null, null];
            }

            $size = @getimagesize($path);

            return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
        }

        return ResponsiveImage::dimensions($image);
    }
}
