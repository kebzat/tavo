<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Support\Ads\Format;

/**
 * Víc nákupů než zahájených objednávek. Každý nákup projde pokladnou, takže
 * výrazně víc nákupů ukazuje na zdvojenou událost (pixel i Conversions API
 * bez deduplikace, nákup na děkovací stránce při každém načtení).
 */
class PurchasesAboveCheckouts implements Rule
{
    private const MIN_CHECKOUTS = 5;

    private const RATIO = 1.3;

    public function key(): string
    {
        return 'measurement_duplicates';
    }

    public function evaluate(AlertContext $context): array
    {
        if ($context->goal() !== PrimaryGoal::Purchases) {
            return [];
        }

        $week = $context->totals($context->week());
        $purchases = $week->get('purchases');
        $checkouts = $week->get('checkouts');

        if ($checkouts < self::MIN_CHECKOUTS || $purchases <= $checkouts * self::RATIO) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Warning,
            'Měření: víc nákupů než zahájených objednávek',
            'Je to podezření, ne jistota. Ověřte deduplikaci pixelu a Conversions API (event_id) a jestli se nákup neodesílá při každém načtení děkovací stránky. Než se to vyjasní, neberte ROAS ani cenu za nákup jako spolehlivé.',
            ['purchases' => $purchases, 'checkouts' => $checkouts],
            facts: 'Za posledních 7 dní: '.Format::count($purchases).' nákupů, ale jen '.Format::count($checkouts).' zahájených objednávek. Nákup bez pokladny je výjimka, tolik jich ukazuje spíš na zdvojené měření.',
        )];
    }
}
