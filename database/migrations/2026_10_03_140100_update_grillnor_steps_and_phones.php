<?php

use App\Models\Proposal;
use App\Support\ProposalDraft;
use Illuminate\Database\Migrations\Migration;

/**
 * Grill NOR po připomínkách Toma (3. 10. 2026): akční kroky po měsících
 * místo týdnů, v prvním týdnu víc oprav, a návrhy z mobilu v rámečku
 * telefonu nad účtem a bannery. Nové znění je v grillnor.json.
 *
 * Přepíše jen to, co pořád zní jako první verze konceptu. Co mezitím
 * upravili v nástrojích, nechá být.
 */
return new class extends Migration
{
    private const OLD_STEPS = ['Rychlé opravy a Search Console', 'Schůzka nad čísly', 'Focení jídel', 'Nový web s jídelníčkem', 'Registrace, účet a platby', 'Rodiny, firmy a připomenutí'];

    private const OLD_EXAMPLE = 'Registrace, účet a bannery';

    public function up(): void
    {
        $proposal = Proposal::firstWhere('slug', 'grillnor');

        if (! $proposal) {
            return;
        }

        $draft = ProposalDraft::read(ProposalDraft::directory().'/grillnor.json');
        $changes = [];

        $titles = array_column($proposal->steps ?? [], 'title');

        if ($titles === self::OLD_STEPS) {
            $changes += [
                'steps_title' => $draft['steps_title'],
                'steps_intro' => $draft['steps_intro'],
                'steps' => $draft['steps'],
            ];
        } elseif (blank($proposal->steps_title) && $titles === array_column($draft['steps'], 'title')) {
            // Čistá databáze: koncept už má nové kroky, chybí jen nadpis,
            // pro který sloupec vznikl až po importu.
            $changes['steps_title'] = $draft['steps_title'];
        }

        $new = collect($draft['examples'])->firstWhere('placement', 'after_recommendations');
        $examples = collect($proposal->examples ?? [])
            ->map(fn (array $example): array => ($example['title'] ?? null) === self::OLD_EXAMPLE ? $new : $example);

        if ($examples->all() !== ($proposal->examples ?? [])) {
            $changes['examples'] = $examples->all();
        }

        if ($changes) {
            $proposal->update($changes);
        }
    }

    public function down(): void
    {
        // Texty mohli mezitím upravit v nástrojích, vrací se tam.
    }
};
