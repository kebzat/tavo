<?php

use App\Models\Proposal;
use App\Support\ProposalDraft;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

/**
 * Koncept nabídky pro Grill NOR (grillnor.cz, rozvoz obědů Trutnov) z
 * database/seeders/proposals/grillnor.json. Web prošel Tom 3. 10. 2026,
 * jídelníček v návrhu je skutečný z menicka.cz (týden 5.–9. 10.), fotky
 * jídel jsou ilustrační. Marketingové body doplní Pavel v nástrojích.
 *
 * Stránku, která už existuje, import přeskočí. Je nesdílená.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Zmenšeniny dlouhého návrhu homepage se do 128 MB nevejdou.
        ini_set('memory_limit', '1024M');

        $path = ProposalDraft::directory().'/grillnor.json';
        $proposal = ProposalDraft::import($path) ?? Proposal::firstWhere('slug', 'grillnor');

        // Na čisté databázi koncept založí už starší migrace všech konceptů,
        // ještě bez sloupců pro měsíční spolupráci. Doplní se jen prázdné.
        if ($proposal && blank($proposal->packages)) {
            $proposal->update(Arr::only(ProposalDraft::read($path), ['packages_intro', 'packages']));
        }
    }

    public function down(): void
    {
        // Koncept mohli mezitím upravit v nástrojích, maže se tam.
    }
};
