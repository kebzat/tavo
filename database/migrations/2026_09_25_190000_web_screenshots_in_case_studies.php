<?php

use App\Models\CaseStudy;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Reference webů a e-shopů dostanou jednotný vizuál: čistý screenshot webu
 * 1920×1200 (16:10) místo fotek notebooku a koláží. Stejný screenshot je
 * náhledem ve výpisu i obrázkem v hlavičce detailu. 2e Kompresory k tomu
 * dostanou blok „Před a po".
 *
 * Screenshoty pochází z tomaskebza.cz/reference a leží v repozitáři
 * v `database/seeders/assets/reference-screenshoty/`, protože `storage/` se
 * při nasazení nepřenáší.
 *
 * Dosavadní obrázky se nemažou. Přesunou se do skryté kolekce `archiv`
 * a `down()` je vrátí zpátky.
 */
return new class extends Migration
{
    private const SOURCE = 'tomaskebza.cz 2026-09';

    private const ARCHIVE = 'archiv';

    private const SLUGS = [
        'vcely-uhersko',
        '2e-kompresory',
        'chrudimlab',
        'hopnjoy',
        'mycomedica',
        'ales-malinsky',
        'helago',
        'rostex',
        'pozarni-zbozi',
        'them-cars',
        'sh-mediace',
        'mesto-chocen',
        'casopis-stavebnictvi',
        'tiyo',
        'milan-schirlo',
        'mekko',
        'kariera-jmk',
        'vas-najem',
        'koor',
    ];

    /** Soubor v repozitáři → cesta na disku `public` (obrázky do bloku, ne do media knihovny). */
    private const BLOCK_IMAGES = [
        '2e-pred.jpg' => 'reference/2e-kompresory-pred.jpg',
        '2e-po.jpg' => 'reference/2e-kompresory-po.jpg',
    ];

    public function up(): void
    {
        foreach (self::SLUGS as $slug) {
            $case = CaseStudy::query()->where('slug', $slug)->first();
            $file = $this->asset("{$slug}.jpg");

            if (! $case || ! $file || $this->alreadyDone($case)) {
                continue;
            }

            $this->archive($case);

            $alt = "Úvodní stránka webu {$case->title}";

            foreach ([CaseStudy::MEDIA_THUMB, CaseStudy::MEDIA_GALLERY] as $collection) {
                $media = $case->addMedia($file)
                    ->preservingOriginal()
                    ->usingFileName("{$slug}.jpg")
                    ->withCustomProperties(['alt' => $alt, 'zdroj' => self::SOURCE])
                    ->toMediaCollection($collection);

                ResponsiveImage::generate($media->getPathRelativeToRoot());
            }
        }

        $this->addBeforeAfterTo2e();
    }

    public function down(): void
    {
        Media::query()
            ->where('model_type', (new CaseStudy)->getMorphClass())
            ->where('custom_properties->zdroj', self::SOURCE)
            ->get()
            ->each->delete();

        Media::query()
            ->where('model_type', (new CaseStudy)->getMorphClass())
            ->where('collection_name', self::ARCHIVE)
            ->get()
            ->each(function (Media $media): void {
                $media->collection_name = $media->getCustomProperty('puvodni_kolekce', CaseStudy::MEDIA_GALLERY);
                $media->forgetCustomProperty('puvodni_kolekce');
                $media->save();
            });

        // Blok „Před a po" u 2e zůstává. Správce s ním mohl mezitím pracovat.
    }

    private function asset(string $name): ?string
    {
        $path = database_path("seeders/assets/reference-screenshoty/{$name}");

        return File::isFile($path) ? $path : null;
    }

    private function alreadyDone(CaseStudy $case): bool
    {
        return $case->getMedia(CaseStudy::MEDIA_THUMB)
            ->contains(fn (Media $media): bool => $media->getCustomProperty('zdroj') === self::SOURCE);
    }

    /**
     * Náhled je `singleFile()`, takže by ho nový obrázek smazal. Proto se staré
     * obrázky nejdřív přesunou jinam; soubory na disku zůstanou, kde jsou.
     */
    private function archive(CaseStudy $case): void
    {
        foreach ([CaseStudy::MEDIA_THUMB, CaseStudy::MEDIA_GALLERY] as $collection) {
            foreach ($case->getMedia($collection) as $media) {
                $media->collection_name = self::ARCHIVE;
                $media->setCustomProperty('puvodni_kolekce', $collection);
                $media->save();
            }
        }
    }

    private function addBeforeAfterTo2e(): void
    {
        $case = CaseStudy::query()->where('slug', '2e-kompresory')->first();

        if (! $case) {
            return;
        }

        $blocks = $case->blocks ?? [];

        if (collect($blocks)->contains(fn (array $block): bool => ($block['type'] ?? null) === 'before_after')) {
            return;
        }

        foreach (self::BLOCK_IMAGES as $source => $target) {
            $path = $this->asset($source);

            if ($path && ! Storage::disk('public')->exists($target)) {
                Storage::disk('public')->put($target, File::get($path));
            }

            ResponsiveImage::generate($target);
        }

        // Hned pod „Výchozí stav", stejně jako na tomaskebza.cz.
        array_unshift($blocks, [
            'type' => 'before_after',
            'data' => [
                'tone' => 'cream',
                'eyebrow' => 'Redesign',
                'title' => 'Starý e-shop vedle nového',
                'perex' => 'Tažením čáry porovnáte úvodní stránku před redesignem a po něm.',
                'before' => self::BLOCK_IMAGES['2e-pred.jpg'],
                'after' => self::BLOCK_IMAGES['2e-po.jpg'],
                'before_alt' => 'Původní úvodní stránka e-shopu 2e Kompresory',
                'after_alt' => 'Nová úvodní stránka e-shopu 2e Kompresory s dlaždicemi kategorií',
                'before_label' => 'Před',
                'after_label' => 'Po',
            ],
        ]);

        $case->blocks = $blocks;
        $case->saveQuietly();
    }
};
