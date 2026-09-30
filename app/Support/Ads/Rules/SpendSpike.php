<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\Format;
use App\Support\Ads\Period;

/** Včera se utratilo výrazně víc než obvykle. */
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

        if ($increase < $context->settings->spend_spike_pct) {
            return [];
        }

        $currency = $context->currency();

        return [new Finding(
            AlertSeverity::Info,
            'Včera o '.Format::number($increase).' % vyšší útrata než obvykle',
            'Včera '.Format::money($yesterday, $currency).', průměr předchozího týdne '.Format::money($average, $currency).' denně.'
                ."\n\nZkontrolujte, jestli někdo nezvedl rozpočet, nespustil novou kampaň nebo jestli kampaň s rozpočtem na dobu trvání nedohání útratu.",
            ['yesterday' => $yesterday, 'average' => $average],
        )];
    }
}
