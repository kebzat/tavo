<?php

use App\Models\CaseStudy;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;

/**
 * Referencím se širokým náhledem zapne „zobrazit celý obrázek".
 *
 * Rámeček výpisu má poměr 4:3. Náhledy nahrané jako screenshot webu bývají
 * 1512×800 nebo 1456×815, tedy skoro 16:9 — `cover` jim ubere třetinu výšky
 * a zbytek nafoukne. Projede se jednou, u ostatních referencí (poměr kolem 4:3)
 * nechá `cover`, jak bylo.
 *
 * Není to nevratné rozhodnutí: u každé reference se dá volba přepnout
 * v administraci, a tahle migrace už se podruhé nespustí.
 */
return new class extends Migration
{
    /** Poměry, které do rámečku 4:3 sednou bez viditelné ztráty. */
    private const MIN_RATIO = 1.2;

    private const MAX_RATIO = 1.5;

    public function up(): void
    {
        foreach (CaseStudy::query()->get() as $case) {
            $path = $case->thumbPath();

            if ($path === null) {
                continue;
            }

            [$width, $height] = ResponsiveImage::dimensions($path);

            if (! $width || ! $height) {
                continue;
            }

            $ratio = $width / $height;

            if ($ratio >= self::MIN_RATIO && $ratio <= self::MAX_RATIO) {
                continue;
            }

            // saveQuietly — jde o jednorázovou opravu zobrazení, není důvod
            // kvůli ní přepočítávat zmenšeniny nebo budit další posluchače.
            $case->thumb_fit = 'contain';
            $case->saveQuietly();
        }
    }

    public function down(): void
    {
        CaseStudy::query()->where('thumb_fit', 'contain')->update(['thumb_fit' => 'cover']);
    }
};
