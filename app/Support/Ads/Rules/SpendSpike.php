<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\Format;
use App\Support\Ads\Period;

/** Včera výrazně vyšší útrata než průměr předchozího týdne. */
class SpendSpike implements Rule
{
    /** Pod tímhle průměrem denní útraty skok nehlásíme, jde o drobné. */
    private const MIN_DAILY = 50;

    public function key(): string
    {
        return 'spend_spike';
    }

    public function evaluate(AlertContext $context): array
    {
        $yesterday = $context->totals(Period::between($context->yesterday(), $context->yesterday()))->spend();
        $average = $context->totals(Period::between($context->yesterday()->subDays(7), $context->yesterday()->subDay()))->spend() / 7;

        if ($average < self::MIN_DAILY) {
            return [];
        }

        $increase = ($yesterday - $average) / $average * 100;

        if ($increase < $context->threshold('spend_spike_pct')) {
            return [];
        }

        $currency = $context->currency();

        return [new Finding(
            AlertSeverity::Info,
            'Včera o '.Format::number($increase).' % vyšší útrata než obvykle',
            'Zkontrolujte, jestli někdo nezvedl rozpočet, nespustil novou kampaň nebo jestli kampaň s rozpočtem na dobu trvání nedohání útratu. Když šlo o záměr, zapište ho do historie zásahů.',
            ['yesterday' => $yesterday, 'average' => $average],
            facts: 'Včera '.Format::money($yesterday, $currency).' proti průměru předchozích 7 dní '.Format::money($average, $currency).' denně.',
        )];
    }
}
