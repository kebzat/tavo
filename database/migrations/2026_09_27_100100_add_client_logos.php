<?php

use App\Models\CaseStudy;
use App\Models\ClientLogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * Prvních deset log: weby a e-shopy, které Tom postavil přímo pro klienty
 * (tomaskebza.cz/reference, bez agenturních projektů).
 *
 * Loga pochází z hlaviček webů klientů. 2e Kompresory a Autoškola Jarkovský
 * tam mají bílou verzi pro tmavou hlavičku, v repozitáři jsou přebarvené
 * (2e na firemní červenou #e30d33, Jarkovský text na tmavou), jinak by na
 * světlém podkladu zmizely. ChrudimLab, SH Mediace a Nekalendář mají v hlavičce
 * jen značku bez názvu a Lucie Junková bílou kresbu se světle žlutým písmem,
 * na pásu by nebyly čitelné, proto chybí.
 *
 * Běží jen na webu s obsahem (prázdnou databázi plní seeder) a jen když
 * v tabulce ještě nic není.
 */
return new class extends Migration
{
    /** Soubor v database/seeders/assets/loga-klientu → název klienta. */
    private const LOGOS = [
        'svet-cejlonu.png' => 'Svět Cejlonu',
        '2e-kompresory.svg' => '2e Kompresory',
        'them-cars.png' => 'THEM CARS',
        'vcely-uhersko.svg' => 'Včely Uhersko',
        'hopnjoy.svg' => "Hop'n'Joy",
        'bspi.png' => 'BSPI',
        'nateracstvi-balcar.svg' => 'Natěračství Balcar',
        'geoma-hj.png' => 'GEOMA HJ',
        'prace-z-plosiny.svg' => 'Práce z plošiny',
        'autoskola-jarkovsky.svg' => 'Autoškola Jarkovský',
    ];

    public function up(): void
    {
        if (CaseStudy::query()->doesntExist() || ClientLogo::query()->exists()) {
            return;
        }

        $order = 1;

        foreach (self::LOGOS as $file => $name) {
            $path = database_path("seeders/assets/loga-klientu/{$file}");

            if (! File::isFile($path)) {
                continue;
            }

            $logo = ClientLogo::query()->create(['name' => $name, 'order_column' => $order++, 'published' => true]);

            $logo->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection(ClientLogo::MEDIA_LOGO);
        }
    }

    public function down(): void
    {
        // Tabulku i s logy maže migrace, která ji zakládá.
    }
};
