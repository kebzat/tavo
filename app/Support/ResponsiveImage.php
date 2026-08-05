<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Throwable;

/**
 * Zmenšeniny obrázků pro web.
 *
 * Do administrace se nahrávají originály — klidně dvoumegový PNG z fotoaparátu.
 * Návštěvník takový soubor nemá důvod stahovat, tak z něj uděláme sadu užších
 * variant ve WebP a prohlížeči nabídneme přes `srcset` tu, která odpovídá jeho
 * displeji.
 *
 * Varianty vznikají při uložení (viz App\Observers\ImageDerivativeObserver)
 * a dají se hromadně dogenerovat příkazem `obrazky:zmensit`. Kdyby přesto
 * chyběly — třeba po přenosu databáze bez souborů — dopočítají se při prvním
 * vykreslení, aby na webu nikdy nechyběl obrázek.
 *
 * Jen WebP, bez fallbacku na původní formát: web stojí na `color-mix(in oklab)`
 * a `aspect-ratio`, což umí právě ty prohlížeče, které umí i WebP. Druhá sada
 * souborů v JPEG by tak zabírala místo pro nikoho.
 */
final class ResponsiveImage
{
    /**
     * Šířky variant. Pokrývají mobil, tablet a desktop včetně dvojnásobné
     * hustoty pixelů; širší variantu než originál nevyrábíme.
     *
     * @var list<int>
     */
    private const WIDTHS = [480, 768, 1024, 1440, 1920];

    /** Podadresář disku `public`, kam varianty ukládáme. */
    private const DIRECTORY = 'zmenseniny';

    private const QUALITY = 82;

    /** O kolik musí být originál širší než nejbližší stupeň, aby se vyplatil zvlášť. */
    private const EXTRA_WIDTH_THRESHOLD = 1.15;

    /**
     * Formáty, které umíme zmenšit. SVG je vektor — ten se posílá tak, jak je,
     * a GIF by přišel o animaci.
     *
     * @var list<string>
     */
    private const CONVERTIBLE = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Obrázek připravený k vykreslení. `null` znamená „není co zobrazit"
     * a šablona podle toho sekci vynechá.
     *
     * @return array{src: string, srcset: ?string, width: ?int, height: ?int, alt: string}|null
     */
    public static function make(?string $path, string $alt = ''): ?array
    {
        $path = self::normalize($path);

        if ($path === null) {
            return null;
        }

        $disk = self::disk();

        if (! $disk->exists($path)) {
            return null;
        }

        [$width, $height] = self::dimensions($path);

        if (! self::isConvertible($path) || $width === null) {
            return [
                'src' => $disk->url($path),
                'srcset' => null,
                'width' => $width,
                'height' => $height,
                'alt' => $alt,
            ];
        }

        $variants = self::variants($path, $width);

        // Nejširší varianta je zároveň `src` — prohlížeč bez podpory `srcset`
        // (a takový sem prakticky nedojde) dostane rozumnou velikost.
        $widest = array_key_last($variants);

        return [
            'src' => $variants[$widest] ?? $disk->url($path),
            'srcset' => self::srcset($variants),
            'width' => $widest ?: $width,
            'height' => $widest && $width ? (int) round($height * ($widest / $width)) : $height,
            'alt' => $alt,
        ];
    }

    /**
     * Vyrobí všechny chybějící varianty. Volá se při uložení obsahu, ať na to
     * návštěvník nečeká.
     */
    public static function generate(?string $path): void
    {
        $path = self::normalize($path);

        if ($path === null || ! self::isConvertible($path) || ! self::disk()->exists($path)) {
            return;
        }

        [$width] = self::dimensions($path);

        if ($width === null) {
            return;
        }

        self::variants($path, $width);
    }

