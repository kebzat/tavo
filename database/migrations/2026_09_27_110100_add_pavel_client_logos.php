<?php

use App\Models\ClientLogo;
use App\Support\WebTexts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Pás log je „S kým spolupracujeme", ne Tomovy weby. Přibývají klienti,
 * kterým Pavel dělá kampaně (pavelvcelis.cz, „Svěřili mi své účty a kampaně").
 * Svět Cejlonu je společný klient a v pásu už je.
 *
 * Loga ze stránky měla bílé nebo barevné obdélníky jako pozadí; v repozitáři
 * jsou s průhledným pozadím a oříznutá na obsah.
 *
 * Pořadí se prostřídá, ať vedle sebe nestojí jen weby nebo jen kampaně.
 * Loga, která mezitím přidal správce (nejsou v seznamu), zůstanou na konci.
 */
return new class extends Migration
{
    /** Soubor v database/seeders/assets/loga-klientu → název klienta (jen nová loga). */
    private const NEW_LOGOS = [
        'fitmin.png' => 'Fitmin',
        'realimo.svg' => 'Realimo',
        'noho.png' => 'NOHO',
        'collamedic.png' => 'Collamedic',
        'wood-and-company.png' => 'Wood & Company',
        'ceska-louka.png' => 'Česká louka',
        'atep.png' => 'ATEP',
        'allnature.png' => 'allnature',
        'big-time.png' => 'BIG TIME',
        'jmp.png' => 'JMP',
        'belles-fleurs.png' => 'Belles Fleurs',
    ];

    private const ORDER = [
        'Fitmin', 'Svět Cejlonu', 'Realimo', '2e Kompresory', 'NOHO', 'THEM CARS',
        'Collamedic', 'Včely Uhersko', 'Wood & Company', "Hop'n'Joy", 'Česká louka', 'BSPI',
        'ATEP', 'Natěračství Balcar', 'allnature', 'GEOMA HJ', 'BIG TIME', 'Práce z plošiny',
        'JMP', 'Autoškola Jarkovský', 'Belles Fleurs',
    ];

    public function up(): void
    {
        // Statické texty staré verze pásu („Pro koho jsme stavěli…", odkaz na
        // tomaskebza.cz). Šablona je už nepoužívá, v administraci by jen mátly.
        DB::table('web_texts')->whereIn('key', ['home.loga_nadpis', 'home.loga_popis', 'home.loga_odkaz', 'home.loga_odkaz_url'])->delete();
        WebTexts::forget();

        // Pás zakládá předchozí migrace jen na webu s obsahem; bez něj tu není co doplňovat.
        if (ClientLogo::query()->doesntExist()) {
            return;
        }

        foreach (self::NEW_LOGOS as $file => $name) {
            $path = database_path("seeders/assets/loga-klientu/{$file}");

            if (! File::isFile($path) || ClientLogo::query()->where('name', $name)->exists()) {
                continue;
            }

            ClientLogo::query()->create(['name' => $name, 'published' => true])
                ->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection(ClientLogo::MEDIA_LOGO);
        }

        $position = array_flip(self::ORDER);
        $rest = count(self::ORDER);

        ClientLogo::query()->ordered()->get()->each(function (ClientLogo $logo) use ($position, &$rest): void {
            $logo->order_column = ($position[$logo->name] ?? $rest++) + 1;
            $logo->saveQuietly();
        });
    }

    public function down(): void
    {
        ClientLogo::query()->whereIn('name', array_values(self::NEW_LOGOS))->get()->each->delete();
    }
};
