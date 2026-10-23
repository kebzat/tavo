<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\Format;

/**
 * Cena za konverzi za poslední týden je nad cílem (klienta nebo skupiny).
 * Hodnotí se jen nad minimem konverzí, u malého účtu dělá jedna objednávka
 * navíc nebo míň rozdíl desítek procent.
 */
class CostAboveTarget implements Rule
{
    public function key(): string
    {
        return 'cost_above_target';
    }

    public function evaluate(AlertContext $context): array
    {
        $goal = $context->goal();
        $target = $context->targetCpa();

        if (! $goal->isPerformance() || ! $target) {
            return [];
        }

        $week = $context->totals($context->week());
        $conversions = $week->conversions($goal);
        $cost = $week->costPerConversion($goal);

        if ($conversions < $context->threshold('min_conversions') || $cost === null) {
            return [];
        }

        $limit = $target * (1 + $context->threshold('cpa_over_pct') / 100);

        if ($cost <= $limit) {
            return [];
        }

        $currency = $context->currency();
        $previous = $context->totals($context->week()->previous())->costPerConversion($goal);

        return [new Finding(
            AlertSeverity::Warning,
            $goal->costLabel().' '.Format::unitPrice($cost, $currency).' je nad cílem '.Format::unitPrice($target, $currency),
            'Podívejte se, které kampaně a sady cenu táhnou nahoru, a přesuňte rozpočet k těm levnějším. Zkontrolujte i frekvenci a stáří kreativ a jestli se nezměnilo něco na webu (ceny, doprava, pokladna).',
            ['cost' => $cost, 'target' => $target, 'conversions' => $conversions, 'previous_cost' => $previous],
            facts: $goal->costLabel().' za 7 dní '.Format::unitPrice($cost, $currency).' proti cíli '.Format::unitPrice($target, $currency)
                .' (+'.Format::number(($cost / $target - 1) * 100).' %, hranice upozornění +'.Format::number($context->threshold('cpa_over_pct')).' %).'
                .($previous !== null ? ' Týden předtím '.Format::unitPrice($previous, $currency).'.' : ''),
        )];
    }
}
