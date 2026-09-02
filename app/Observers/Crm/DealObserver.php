<?php

namespace App\Observers\Crm;

use App\Enums\Crm\DealStage;
use App\Models\Crm\Deal;

/**
 * Fáze obchodu s sebou táhne tři odvozené hodnoty: pravděpodobnost, datum
 * změny fáze a datum uzavření. Kdyby je vyplňoval formulář, kanban ani
 * strojová změna fáze by je nenastavily.
 */
class DealObserver
{
    public function creating(Deal $deal): void
    {
        $deal->stage_changed_at ??= now();
        $deal->probability ??= $deal->stage?->defaultProbability() ?? DealStage::Lead->defaultProbability();
    }

    public function updating(Deal $deal): void
    {
        if (! $deal->isDirty('stage')) {
            return;
        }

        $deal->stage_changed_at = now();

        // Pravděpodobnost se srovná podle nové fáze, pokud si ji uživatel
        // právě ručně nepřepisuje — ruční hodnota má vždy přednost.
        if (! $deal->isDirty('probability')) {
            $deal->probability = $deal->stage->defaultProbability();
        }

        // Nabídku počítáme jednou. Když se obchod vrátí o fázi zpět a nabídka
        // odejde znovu, je to pořád tentýž obchod a KPI ho nemá počítat dvakrát.
        if ($deal->stage === DealStage::ProposalSent) {
            $deal->proposal_sent_at ??= now();
        }

        $deal->won_at = $deal->stage === DealStage::Won ? ($deal->won_at ?? now()) : null;
        $deal->lost_at = $deal->stage === DealStage::Lost ? ($deal->lost_at ?? now()) : null;

        if ($deal->stage !== DealStage::Lost) {
            $deal->lost_reason = null;
        }
    }
}
