<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Support\Ads\Format;

/**
 * Nákupy chodí, ale bez hodnoty. Měření posílá událost nákupu bez ceny
 * objednávky, takže ROAS a hodnota nákupů jsou nula, i když se prodává.
 * Problém měření, ne výkonu reklam.
 */
class PurchasesWithoutValue implements Rule
{
    private const MIN_PURCHASES = 3;

    public function key(): string
    {
        return 'measurement_value';
    }

    public function evaluate(AlertContext $context): array
    {
        if ($context->goal() !== PrimaryGoal::Purchases) {
            return [];
        }

        $week = $context->totals($context->week());

        if ($week->get('purchases') < self::MIN_PURCHASES || $week->get('purchase_value') > 0) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Critical,
            'Měření: nákupy bez hodnoty objednávky',
            'Za posledních 7 dní reklamy hlásí '.Format::count($week->get('purchases')).' nákupů, ale jejich hodnota je nula. '
                .'ROAS a hodnota nákupů proto neplatí, i když se prodává.'
                ."\n\nJde o problém měření, ne o výkon reklam. Zkontrolujte, jestli událost Purchase posílá value a currency (Meta Pixel Helper, Správce událostí → Testovat události), hlavně po úpravě pokladny nebo doplňku pro měření.",
            ['purchases' => $week->get('purchases'), 'purchase_value' => 0],
        )];
    }
}
