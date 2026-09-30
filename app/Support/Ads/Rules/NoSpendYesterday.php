<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\Format;
use App\Support\Ads\Period;

/** Včera se neutratilo nic, i když předtím reklamy běžely. */
class NoSpendYesterday implements Rule
{
    public function key(): string
    {
        return 'no_spend';
    }

    public function evaluate(AlertContext $context): array
    {
        $yesterday = $context->totals(Period::between($context->yesterday(), $context->yesterday()));
        $before = $context->totals(Period::between($context->yesterday()->subDays(7), $context->yesterday()->subDay()));

        if ($yesterday->spend() > 0 || $before->spend() <= 0) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Warning,
            'Včera reklamy neutratily nic',
            'Předchozí týden reklamy běžely ('.Format::money($before->spend(), $context->currency()).'), včera se ale neutratila ani koruna. '
                .'Zkontrolujte, jestli kampaně neskončily, nevyčerpal se jejich rozpočet nebo neprošla platba. Když je to záměr (sezóna, dovolená), upozornění zamítněte.',
            ['previous_week_spend' => $before->spend()],
        )];
    }
}
