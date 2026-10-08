<?php

use App\Models\Proposal;
use Illuminate\Database\Migrations\Migration;

/**
 * TTreality: hodnocení sociálních sítí a výkonnostního marketingu, stejné
 * dlaždice jako u ostatních konceptů (Annie's Books). Prázdná dlaždice
 * „Marketing“ z prvního konceptu se nahradí.
 *
 * Sociální sítě 1 z 5: Facebook 386 sledujících, 17 recenzí, poslední
 * viditelný příspěvek 25. 4. 2025, Instagram není, web na sítě neodkazuje.
 * Výkonnostní marketing 1 z 5: v knihovně reklam Mety nic, web nenačítá
 * GA ani Meta pixel, retargeting Seznamu běží bez souhlasu (ověřeno 1. 10.
 * a 8. 10. 2026).
 *
 * Hodnotu vyplněnou v nástrojích nepřepíše.
 */
return new class extends Migration
{
    private const SCORES = [
        'Sociální sítě' => '1 z 5',
        'Výkonnostní marketing' => '1 z 5',
    ];

    public function up(): void
    {
        $proposal = Proposal::firstWhere('slug', 'tt-reality');

        if (! $proposal) {
            return;
        }

        $tiles = collect($proposal->highlights ?? [])
            ->reject(fn (array $tile): bool => ($tile['label'] ?? null) === 'Marketing' && blank($tile['value'] ?? null))
            ->values()
            ->all();

        foreach (self::SCORES as $label => $value) {
            $index = array_search($label, array_column($tiles, 'label'), true);

            if ($index === false) {
                $tiles[] = ['label' => $label, 'value' => $value];
            } elseif (blank($tiles[$index]['value'] ?? null)) {
                $tiles[$index]['value'] = $value;
            }
        }

        if ($tiles !== $proposal->highlights) {
            $proposal->update(['highlights' => $tiles]);
        }
    }

    public function down(): void
    {
        // Hodnocení mohli mezitím upravit v nástrojích, vrací se tam.
    }
};