    /**
     * Rozměry originálu. Čtou se ze souboru, takže si výsledek pamatujeme —
     * klíč nese čas změny, po přenahrání obrázku tedy platnost sama vyprší.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function dimensions(string $path): array
    {
        $disk = self::disk();

        if (! $disk->exists($path)) {
            return [null, null];
        }

        return Cache::rememberForever(
            'obrazek-rozmery-'.md5($path).'-'.$disk->lastModified($path),
            function () use ($disk, $path): array {
                $size = @getimagesize($disk->path($path));

                return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
            },
        );
    }

    /** Cesta relativní k disku `public` — přijme cestu i hotovou URL. */
    public static function normalize(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        // Z absolutní URL zbude jen cesta; `/storage/` je veřejný odkaz na disk.
        $path = (string) parse_url($path, PHP_URL_PATH) ?: $path;
        $path = ltrim($path, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        // Cesta ven z disku (../) by pustila ke čtení cizích souborů.
        return str_contains($path, '..') ? null : $path;
    }

    /**
     * Varianty seřazené podle šířky: [šířka => URL]. Chybějící se dogenerují.
     *
     * @return array<int, string>
     */
    private static function variants(string $path, int $originalWidth): array
    {
        $disk = self::disk();
        $variants = [];

        foreach (self::targetWidths($originalWidth) as $width) {
            $target = self::variantPath($path, $width);

            if (! $disk->exists($target) && ! self::render($path, $target, $width)) {
                continue;
            }

            $variants[$width] = $disk->url($target);
        }

        // Kdyby se nepovedla ani jedna varianta, pošleme originál, jak je.
        if ($variants === []) {
            $variants[$originalWidth] = $disk->url($path);
        }

        return $variants;
    }

    /**
     * Šířky, ve kterých se má obrázek vyrobit.
     *
     * Zvětšovat nemá smysl — z úzkého originálu by vznikla rozmazanina. Když ale
     * originál padne mezi dva stupně a od toho nižšího je znatelně širší, přidáme
     * ho v jeho vlastní šířce, ať se na jemném displeji nezobrazuje zbytečně
     * měkký. Nad nejširší stupeň nejdeme, tam už je rozdíl nepoznatelný a soubor
     * zbytečně velký.
     *
     * @return list<int>
     */
    private static function targetWidths(int $originalWidth): array
    {
        $widths = array_values(array_filter(
            self::WIDTHS,
            fn (int $width): bool => $width < $originalWidth,
        ));

        $capped = min($originalWidth, max(self::WIDTHS));
        $largest = $widths === [] ? 0 : max($widths);

        // Pod 15 % rozdílu by druhý soubor s prakticky stejným obrázkem jen
        // zabíral místo.
        if ($capped > $largest * self::EXTRA_WIDTH_THRESHOLD) {
            $widths[] = $capped;
        }

        return $widths;
    }

    private static function render(string $source, string $target, int $width): bool
    {
        $disk = self::disk();

        try {
            $disk->makeDirectory(dirname($target));

            Image::load($disk->path($source))
                ->width($width)
                ->format('webp')
                ->quality(self::QUALITY)
                ->save($disk->path($target));

            return true;
        } catch (Throwable $e) {
            // Rozbitý nebo neúplný soubor nesmí shodit stránku — vykreslí se originál.
            Log::warning('Zmenšeninu se nepodařilo vyrobit.', [
                'obrazek' => $source,
                'sirka' => $width,
                'chyba' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @param  array<int, string>  $variants */
    private static function srcset(array $variants): ?string
    {
        if (count($variants) < 2) {
            return null;
        }

        $parts = [];

        foreach ($variants as $width => $url) {
            $parts[] = $url.' '.$width.'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Cesta varianty kopíruje cestu originálu, jen v jiném adresáři a s šířkou
     * v názvu: `1/foto.png` → `zmenseniny/1/foto-768.webp`.
     */
    private static function variantPath(string $path, int $width): string
    {
        $directory = trim(dirname($path), '.'.DIRECTORY_SEPARATOR);
        $name = pathinfo($path, PATHINFO_FILENAME);

        return self::DIRECTORY.'/'.($directory !== '' ? $directory.'/' : '').$name.'-'.$width.'.webp';
    }

    private static function isConvertible(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::CONVERTIBLE, true);
    }

    private static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk;
    }
}
