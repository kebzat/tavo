<?php

use App\Support\ProposalDraft;
use Illuminate\Database\Migrations\Migration;

/**
 * Koncepty nabídek spolupráce z database/seeders/proposals/*.json
 * (e-shopy a weby ze seznamu z 1. 10. 2026). Web za nás prošel Tom,
 * marketingové body doplní Pavel v nástrojích. Všechny jsou nesdílené.
 *
 * Stránku, která už existuje, import přeskočí.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ProposalDraft::files() as $path) {
            ProposalDraft::import($path);
        }
    }

    public function down(): void
    {
        // Koncepty mohli mezitím upravit v nástrojích, mažou se tam.
    }
};
