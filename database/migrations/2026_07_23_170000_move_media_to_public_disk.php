<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Obrázky nahrané přes Filament skončily na disku `local` (storage/app/private),
 * kam se z webu nedá dostat — v administraci vypadaly v pořádku, na webu byly
 * rozbité. Příčinu řeší config/filament.php a useDisk('public') na modelech;
 * tahle migrace narovná soubory, které se stihly nahrát předtím.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('media')->where('disk', 'local')->get();

        foreach ($rows as $row) {
            $this->moveDirectory((string) $row->id);

            DB::table('media')->where('id', $row->id)->update([
                'disk' => 'public',
                'conversions_disk' => 'public',
            ]);
        }
    }

    public function down(): void
    {
        // Zpět nic nevracíme — `local` byl chybný stav, ne funkční varianta.
    }

    /**
     * Přesune složku média (originál i konverze) z privátního úložiště do veřejného.
     * Na čerstvém prostředí soubory neexistují, proto se jen tiše přeskočí.
     */
    private function moveDirectory(string $id): void
    {
        $from = Storage::disk('local');
        $to = Storage::disk('public');

        if (! $from->exists($id)) {
            return;
        }

        foreach ($from->allFiles($id) as $path) {
            if (! $to->exists($path)) {
                $to->put($path, $from->get($path));
            }
        }

        $from->deleteDirectory($id);
    }
};
