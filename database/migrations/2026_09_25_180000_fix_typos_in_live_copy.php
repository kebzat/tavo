<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Oprava překlepů v textech, které Pavel napsal v administraci
 * (nalezeno na živém webu 25. 9. 2026).
 *
 * Nahrazuje jen přesná chybná slova, zbytek textu nechává být. Když už
 * překlep v databázi není (správce ho opravil sám, nebo text přepsal),
 * migrace nic nezmění.
 */
return new class extends Migration
{
    private const FIXES = [
        'Veškěrá' => 'Veškerá',
        'návštěvíků' => 'návštěvníků',
        'spolupráce — V Meta ads' => 'spolupráce. V Meta ads',
    ];

    public function up(): void
    {
        // Settings: payload je JSON, takže se nahrazuje v dekódované hodnotě.
        // V surovém řetězci by mohla být diakritika zapsaná jako ě.
        $settingsChanged = false;

        DB::table('settings')->orderBy('id')->each(function (object $row) use (&$settingsChanged): void {
            $value = json_decode($row->payload, true);
            $fixed = $this->fix($value);

            if ($fixed !== $value) {
                DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode($fixed)]);
                $settingsChanged = true;
            }
        });

        if ($settingsChanged) {
            Artisan::call('settings:clear-cache');
        }

        DB::table('founders')->orderBy('id')->each(function (object $row): void {
            $bio = $this->fix($row->bio);

            if ($bio !== $row->bio) {
                DB::table('founders')->where('id', $row->id)->update(['bio' => $bio]);
            }
        });
    }

    public function down(): void
    {
        // Překlepy zpátky nevracíme.
    }

    private function fix(mixed $value): mixed
    {
        if (is_string($value)) {
            return strtr($value, self::FIXES);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->fix($item), $value);
        }

        return $value;
    }
};
