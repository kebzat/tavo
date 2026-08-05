<?php

namespace App\Console\Commands;

use App\Support\ImageDerivatives;
use App\Support\ResponsiveImage;
use Illuminate\Console\Command;

/**
 * Hromadně vyrobí zmenšeniny ke všem obrázkům v obsahu.
 *
 * Nové obrázky si zmenšeniny udělají samy při uložení. Tenhle příkaz je pro
 * obsah, který vznikl dřív, a po nasazení na nový server.
 */
class GenerateImageDerivatives extends Command
{
    protected $signature = 'obrazky:zmensit';

    protected $description = 'Vyrobí zmenšeniny (WebP) ke všem obrázkům v obsahu';

    public function handle(): int
    {
        $paths = ImageDerivatives::all();

        if ($paths->isEmpty()) {
            $this->info('V obsahu není žádný obrázek.');

            return self::SUCCESS;
        }

        $this->withProgressBar($paths, ResponsiveImage::generate(...));

        $this->newLine(2);
        $this->info("Hotovo — zpracováno {$paths->count()} obrázků.");

        return self::SUCCESS;
    }
}
