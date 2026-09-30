<?php

use App\Models\Proposal;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * IQ Hračky: sekce Kdysi, dnes a s námi. Web z roku 2016 (web.archive.org),
 * dnešní homepage (screenshot z 30. 9. 2026) a návrh refreshe.
 *
 * Doplní se jen do prázdné sekce, co správce mezitím upravil, zůstane.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const IMAGES = [
        '2016' => 'spoluprace/iq-hracky-web-2016.jpg',
        '2026' => 'spoluprace/iq-hracky-web-2026.jpg',
        'navrh' => 'spoluprace/iq-hracky-navrh-homepage.jpg',
    ];

    public function up(): void
    {
        $proposal = Proposal::where('slug', 'iq-hracky')->first();

        if (! $proposal || filled($proposal->timeline)) {
            return;
        }

        foreach (self::IMAGES as $path) {
            $this->copyImage($path);
        }

        $proposal->update([
            'timeline_intro' => 'Web IQ Hraček za deset let. Po kliknutí na obrázek se otevře celý.',
            'timeline' => [
                [
                    'label' => '2016',
                    'title' => 'Kdysi',
                    'body' => 'Zelená, oranžová a logo s hvězdou. Na svou dobu veselý e-shop, který si lidé zapamatovali.',
                    'image' => $this->stored(self::IMAGES['2016']),
                    'image_alt' => 'E-shop IQ Hračky v roce 2016',
                ],
                [
                    'label' => '2026',
                    'title' => 'Dnes',
                    'body' => 'Rozvržení je modernější, logo a barvy zůstaly z roku 2016. Hodnocení z Heureky ani důvody nakoupit na první obrazovce nejsou.',
                    'image' => $this->stored(self::IMAGES['2026']),
                    'image_alt' => 'Dnešní homepage e-shopu IQ Hračky',
                ],
                [
                    'label' => 'Návrh',
                    'title' => 'S námi',
                    'body' => 'Hodnocení z Heureky nahoře, výběr hračky podle věku a důvody nakoupit hned pod hlavičkou. Na Shoptetu, bez migrace.',
                    'image' => $this->stored(self::IMAGES['navrh']),
                    'image_alt' => 'Návrh nové homepage e-shopu IQ Hračky',
                ],
            ],
        ]);
    }

    public function down(): void
    {
        Proposal::where('slug', 'iq-hracky')->update(['timeline' => null, 'timeline_intro' => null]);
    }

    private function stored(string $path): ?string
    {
        return Storage::disk('public')->exists($path) ? $path : null;
    }

    /** Obrázky se do gitu nedostanou přes storage/, leží v database/seeders/assets/. */
    private function copyImage(string $path): void
    {
        $source = database_path('seeders/assets/'.$path);
        $disk = Storage::disk('public');

        if (! File::isFile($source) || $disk->exists($path)) {
            return;
        }

        $disk->put($path, File::get($source));
        ResponsiveImage::generate($path);
    }
};
